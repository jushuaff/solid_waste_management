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
        return view('fees.household-links',compact('requests','staff','status','hasLink','hasPending'));
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
            'household_reference'=>['required_if:decision,approve','nullable','string','max:100'],
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
                        $matches=Household::where('zone_id',(string)$current->zone_id)
                            ->where('reference',trim($data['household_reference']))
                            ->where('is_active',true)->whereNull('archived_at')->get();
                        if($matches->count()!==1){
                            throw ValidationException::withMessages(['household_reference'=>'Select an existing active, unique household reference in this zone. Create the household in the registry first if needed.']);
                        }
                        $household=$matches->first();
                        $residentId=(string)$resident->id;
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
                                ->update(['resident_user_id'=>$residentId]);
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
