<?php
namespace App\Http\Controllers;

use App\Models\{
    FeeCity, FeeRate, FeeReceivingAccount, FeePaymentSubmission, Zone, Household, GarbageFeeCharge, GarbageFeePayment, Notice, User
};
use App\Services\{FeeLedger,NoticeService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;

class GarbageFeeController extends Controller
{
    private function role(Request $request):string
    {
        $role=$request->user()->role_code;
        abort_unless(in_array($role,['superadmin','admin','coordinator','resident'],true),403);
        return $role;
    }

    private function ensureChargeAccess(Request $request,GarbageFeeCharge $charge,bool $write=false):void
    {
        $role=$this->role($request);
        if($write)abort_unless(in_array($role,['superadmin','admin','coordinator'],true),403);
        if(in_array($role,['superadmin','admin'],true))return;
        if($role==='coordinator'){
            abort_unless($request->user()->zone_id &&
                (string)$charge->zone_id===(string)$request->user()->zone_id,404);
            return;
        }
        $household=Household::find($charge->household_id);
        abort_unless($household && (string)$household->resident_user_id===(string)$request->user()->id,404);
    }

    private function month(Request $request):string
    {
        $value=$request->query('month',now()->format('Y-m'));
        abort_unless(is_string($value) &&
            preg_match('/^(19|20|21)\d\d-(0[1-9]|1[0-2])$/',$value),422,'Choose a valid reporting month.');
        return $value;
    }

    public function index(Request $request)
    {
        $role=$this->role($request);
        $month=$this->month($request);
        $status=$request->query('status','');
        abort_unless(is_string($status) && in_array($status,['','Unpaid','Partial','Paid'],true),422);

        $query=GarbageFeeCharge::with(['household','zone','city','payments'])
            ->where('billing_month',$month);
        if($role==='resident'){
            $ids=Household::where('resident_user_id',(string)$request->user()->id)
                ->pluck('id')->map(fn($id)=>(string)$id)->all();
            $query->whereIn('household_id',$ids);
        }
        if($role==='coordinator'){
            abort_unless($request->user()->zone_id,403,'Your account requires an assigned collection zone.');
            $query->where('zone_id',(string)$request->user()->zone_id);
        }
        if(in_array($role,['superadmin','admin','coordinator'],true)){
            foreach(['city_id','zone_id'] as $key){
                $filter=$request->query($key);
                if($filter!==null && $filter!==''){
                    abort_unless(is_string($filter) && strlen($filter)<=64,422);
                    $query->where($key,$filter);
                }
            }
        }

        // Ledger totals are derived from non-voided entries; never rely on a mutable paid flag.
        $rows=$query->get()->map(function($charge){
            $paid=FeeLedger::paid($charge);
            return [
                'charge'=>$charge,
                'paid'=>$paid,
                'balance'=>max(0,(int)$charge->amount_centavos-$paid),
                'status'=>FeeLedger::status($charge),
            ];
        })->filter(fn($row)=>!$status || $row['status']===$status)->all();

        // Sorting applies to the entire filtered month before pagination.
        $allowed=['household','city','zone','due','paid','balance','status'];
        $sort=$request->query('sort','household');
        if(!is_string($sort) || !in_array($sort,$allowed,true))$sort='household';
        $direction=$request->query('direction','asc');
        if(!is_string($direction) || !in_array($direction,['asc','desc'],true))$direction='asc';
        usort($rows,function($a,$b)use($sort,$direction){
            $value=static function($row)use($sort){
                $bill=$row['charge'];
                return match($sort){
                    'household'=>$bill->household?->reference??'',
                    'city'=>$bill->city?->name??'',
                    'zone'=>$bill->zone?->name??'',
                    'due'=>(int)$bill->amount_centavos,
                    'paid'=>$row['paid'],
                    'balance'=>$row['balance'],
                    'status'=>$row['status'],
                };
            };
            $left=$value($a);$right=$value($b);
            $cmp=is_numeric($left)&&is_numeric($right)?$left<=>$right:strnatcasecmp((string)$left,(string)$right);
            if(!$cmp)$cmp=strcmp((string)$a['charge']->id,(string)$b['charge']->id);
            return $direction==='desc'?-$cmp:$cmp;
        });
        $totalDue=array_sum(array_map(fn($row)=>(int)$row['charge']->amount_centavos,$rows));
        $totalPaid=array_sum(array_column($rows,'paid'));
        $page=max(1,(int)LengthAwarePaginator::resolveCurrentPage());
        $charges=new LengthAwarePaginator(
            array_slice($rows,($page-1)*20,20),
            count($rows),20,$page,['path'=>$request->url(),'query'=>$request->query()]
        );
        $cities=in_array($role,['superadmin','admin','coordinator'],true)
            ?FeeCity::orderBy('name')->get():collect();
        $zones=in_array($role,['superadmin','admin','coordinator'],true)
            ?Zone::orderBy('name')->get():collect();
        if($role==='coordinator'){
            $zones=$zones->filter(fn($z)=>(string)$z->id===(string)$request->user()->zone_id);
        }
        $receivingAccounts=collect();
        $residentOutstanding=collect();
        $residentSubmissions=collect();
        $residentHouseholdCount=0;
        $pendingVerificationCount=0;
        if(in_array($role,['superadmin','admin'],true)){
            $pendingVerificationCount=FeePaymentSubmission::where('status','pending')->count();
        }
        if($role==='resident'){
            $householdIds=Household::where('resident_user_id',(string)$request->user()->id)
                ->pluck('id')->map(fn($id)=>(string)$id)->all();
            $zoneIds=Household::where('resident_user_id',(string)$request->user()->id)
                ->pluck('zone_id')->map(fn($id)=>(string)$id)->all();
            $cityIds=Zone::findMany($zoneIds)->pluck('fee_city_id')
                ->filter()->map(fn($id)=>(string)$id)->all();
            $pastCityIds=GarbageFeeCharge::whereIn('household_id',$householdIds)
                ->pluck('city_id')->filter()->map(fn($id)=>(string)$id)->all();
            $cityIds=array_values(array_unique(array_merge($cityIds,$pastCityIds)));
            if($cityIds){
                $receivingAccounts=FeeReceivingAccount::with('city')
                    ->where('is_active',true)->whereIn('city_id',$cityIds)
                    ->orderBy('provider')->get();
            }
            $residentHouseholdCount=Household::where('resident_user_id',(string)$request->user()->id)
                ->where('is_active',true)->whereNull('archived_at')->count();
            $residentSubmissions=FeePaymentSubmission::with(['charge','receivingAccount'])
                ->where('resident_user_id',(string)$request->user()->id)
                ->orderBy('created_at','desc')->limit(20)->get();
            $pendingIds=$residentSubmissions->where('status','pending')
                ->pluck('charge_id')->map(fn($id)=>(string)$id)->all();
            $activeHouseholds=Household::where('resident_user_id',(string)$request->user()->id)
                ->where('is_active',true)->whereNull('archived_at')->pluck('id')
                ->map(fn($id)=>(string)$id)->all();
            if($activeHouseholds){
                $residentOutstanding=GarbageFeeCharge::with(['household','payments','city'])
                    ->whereIn('household_id',$activeHouseholds)->get()
                    ->filter(fn($bill)=>FeeLedger::balance($bill)>0
                        && !in_array((string)$bill->id,$pendingIds,true)
                        && $receivingAccounts->contains(fn($account)=>(string)$account->city_id===(string)$bill->city_id))
                    ->sortByDesc('billing_month')->values();
            }
        }
        return view('fees.index',compact('charges','month','role','cities','zones','status','totalDue','totalPaid','receivingAccounts',
            'residentOutstanding','residentSubmissions','residentHouseholdCount','pendingVerificationCount'));
    }

    /** Create one immutable charge per active household and month. Re-running skips existing charges. */
    public function generate(Request $request)
    {
        abort_unless(in_array($request->user()->role_code,['superadmin','admin'],true),403);
        $data=$request->validate([
            'city_id'=>['required','string'],
            'month'=>['required','date_format:Y-m'],
        ]);
        $city=FeeCity::findOrFail($data['city_id']);
        $month=$data['month'];
        $rate=FeeRate::where('city_id',(string)$city->id)
            ->where('effective_month','<=',$month)
            ->orderBy('effective_month','desc')->first();
        if(!$rate){
            throw ValidationException::withMessages(['month'=>'No approved city rate is configured for this month. Set an effective rate first.']);
        }
        $zoneIds=Zone::where('fee_city_id',(string)$city->id)
            ->where('is_active',true)->pluck('id')->map(fn($id)=>(string)$id)->all();
        if(!$zoneIds){
            throw ValidationException::withMessages(['city_id'=>'Assign collection zones to this city before generating monthly charges.']);
        }
        $households=Household::whereIn('zone_id',$zoneIds)->where('is_active',true)
            ->whereNull('archived_at')->get();
        $created=0;
        foreach($households as $household){
            $charge=GarbageFeeCharge::firstOrCreate(
                ['household_id'=>(string)$household->id,'billing_month'=>$month],
                [
                    'city_id'=>(string)$city->id,
                    'zone_id'=>(string)$household->zone_id,
                    'rate_id'=>(string)$rate->id,
                    'amount_centavos'=>(int)$rate->amount_centavos,
                    'created_by'=>(string)$request->user()->id,
                ]
            );
            if($charge->wasRecentlyCreated){
                $created++;
                $this->notifyResident($charge,'Garbage fee for '.$month,
                    'A monthly household garbage-fee charge has been recorded. Check your balance in Garbage Fees.');
            }
        }
        return redirect()->route('fees.index',['month'=>$month,'city_id'=>(string)$city->id])
            ->with('status',$created.' monthly household charge(s) created. Existing charges and payment histories were preserved.');
    }

    /** Cash is recorded by authorized administrators; residents only submit online proof. */
    public function recordPayment(Request $request,string $charge,NoticeService $notices)
    {
        abort_unless(in_array($request->user()->role_code,['superadmin','admin'],true),403);
        $bill=GarbageFeeCharge::with(['household','payments'])->findOrFail($charge);
        $data=$request->validate([
            'method'=>['required','in:cash'],
            'amount'=>['required','regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'paid_on'=>['required','date','before_or_equal:today'],
            'official_receipt_number'=>['nullable','string','max:100'],
            'note'=>['nullable','string','max:500'],
            'acknowledged'=>['accepted'],
        ]);
        $amount=FeeLedger::centavos($data['amount']);
        if($amount<1)throw ValidationException::withMessages(['amount'=>'Cash payment must be greater than zero.']);
        try{
            $payment=Cache::lock('fee-transaction:'.$bill->id,15)->block(5,function()use($request,$bill,$data,$amount){
                $current=GarbageFeeCharge::with('payments')->findOrFail($bill->id);
                if($amount>FeeLedger::balance($current)){
                    throw ValidationException::withMessages(['amount'=>'Cash amount exceeds the remaining bill balance. Refresh this household month.']);
                }
                return GarbageFeePayment::create([
                    'charge_id'=>(string)$bill->id,
                    'city_id'=>(string)$bill->city_id,
                    'amount_centavos'=>$amount,
                    'method'=>'cash',
                    'paid_on'=>$data['paid_on'],
                    'external_reference'=>null,
                    'official_receipt_number'=>trim($data['official_receipt_number']??'')?:null,
                    'note'=>$data['note']??null,
                    'status'=>'recorded',
                    'receipt_code'=>'FEE-'.Str::upper(Str::random(14)),
                    'recorded_by'=>(string)$request->user()->id,
                    'recorded_at'=>now(),
                ]);
            });
        }catch(LockTimeoutException $e){
            throw ValidationException::withMessages(['amount'=>'Another payment is being processed. Please retry.']);
        }
        Log::notice('Cash garbage fee payment recorded',[
            'payment_id'=>(string)$payment->id,
            'charge_id'=>(string)$bill->id,
            'actor_user_id'=>(string)$request->user()->id,
        ]);
        $bill->unsetRelation('payments');
        $remaining=FeeLedger::balance($bill);
        $status=FeeLedger::status($bill);
        $this->notifyResident($bill,$remaining===0?'Garbage fee fully paid':'Cash payment confirmed',
            'An authorized collector recorded a cash payment of '.FeeLedger::money($amount).
            ' for '.$bill->billing_month.'. Status: '.$status.
            '. Remaining balance: '.FeeLedger::money($remaining).'.');
        $notices->feePaymentRecorded($bill,$amount,'cash',$status,(string)$request->user()->id);
        return redirect()->route('fees.index',['month'=>$bill->billing_month,'highlight'=>(string)$bill->id])
            ->with('status','Cash payment recorded. Internal reference: '.$payment->receipt_code.'. An official receipt must be handled through the authorized office.');
    }

    /** Preserve the entry as an audited void, rather than deleting financial history. */
    public function voidPayment(Request $request,string $payment)
    {
        abort_unless($request->user()->role_code==='superadmin',403);
        $entry=GarbageFeePayment::findOrFail($payment);
        $bill=GarbageFeeCharge::findOrFail($entry->charge_id);
        abort_unless($entry->status==='recorded',409,'This payment has already been voided.');

        $data=$request->validate([
            'reason'=>['required','string','min:10','max:500'],
        ]);
        $entry->status='void';
        $entry->void_reason=$data['reason'];
        $entry->voided_by=(string)$request->user()->id;
        $entry->voided_at=now();
        $entry->save();
        Log::warning('Garbage fee payment voided',[
            'payment_id'=>(string)$entry->id,
            'charge_id'=>(string)$bill->id,
            'actor_user_id'=>(string)$request->user()->id,
        ]);
        $this->notifyResident($bill,'Garbage fee payment corrected',
            'A previous payment entry for '.$bill->billing_month.' was reversed. Please review your updated balance.');
        return redirect()->route('fees.index',['month'=>$bill->billing_month,'highlight'=>(string)$bill->id])
            ->with('status','Payment reversed with audit history retained.');
    }

    private function notifyResident(GarbageFeeCharge $bill,string $title,string $message):void
    {
        $residentId=$bill->household?->resident_user_id;
        if(!$residentId)return;
        $resident=User::find($residentId);
        if(!$resident || !$resident->is_active || $resident->role_code!=='resident')return;
        Notice::create([
            'recipient_user_id'=>(string)$resident->id,
            'fee_charge_id'=>(string)$bill->id,
            'title'=>$title,'message'=>$message,'is_read'=>false,
            'created_at'=>now(),
        ]);
    }
}
