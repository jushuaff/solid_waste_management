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
    public function store(Request $request,NoticeService $notices)
    {
        $chargeData=$request->validate(['charge_id'=>['required','string']]);
        $bill=$this->ownedCharge($request,$chargeData['charge_id']);
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
        if(mb_strlen($reference)<5)throw ValidationException::withMessages(['transaction_reference'=>'Enter the actual transfer reference.']);
        if($amount<1)throw ValidationException::withMessages(['amount'=>'Enter an amount greater than zero.']);

        try {
            $submission=Cache::lock('fee-transaction:'.$bill->id,15)->block(5,
                function()use($request,$bill,$data,$receiving,$amount,$reference){
                    $current=GarbageFeeCharge::with('payments')->findOrFail($bill->id);
                    if($amount>FeeLedger::availableForSubmission($current)){
                        throw ValidationException::withMessages(['amount'=>'The amount exceeds the remaining balance available for new submissions after pending receipts.']);
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

    public function update(Request $request,string $submission,NoticeService $notices)
    {
        $entry=FeePaymentSubmission::findOrFail($submission);
        abort_unless($request->user()->role_code==='resident'
            && (string)$entry->resident_user_id===(string)$request->user()->id,404);
        $bill=$this->ownedCharge($request,(string)$entry->charge_id);
        $data=$request->validate([
            'amount'=>['required','regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'paid_on'=>['required','date','before_or_equal:today'],
            'transaction_reference'=>['required','string','min:5','max:100'],
            'receipt'=>['nullable','file','mimes:jpg,jpeg,png,webp,pdf','max:5120'],
            'note'=>['nullable','string','max:400'],
        ]);
        $amount=FeeLedger::centavos($data['amount']);
        $reference=mb_strtoupper(trim($data['transaction_reference']));
        if($amount<1 || mb_strlen($reference)<5){
            throw ValidationException::withMessages(['transaction_reference'=>'Enter a positive amount and valid transfer reference.']);
        }
        try{
            Cache::lock('fee-transaction:'.$bill->id,15)->block(5,function()use($request,$entry,$bill,$data,$amount,$reference){
                $current=FeePaymentSubmission::findOrFail($entry->id);
                if($current->status!=='pending' || GarbageFeePayment::where('source_submission_id',(string)$current->id)->exists()){
                    throw ValidationException::withMessages(['receipt'=>'This submission has already been reviewed or credited and cannot be edited.']);
                }
                $charge=GarbageFeeCharge::with('payments')->findOrFail($bill->id);
                if($amount>FeeLedger::availableForSubmission($charge,(string)$current->id)){
                    throw ValidationException::withMessages(['amount'=>'The amount exceeds the available balance after other pending receipts.']);
                }
                if(FeePaymentSubmission::where('city_id',(string)$charge->city_id)
                    ->where('transaction_reference',$reference)->where('status','!=','rejected')->get()
                    ->contains(fn($proof)=>(string)$proof->id!==(string)$current->id)
                    || GarbageFeePayment::where('city_id',(string)$charge->city_id)
                        ->where('external_reference',$reference)->where('status','recorded')->exists()){
                    throw ValidationException::withMessages(['transaction_reference'=>'This transaction reference has already been submitted.']);
                }
                $oldPath=(string)$current->receipt_path;
                $newPath=null;
                if($request->hasFile('receipt')){
                    $file=$request->file('receipt');
                    $newPath=$file->store('fee-payment-proofs','local');
                    if(!$newPath)throw ValidationException::withMessages(['receipt'=>'Could not store the replacement receipt.']);
                    $current->receipt_path=$newPath;
                    $current->receipt_name=mb_substr((string)$file->getClientOriginalName(),0,150);
                    $current->receipt_mime=(string)$file->getMimeType();
                    $current->receipt_size=(int)$file->getSize();
                }
                $current->amount_centavos=$amount;
                $current->paid_on=$data['paid_on'];
                $current->transaction_reference=$reference;
                $current->note=$data['note']??null;
                try{
                    $current->save();
                }catch(\Throwable $error){
                    if($newPath)Storage::disk('local')->delete($newPath);
                    throw $error;
                }
                if($newPath && $oldPath)Storage::disk('local')->delete($oldPath);
            });
        }catch(LockTimeoutException $error){
            throw ValidationException::withMessages(['receipt'=>'Another payment update is being processed. Please retry.']);
        }
        $notices->feeSubmissionReceived(FeePaymentSubmission::findOrFail($entry->id));
        return redirect()->route('fees.index',['month'=>$bill->billing_month])
            ->with('status','Receipt updated. It remains pending staff verification.');
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
        abort_if($entry->status==='verified' && !$path,410,'The verified proof was removed; the transaction reference is retained.');
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

    private function purgeVerifiedReceipt(FeePaymentSubmission $entry):void
    {
        $path=(string)$entry->receipt_path;
        if($entry->status!=='verified' || !$path)return;
        try{
            if(Storage::disk('local')->exists($path) && !Storage::disk('local')->delete($path)){
                throw new \RuntimeException('Receipt deletion failed.');
            }
            $entry->receipt_path=null;
            $entry->receipt_name=null;
            $entry->receipt_mime=null;
            $entry->receipt_size=null;
            $entry->receipt_deleted_at=now();
            $entry->save();
        }catch(\Throwable $error){
            Log::warning('Verified payment proof cleanup requires retry',[
                'submission_id'=>(string)$entry->id,'error'=>$error->getMessage(),
            ]);
        }
    }

    public function review(Request $request,string $submission,NoticeService $notices)
    {
        $this->staff($request);
        $data=$request->validate([
            'decision'=>['required','in:verify,reject'],
            'review_note'=>['nullable','string','max:500'],
            'confirmed_reference'=>['required_if:decision,verify','nullable','string','min:5','max:100'],
        ]);
        if($data['decision']==='reject' && mb_strlen(trim($data['review_note']??''))<10){
            throw ValidationException::withMessages(['review_note'=>'Explain why the receipt was rejected (at least 10 characters).']);
        }
        if($data['decision']==='verify' && !$request->boolean('funds_confirmed')){
            throw ValidationException::withMessages(['funds_confirmed'=>'Confirm that the transferred funds reached the authorized receiving account before crediting the household.']);
        }
        $entry=FeePaymentSubmission::findOrFail($submission);
        $reference=mb_strtoupper(trim($data['confirmed_reference']??''));
        if($data['decision']==='verify' && mb_strlen($reference)<5){
            throw ValidationException::withMessages(['confirmed_reference'=>'Enter the reference visible on the receipt and confirmed against the transaction record.']);
        }
        try{
            $result=Cache::lock('fee-transaction:'.$entry->charge_id,15)->block(5,function()use($entry,$request,$data,$reference){
                $current=FeePaymentSubmission::findOrFail($entry->id);
                if($current->status!=='pending'){
                    throw ValidationException::withMessages(['decision'=>'This receipt has already been reviewed. Refresh the queue.']);
                }
                $bill=GarbageFeeCharge::with(['payments','household'])->findOrFail($current->charge_id);
                $record=null;
                if($data['decision']==='reject' && GarbageFeePayment::where('source_submission_id',(string)$current->id)->exists()){
                    throw ValidationException::withMessages(['decision'=>'A linked payment already exists. Reconcile the ledger before rejecting this submission.']);
                }
                if($data['decision']==='verify'){
                    if(!$current->receipt_path || !Storage::disk('local')->exists((string)$current->receipt_path)){
                        throw ValidationException::withMessages(['decision'=>'The uploaded receipt is unavailable. Do not verify until proof is available.']);
                    }
                    if(FeePaymentSubmission::where('city_id',(string)$bill->city_id)->where('transaction_reference',$reference)
                        ->where('status','!=','rejected')->get()->contains(fn($proof)=>(string)$proof->id!==(string)$current->id)){
                        throw ValidationException::withMessages(['confirmed_reference'=>'This reference belongs to another active submission.']);
                    }
                    $amount=(int)$current->amount_centavos;
                    // A saved payment can outlive a failed status-write. Resume the
                    // original confirmation rather than crediting a second payment.
                    $record=GarbageFeePayment::where('source_submission_id',(string)$current->id)->first();
                    if(!$record){
                        if($amount<1 || $amount>FeeLedger::balance($bill)){
                            throw ValidationException::withMessages(['decision'=>'This month’s balance changed. The receipt cannot be confirmed at the submitted amount. Reconcile the payment before continuing.']);
                        }
                        if(GarbageFeePayment::where('city_id',(string)$bill->city_id)
                            ->where('external_reference',$reference)
                            ->where('status','recorded')->exists()){
                            throw ValidationException::withMessages(['decision'=>'This transfer has already been recorded. Review the payment ledger.']);
                        }
                        $record=GarbageFeePayment::create([
                            'charge_id'=>(string)$bill->id,
                            'city_id'=>(string)$bill->city_id,
                            'amount_centavos'=>$amount,
                            'method'=>(string)$current->method,
                            'paid_on'=>$current->paid_on?->format('Y-m-d')??$current->getRawOriginal('paid_on'),
                            'external_reference'=>$reference,
                            'source_submission_id'=>(string)$current->id,
                            'note'=>'Resident-submitted transfer verified by authorized staff',
                            'status'=>'recorded',
                            'receipt_code'=>'FEE-'.Str::upper(Str::random(14)),
                            'recorded_by'=>(string)$request->user()->id,
                            'recorded_at'=>now(),
                        ]);
                    }elseif($record->status!=='recorded' || (string)$record->external_reference!==$reference){
                        throw ValidationException::withMessages(['decision'=>'The linked payment was reversed or has a different reference. Reconcile the entry before changing this submission.']);
                    }
                }

                $current->status=$data['decision']==='verify'?'verified':'rejected';
                $current->review_note=trim($data['review_note']??'')?:null;
                $current->reviewed_by=(string)$request->user()->id;
                $current->reviewed_at=now();
                if($record){
                    $current->payment_id=(string)$record->id;
                    $current->submitted_reference=$current->transaction_reference;
                    $current->transaction_reference=$reference;
                }
                $current->save();
                // Only purge evidence after both the confirmed ledger and review are saved.
                if($record)$this->purgeVerifiedReceipt($current);
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
