<?php
namespace App\Http\Controllers;

use App\Models\{Household,HouseholdLinkRequest,User,Zone};
use App\Services\NoticeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Cache\LockTimeoutException;

class HouseholdLinkController extends Controller
{
    public function index(Request $request)
    {
        $role=$request->user()->role_code;
        abort_unless(in_array($role,['resident','admin','superadmin'],true),403);
        $staff=$role!=='resident';
        $query=HouseholdLinkRequest::with(['resident','zone','household'])->orderBy('created_at','desc');
        $status=$request->query('status',$staff?'pending':'all');
        abort_unless(is_string($status) && in_array($status,['all','pending','approved','rejected'],true),422);
        if(!$staff)$query->where('resident_user_id',(string)$request->user()->id);
        if($status!=='all')$query->where('status',$status);
        $requests=$query->paginate(20)->withQueryString();
        $hasLink=false;
        $hasPending=false;
        if(!$staff){
            $hasLink=Household::where('resident_user_id',(string)$request->user()->id)
                ->where('is_active',true)->whereNull('archived_at')->exists();
            $hasPending=HouseholdLinkRequest::where('resident_user_id',(string)$request->user()->id)
                ->where('status','pending')->exists();
        }
        $householdsByZone=collect();
        if($staff){
            $zoneIds=$requests->getCollection()->pluck('zone_id')->filter()->map(fn($id)=>(string)$id)->unique()->all();
            if($zoneIds){
                $householdsByZone=Household::whereIn('zone_id',$zoneIds)->where('is_active',true)
                    ->whereNull('archived_at')->orderBy('reference')->get()->groupBy('zone_id');
            }
        }
        return view('fees.household-links',compact('requests','staff','status','hasLink','hasPending','householdsByZone'));
    }

    public function store(Request $request,NoticeService $notices)
    {
        $user=$request->user();
        abort_unless($user->role_code==='resident',403);
        $zone=Zone::find((string)$user->zone_id);
        if(!$zone || !$zone->is_active){
            throw ValidationException::withMessages(['location'=>'Your account needs an active collection zone. Contact the barangay office.']);
        }
        $data=$request->validate([
            'reference'=>['nullable','string','max:100'],
            'location'=>['required','string','min:5','max:250'],
            'note'=>['nullable','string','max:500'],
        ]);
        try{
            $entry=Cache::lock('household-link-resident:'.$user->id,20)->block(5,function()use($user,$data,$zone){
                if(Household::where('resident_user_id',(string)$user->id)->where('is_active',true)
                    ->whereNull('archived_at')->exists()){
                    throw ValidationException::withMessages(['location'=>'Your account already has an active household link. Contact staff for corrections.']);
                }
                if(HouseholdLinkRequest::where('resident_user_id',(string)$user->id)->where('status','pending')->exists()){
                    throw ValidationException::withMessages(['location'=>'You already have a pending household link request.']);
                }
                return HouseholdLinkRequest::create([
                    'resident_user_id'=>(string)$user->id,'zone_id'=>(string)$zone->id,
                    'reference'=>trim($data['reference']??'')?:null,
                    'location'=>trim($data['location']),'note'=>trim($data['note']??'')?:null,
                    'status'=>'pending',
                ]);
            });
        }catch(LockTimeoutException $error){
            throw ValidationException::withMessages(['location'=>'Another request is being processed. Please retry.']);
        }
        $notices->householdLinkSubmitted($entry);
        return redirect()->route('fees.household-links.index')->with('status','Household link requested. Await administrator verification before paying.');
    }

