<?php
namespace App\Http\Controllers;

use App\Models\{FeePaymentSubmission,FeeReceivingAccount,GarbageFeeCharge,GarbageFeePayment,Household};
use App\Services\{FeeLedger,NoticeService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Cache\LockTimeoutException;

class FeePaymentSubmissionController extends Controller
{
    private function staff(Request $request):void
    {
        abort_unless(in_array($request->user()->role_code,['superadmin','admin'],true),403);
    }

    private function ownedCharge(Request $request,string $id):GarbageFeeCharge
    {
        abort_unless($request->user()->role_code==='resident',403);
        $charge=GarbageFeeCharge::with(['household','payments'])->findOrFail($id);
        $household=$charge->household;
        abort_unless($household && (string)$household->resident_user_id===(string)$request->user()->id
            && $household->is_active && !$household->archived_at,404);
        return $charge;
    }

    /**
     * Resident submits an *external* bank/wallet transfer for staff verification.
     * Submission is NOT a confirmed payment and MUST NOT change the ledger balance.
     */
    public function store(Request $request,string $charge,NoticeService $notices)
    {
        $bill=$this->ownedCharge($request,$charge);
        $data=$request->validate([
            'receiving_account_id'=>['required','string'],
            'amount'=>['required','regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'paid_on'=>['required','date','before_or_equal:today'],
            'transaction_reference'=>['required','string','min:5','max:100'],
            'receipt'=>['required','file','mimes:jpg,jpeg,png,webp,pdf','max:5120'],
            'note'=>['nullable','string','max:400'],
        ]);
        $receiving=FeeReceivingAccount::findOrFail($data['receiving_account_id']);
        if(!$receiving->is_active || (string)$receiving->city_id!==(string)$bill->city_id){
            throw ValidationException::withMessages(['receiving_account_id'=>'Select an active receiving account for this billing city.']);
        }
        $amount=FeeLedger::centavos($data['amount']);
        $reference=mb_strtoupper(trim($data['transaction_reference']));
        if($amount<1)throw ValidationException::withMessages(['amount'=>'Enter an amount greater than zero.']);

        try {
            $submission=Cache::lock('fee-transaction:'.$bill->id,15)->block(5,
                function()use($request,$bill,$data,$receiving,$amount,$reference){
                    $current=GarbageFeeCharge::with('payments')->findOrFail($bill->id);
                    if($amount>FeeLedger::balance($current)){
                        throw ValidationException::withMessages(['amount'=>'The amount exceeds this household month’s unpaid balance. Refresh the bill and try again.']);
                    }
                    if(FeePaymentSubmission::where('charge_id',(string)$bill->id)->where('status','pending')->exists()){
                        throw ValidationException::withMessages(['receipt'=>'An online payment submission is already waiting for verification for this month.']);
                    }
                    if(FeePaymentSubmission::where('city_id',(string)$bill->city_id)
                        ->where('transaction_reference',$reference)
                        ->where('status','!=','rejected')->exists()
                        ||GarbageFeePayment::where('city_id',(string)$bill->city_id)
                            ->where('external_reference',$reference)->where('status','recorded')->exists()){
                        throw ValidationException::withMessages(['transaction_reference'=>'This transaction reference has already been submitted.']);
                    }

                    $file=$request->file('receipt');
                    $path=$file->store('fee-payment-proofs','local');
                    if(!$path)throw ValidationException::withMessages(['receipt'=>'Could not store the receipt. Please retry.']);
                    try{
                        return FeePaymentSubmission::create([
                            'submission_code'=>'PAY-'.Str::upper(Str::random(14)),
                            'charge_id'=>(string)$bill->id,
                            'city_id'=>(string)$bill->city_id,
                            'household_id'=>(string)$bill->household_id,
                            'resident_user_id'=>(string)$request->user()->id,
                            'receiving_account_id'=>(string)$receiving->id,
                            'provider'=>(string)$receiving->provider,
                            'method'=>in_array($receiving->method,['gcash','wallet'],true)
                                ?(strcasecmp((string)$receiving->provider,'GCash')===0?'gcash':'wallet')
                                :'bank',
                            'account_name'=>(string)$receiving->account_name,
                            'amount_centavos'=>$amount,
                            'paid_on'=>$data['paid_on'],
                            'transaction_reference'=>$reference,
                            'note'=>$data['note']??null,
                            'receipt_path'=>$path,
                            'receipt_name'=>mb_substr((string)$file->getClientOriginalName(),0,150),
                            'receipt_mime'=>(string)$file->getMimeType(),
                            'receipt_size'=>(int)$file->getSize(),
                            'status'=>'pending',
                            'submitted_at'=>now(),
                        ]);
                    }catch(\Throwable $error){
                        Storage::disk('local')->delete($path);
                        throw $error;
                    }
                }
            );
        }catch(LockTimeoutException $error){
            throw ValidationException::withMessages(['receipt'=>'Another payment update is being processed. Please retry.']);
        }

        $notices->feeSubmissionReceived($submission);
        return redirect()->route('fees.index',[
            'month'=>$bill->billing_month,'highlight'=>(string)$bill->id,
        ])->with('status','Receipt submitted for verification ('.$submission->submission_code.'). Your balance remains unpaid until staff confirm the transfer.');
    }

    public function index(Request $request)
    {
        $this->staff($request);
        $status=$request->query('status','pending');
        abort_unless(is_string($status) && in_array($status,['pending','verified','rejected','all'],true),422);
        $query=FeePaymentSubmission::with(['charge','household','resident','receivingAccount'])
            ->orderBy('created_at','desc');
        if($status!=='all')$query->where('status',$status);
        $submissions=$query->paginate(20)->withQueryString();
        $pendingCount=FeePaymentSubmission::where('status','pending')->count();
        return view('fees.submissions',compact('submissions','status','pendingCount'));
    }

    /** Private proof — only the original resident or superadmin / barangay admin. */
    public function receipt(Request $request,string $submission)
    {
        $entry=FeePaymentSubmission::findOrFail($submission);
        $role=$request->user()->role_code;
        abort_unless(in_array($role,['superadmin','admin'],true)
            ||($role==='resident' && (string)$entry->resident_user_id===(string)$request->user()->id),404);
        $path=(string)$entry->receipt_path;
        abort_unless($path && Storage::disk('local')->exists($path),404);
        $mime=(string)$entry->receipt_mime;
        $isImage=in_array($mime,['image/jpeg','image/png','image/webp'],true);
        $extension=$mime==='application/pdf'?'pdf':($mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg'));
        return response()->file(Storage::disk('local')->path($path),[
            'Content-Type'=>$mime,
            'Content-Disposition'=>($isImage?'inline':'attachment').'; filename="payment-proof-'.$entry->submission_code.'.'.$extension.'"',
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    public function review(Request $request,string $submission,NoticeService $notices)
    {
        $this->staff($request);
        $data=$request->validate([
            'decision'=>['required','in:verify,reject'],
            'review_note'=>['nullable','string','max:500'],
        ]);
        if($data['decision']==='reject' && mb_strlen(trim($data['review_note']??''))<10){
            throw ValidationException::withMessages(['review_note'=>'Explain why the receipt was rejected (at least 10 characters).']);
        }
        $entry=FeePaymentSubmission::findOrFail($submission);
        try{
            $result=Cache::lock('fee-transaction:'.$entry->charge_id,15)->block(5,function()use($entry,$request,$data){
                $current=FeePaymentSubmission::findOrFail($entry->id);
                if($current->status!=='pending'){
                    throw ValidationException::withMessages(['decision'=>'This receipt has already been reviewed. Refresh the queue.']);
                }
                $bill=GarbageFeeCharge::with(['payments','household'])->findOrFail($current->charge_id);
                $record=null;
                if($data['decision']==='verify'){
                    $amount=(int)$current->amount_centavos;
                    if($amount<1 || $amount>FeeLedger::balance($bill)){
                        throw ValidationException::withMessages(['decision'=>'This month’s balance changed. The receipt cannot be confirmed at the submitted amount. Reconcile the payment before continuing.']);
                    }
                    if(GarbageFeePayment::where('source_submission_id',(string)$current->id)->exists()
                        ||GarbageFeePayment::where('city_id',(string)$bill->city_id)
                            ->where('external_reference',(string)$current->transaction_reference)
                            ->where('status','recorded')->exists()){
                        throw ValidationException::withMessages(['decision'=>'This transfer has already been recorded. Review the payment ledger.']);
                    }
                    $record=GarbageFeePayment::create([
                        'charge_id'=>(string)$bill->id,
                        'city_id'=>(string)$bill->city_id,
                        'amount_centavos'=>$amount,
                        'method'=>(string)$current->method,
                        'paid_on'=>$current->paid_on?->format('Y-m-d')??$current->getRawOriginal('paid_on'),
                        'external_reference'=>(string)$current->transaction_reference,
                        'source_submission_id'=>(string)$current->id,
                        'note'=>'Resident-submitted transfer verified by authorized staff',
                        'status'=>'recorded',
                        'receipt_code'=>'FEE-'.Str::upper(Str::random(14)),
                        'recorded_by'=>(string)$request->user()->id,
                        'recorded_at'=>now(),
                    ]);
                }

                $current->status=$data['decision']==='verify'?'verified':'rejected';
                $current->review_note=trim($data['review_note']??'')?:null;
                $current->reviewed_by=(string)$request->user()->id;
                $current->reviewed_at=now();
                if($record)$current->payment_id=(string)$record->id;
                $current->save();
                $bill->unsetRelation('payments');
                return [$current,$bill,FeeLedger::balance($bill)];
            });
        }catch(LockTimeoutException $error){
            throw ValidationException::withMessages(['decision'=>'Another payment update is being processed. Please retry.']);
        }

        [$entry,$bill,$balance]=$result;
        $notices->feeSubmissionReviewed($entry,$balance);
        if($entry->status==='verified'){
            $notices->feePaymentRecorded($bill,(int)$entry->amount_centavos,(string)$entry->method,
                FeeLedger::status($bill),(string)$request->user()->id);
        }
        Log::notice('Resident payment receipt reviewed',[
            'submission_id'=>(string)$entry->id,
            'decision'=>(string)$entry->status,
            'reviewer_id'=>(string)$request->user()->id,
        ]);
        return redirect()->route('fees.submissions.index',['status'=>'pending'])
            ->with('status',$entry->submission_code.' '.($entry->status==='verified'?'verified and credited.':'rejected. The resident can upload a corrected receipt.'));
    }
}
