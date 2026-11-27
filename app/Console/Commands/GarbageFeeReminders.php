<?php
namespace App\Console\Commands;

use App\Models\{GarbageFeeCharge,FeePaymentSubmission,Household,Notice,User};
use App\Services\FeeLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GarbageFeeReminders extends Command
{
    protected $signature='fees:send-reminders
        {--dry-run : Calculate which reminders would be issued without saving them}
        {--date= : Optional local date YYYY-MM-DD for deterministic checks}';
    protected $description='Send one-time monthly and overdue garbage-fee reminders only for billed, unpaid households.';

    public function handle():int
    {
        $dateOption=$this->option('date');
        if($dateOption && (!is_string($dateOption) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateOption))){
            $this->error('Invalid date. Use YYYY-MM-DD.');return self::FAILURE;
        }
        try {
            $asOf=$dateOption
                ?Carbon::createFromFormat('!Y-m-d',$dateOption,'Asia/Manila')
                :Carbon::now('Asia/Manila');
            if(!$asOf || ($dateOption && $asOf->format('Y-m-d')!==$dateOption)){
                $this->error('Invalid date. Use YYYY-MM-DD.');return self::FAILURE;
            }
        }catch(\Throwable $e){
            $this->error('Invalid date. Use YYYY-MM-DD.');return self::FAILURE;
        }
        $month=$asOf->format('Y-m');
        $dry=(bool)$this->option('dry-run');
        $day=(int)$asOf->day;
        // Only one current-month reminder per household per stage.
        $stage=$day>=20?'20':($day>=10?'10':null);
        $current=0;$overdue=0;

        // Residents who already uploaded proof must receive a review decision,
        // not repeated unpaid reminders while administrators verify the transfer.
        $pendingIds=FeePaymentSubmission::where('status','pending')
            ->pluck('charge_id')->map(fn($id)=>(string)$id)->unique()->all();

        if($stage){
            $charges=GarbageFeeCharge::with(['household','payments'])
                ->where('billing_month',$month)->get();
            foreach($charges as $charge){
                if(FeeLedger::balance($charge)<=0 || in_array((string)$charge->id,$pendingIds,true))continue;
                $resident=$this->resident($charge);
                if(!$resident)continue;
                $key='fee:current:'.$month.':'.$stage.':'.(string)$charge->id.':'.(string)$resident->id;
                $current+=$this->once(
                    $key,$resident,$charge,
                    'Monthly garbage fee reminder',
                    'Your '.$asOf->format('F Y').' garbage fee for household '.
                    ($charge->household?->reference??'your household').
                    ' has an outstanding balance of '.FeeLedger::money(FeeLedger::balance($charge)).
                    '. View your household payment tracker for details.',
                    $dry
                );
            }
        }

        // Consolidate all earlier unpaid billed months into one notice per
        // household per calendar month, rather than spamming each old charge.
        $prior=GarbageFeeCharge::with(['household','payments'])
            ->where('billing_month','<',$month)->get();
        $byHousehold=[];
        foreach($prior as $charge){
            $balance=FeeLedger::balance($charge);
            if($balance<=0 || in_array((string)$charge->id,$pendingIds,true))continue;
            $resident=$this->resident($charge);
            if(!$resident)continue;
            $key=(string)$charge->household_id.'|'.(string)$resident->id;
            if(!isset($byHousehold[$key])){
                $byHousehold[$key]=['resident'=>$resident,'charge'=>$charge,'balance'=>0,'months'=>[]];
            }
            $byHousehold[$key]['balance']+=$balance;
            $byHousehold[$key]['months'][]=(string)$charge->billing_month;
        }
        foreach($byHousehold as $item){
            $resident=$item['resident'];
            $charge=$item['charge'];
            $key='fee:overdue:'.$month.':'.(string)$charge->household_id.':'.(string)$resident->id;
            $overdue+=$this->once(
                $key,$resident,$charge,
                'Outstanding garbage fee reminder',
                'Household '.($charge->household?->reference??'account').' has '.
                count(array_unique($item['months'])).' earlier billed month(s) with a combined remaining balance of '.
                FeeLedger::money($item['balance']).'. Review the payment history and contact your barangay office if a payment is missing.',
                $dry
            );
        }
        $this->info(($dry?'Potential':'New').' payment reminders: '.($current+$overdue).
            ' (current month: '.$current.', earlier months: '.$overdue.').');
        return self::SUCCESS;
    }

    private function resident(GarbageFeeCharge $charge):?User
    {
        $id=$charge->household?->resident_user_id;
        if(!$id)return null;
        $user=User::find($id);
        return $user && $user->is_active && $user->role_code==='resident' ? $user : null;
    }

    private function once(string $key,User $resident,GarbageFeeCharge $charge,
        string $title,string $message,bool $dry):int
    {
        if(Notice::where('alert_key',$key)->exists())return 0;
        if(!$dry){
            Notice::create([
                'alert_key'=>$key,
                'recipient_user_id'=>(string)$resident->id,
                'fee_charge_id'=>(string)$charge->id,
                'title'=>$title,'message'=>$message,
                'is_read'=>false,'created_at'=>now(),
            ]);
        }
        return 1;
    }
}