    public function review(Request $request,string $link,NoticeService $notices)
    {
        abort_unless(in_array($request->user()->role_code,['admin','superadmin'],true),403);
        $data=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'link_mode'=>['required_if:decision,approve','nullable','in:existing,new'],
            'household_id'=>['nullable','string','max:64'],
            'household_reference'=>['nullable','string','max:100'],
            'household_location'=>['nullable','string','max:250'],
            'reason'=>['required_if:decision,reject','nullable','string','max:500'],
            'identity_confirmed'=>['nullable','boolean'],
        ]);
        if($data['decision']==='approve' && !$request->boolean('identity_confirmed')){
            throw ValidationException::withMessages(['identity_confirmed'=>'Verify residency, identity and authorization to access this household before approving.']);
        }
        if($data['decision']==='reject' && mb_strlen(trim($data['reason']??''))<5){
            throw ValidationException::withMessages(['reason'=>'Provide a reason for rejection.']);
        }
        $entry=HouseholdLinkRequest::findOrFail($link);
        try{
            $entry=Cache::lock('household-link-resident:'.$entry->resident_user_id,20)->block(5,
                function()use($entry,$data,$request){
                    $current=HouseholdLinkRequest::findOrFail($entry->id);
                    if($current->status!=='pending'){
                        throw ValidationException::withMessages(['decision'=>'This request was already reviewed.']);
                    }
                    if($data['decision']==='approve'){
                        $resident=User::find($current->resident_user_id);
                        if(!$resident || !$resident->is_active || $resident->role_code!=='resident'
                            || (string)$resident->zone_id!==(string)$current->zone_id){
                            throw ValidationException::withMessages(['decision'=>'The resident account or zone has changed. Reject and request a new submission.']);
                        }
                        $zone=Zone::find((string)$current->zone_id);
                        if(!$zone || !$zone->is_active){
                            throw ValidationException::withMessages(['decision'=>'This collection zone is not active. Verify the zone before approving.']);
                        }
                        $residentId=(string)$resident->id;
                        $household=null;
                        if($data['link_mode']==='new'){
                            $reference=trim($data['household_reference']??'');
                            $location=trim($data['household_location']??'');
                            if($reference==='' || mb_strlen($location)<5){
                                throw ValidationException::withMessages(['household_reference'=>'Enter a verified household reference and address to create a new registry record.']);
                            }
                            $household=Cache::lock('household-registry-zone:'.$zone->id,20)->block(5,
                                function()use($zone,$reference,$location,$residentId,$current,$request){
                                    // Do not create a second record if a reference or exact address is already registered.
                                    $existing=Household::where('zone_id',(string)$zone->id)->get()
                                        ->filter(fn($h)=>mb_strtolower(trim((string)$h->reference))===mb_strtolower($reference)
                                            || mb_strtolower(trim((string)$h->location_description))===mb_strtolower($location));
                                    if($existing->isNotEmpty()){
                                        // Recover a previously created household if the request status write failed.
                                        if($existing->count()===1 && (string)$existing->first()->link_request_id===(string)$current->id
                                            && (string)$existing->first()->resident_user_id===$residentId
                                            && $existing->first()->is_active && !$existing->first()->archived_at){
                                            return $existing->first();
                                        }
                                        throw ValidationException::withMessages(['household_reference'=>'A matching household reference or address already exists in this zone. Select the existing record or resolve the duplicate in Household Registry.']);
                                    }
                                    if(Household::where('resident_user_id',$residentId)->where('is_active',true)->whereNull('archived_at')->exists()){
                                        throw ValidationException::withMessages(['household_reference'=>'This resident already has an active household link.']);
                                    }
                                    return Household::create([
                                        'zone_id'=>(string)$zone->id,'reference'=>$reference,
                                        'location_description'=>$location,'resident_user_id'=>$residentId,
                                        'is_active'=>true,'link_request_id'=>(string)$current->id,
                                        'created_by'=>(string)$request->user()->id,'change_history'=>[],
                                    ]);
                                });
                        }else{
                            $household=Household::find($data['household_id']??'');
                            if(!$household || (string)$household->zone_id!==(string)$zone->id
                                || !$household->is_active || $household->archived_at){
                                throw ValidationException::withMessages(['household_id'=>'Select an active household in the resident’s collection zone.']);
                            }
                        }
                        if($household->resident_user_id && (string)$household->resident_user_id!==$residentId){
                            throw ValidationException::withMessages(['household_reference'=>'This household is already linked to another account. Resolve ownership in the registry first.']);
                        }
                        if(Household::where('resident_user_id',$residentId)->where('is_active',true)
                            ->whereNull('archived_at')->get()->contains(fn($h)=>(string)$h->id!==(string)$household->id)){
                            throw ValidationException::withMessages(['household_reference'=>'The resident already has another active household link. Resolve it before approving.']);
                        }
                        // Conditional update prevents concurrent approval from taking another resident's household.
                        if((string)$household->resident_user_id!==$residentId){
                            $updated=Household::whereKey($household->id)->where('zone_id',(string)$current->zone_id)
                                ->where('is_active',true)->whereNull('archived_at')
                                ->where(function($q){$q->whereNull('resident_user_id')->orWhere('resident_user_id','');})
                                ->update(['resident_user_id'=>$residentId,'linked_by'=>(string)$request->user()->id,'linked_at'=>now()]);
                            if(!$updated){
                                throw ValidationException::withMessages(['household_reference'=>'The household changed while reviewing. Reload and check its owner.']);
                            }
                        }
                        $current->household_id=(string)$household->id;
                    }
                    $current->status=$data['decision']==='approve'?'approved':'rejected';
                    $current->reviewed_by=(string)$request->user()->id;
                    $current->reviewed_at=now();
                    $current->review_note=trim($data['reason']??'')?:null;
                    $current->save();
                    return $current;
                });
        }catch(LockTimeoutException $error){
            throw ValidationException::withMessages(['decision'=>'Another review is in progress. Please retry.']);
        }
        $notices->householdLinkReviewed($entry);
        return redirect()->route('fees.household-links.index')->with('status','Household link '.$entry->status.'. The resident was notified.');
    }
}
