<?php
namespace App\Http\Controllers;

use App\Models\{FeeCity,FeeReceivingAccount,GarbageFeeCharge,Household};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class FeeReceivingAccountController extends Controller
{
    private function manager(Request $request):void
    {
        abort_unless(in_array($request->user()->role_code,['superadmin','admin'],true),403);
    }

    public function index(Request $request)
    {
        $this->manager($request);
        $cities=FeeCity::orderBy('name')->get();
        $accounts=FeeReceivingAccount::with('city')->orderBy('created_at','desc')->get();
        return view('fees.accounts',compact('cities','accounts'));
    }

    private function validateAccount(Request $request):array
    {
        return $request->validate([
            'city_id'=>['required','string'],
            'method'=>['required','in:gcash,bank'],
            'provider'=>['required','string','max:90'],
            'account_name'=>['required','string','max:160'],
            'account_number'=>['required','string','max:60'],
            'instructions'=>['nullable','string','max:500'],
            'qr_code'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'remove_qr'=>['sometimes','boolean'],
            'is_active'=>['sometimes','boolean'],
        ]);
    }

    public function store(Request $request)
    {
        $this->manager($request);
        $data=$this->validateAccount($request);
        FeeCity::findOrFail($data['city_id']);
        $accountNumber=trim($data['account_number']);
        $provider=trim($data['provider']);
        if(FeeReceivingAccount::where('city_id',$data['city_id'])
            ->where('method',$data['method'])->where('provider',$provider)
            ->where('account_number',$accountNumber)->exists()){
            throw ValidationException::withMessages(['account_number'=>'This receiving account is already registered for the selected city.']);
        }

        // Only PNG/JPEG/WebP, no SVG or arbitrary HTML; stored outside public webroot.
        $qrPath=null;
        if($request->hasFile('qr_code')){
            $qrPath=$request->file('qr_code')->store('fee-qr-codes','local');
            if(!$qrPath)throw ValidationException::withMessages(['qr_code'=>'Could not save the QR code securely.']);
        }
        try{
            FeeReceivingAccount::create([
                'city_id'=>$data['city_id'],
                'method'=>$data['method'],
                'provider'=>$provider,
                'account_name'=>trim($data['account_name']),
                'account_number'=>$accountNumber,
                'instructions'=>$data['instructions']??null,
                'qr_path'=>$qrPath,
                'is_active'=>$request->boolean('is_active',true),
                'created_by'=>(string)$request->user()->id,
                'change_history'=>[],
            ]);
        }catch(\Throwable $e){
            if($qrPath)Storage::disk('local')->delete($qrPath);
            throw $e;
        }
        return redirect()->route('fees.accounts.index')->with('status','Receiving account saved. Residents in this city can view its active payment instructions.');
    }

    public function update(Request $request,string $account)
    {
        $this->manager($request);
        $entry=FeeReceivingAccount::findOrFail($account);
        $data=$this->validateAccount($request);
        FeeCity::findOrFail($data['city_id']);
        $number=trim($data['account_number']);
        $provider=trim($data['provider']);
        $duplicate=FeeReceivingAccount::where('city_id',$data['city_id'])
            ->where('method',$data['method'])->where('provider',$provider)
            ->where('account_number',$number)->first();
        if($duplicate && (string)$duplicate->id!==(string)$entry->id){
            throw ValidationException::withMessages(['account_number'=>'This receiving account is already registered for the selected city.']);
        }

        $newQr=null;
        if($request->hasFile('qr_code')){
            $newQr=$request->file('qr_code')->store('fee-qr-codes','local');
            if(!$newQr)throw ValidationException::withMessages(['qr_code'=>'Could not save the QR code securely.']);
        }
        $oldQr=$entry->qr_path;
        try{
            $previous=[
                'city_id'=>$entry->city_id,'method'=>$entry->method,
                'provider'=>$entry->provider,'account_name'=>$entry->account_name,
                'account_number'=>$entry->account_number,
                'instructions'=>$entry->instructions,'is_active'=>$entry->is_active,
                'had_qr'=>(bool)$entry->qr_path,
            ];
            $entry->fill([
                'city_id'=>$data['city_id'],
                'method'=>$data['method'],
                'provider'=>$provider,
                'account_name'=>trim($data['account_name']),
                'account_number'=>$number,
                'instructions'=>$data['instructions']??null,
                'is_active'=>$request->boolean('is_active'),
            ]);
            if($newQr)$entry->qr_path=$newQr;
            elseif($request->boolean('remove_qr'))$entry->qr_path=null;
            if($entry->isDirty()){
                $history=(array)($entry->change_history??[]);
                $history[]=['actor_id'=>(string)$request->user()->id,'at'=>now()->toIso8601String(),'before'=>$previous];
                $entry->change_history=$history;
                $entry->updated_by=(string)$request->user()->id;
                $entry->save();
            }
        }catch(\Throwable $e){
            if($newQr)Storage::disk('local')->delete($newQr);
            throw $e;
        }
        // Old files are retained so account updates do not destroy previous QR evidence.
        return redirect()->route('fees.accounts.index')->with('status','Receiving account updated. Earlier payment records were not changed.');
    }

    public function qr(Request $request,string $account)
    {
        $entry=FeeReceivingAccount::findOrFail($account);
        $role=$request->user()->role_code;
        if(!in_array($role,['superadmin','admin'],true)){
            abort_unless($role==='resident' && $entry->is_active,403);
            $zoneIds=\App\Models\Zone::where('fee_city_id',(string)$entry->city_id)
                ->pluck('id')->map(fn($id)=>(string)$id)->all();
            $householdIds=Household::where('resident_user_id',(string)$request->user()->id)
                ->pluck('id')->map(fn($id)=>(string)$id)->all();
            $currentlyAssigned=Household::where('resident_user_id',(string)$request->user()->id)
                ->whereIn('zone_id',$zoneIds)->where('is_active',true)
                ->whereNull('archived_at')->exists();
            $hasHistoricCharge=$householdIds
                && \App\Models\GarbageFeeCharge::whereIn('household_id',$householdIds)
                    ->where('city_id',(string)$entry->city_id)->exists();
            abort_unless($currentlyAssigned || $hasHistoricCharge,404);
        }
        abort_unless($entry->qr_path && Storage::disk('local')->exists($entry->qr_path),404);
        return response()->file(Storage::disk('local')->path($entry->qr_path),[
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
            'Content-Disposition'=>'inline',
        ]);
    }
}
