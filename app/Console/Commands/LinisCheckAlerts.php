<?php
namespace App\Console\Commands;

use App\Models\{Notice, PickupInspection, Report, Role, User, Zone};
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class LinisCheckAlerts extends Command
{
    protected $signature='linis:check-alerts {--dry-run : Calculate alerts without storing notifications}';
    protected $description='Issue online LINIS escalation notices for unresolved reports and consistently low segregation compliance.';

    public function handle(): int
    {
        $dry=$this->option('dry-run');
        $created=0;
        $cutoff=now()->subHours(48);
        $open=['Submitted','Under Review','Verified','Assigned','In Progress','Exception'];
        $unresolved=Report::whereIn('current_status',$open)->whereNull('archived_at')
            ->where('submitted_at','<=',$cutoff)->limit(2000)->get();

        foreach($unresolved as $report){
            $key='linis:overdue:'.(string)$report->id;
            $staff=$this->staffForZone((string)$report->zone_id);
            foreach($staff as $recipient){
                $created+=$this->noticeOnce($key.':'.(string)$recipient->id,$recipient,
                    'Overdue missed-collection report',
                    'Report '.$report->tracking_id.' remains unresolved more than 48 hours after submission.',
                    (string)$report->id,null,$dry);
            }
        }

        // Evaluate the two *completed* Monday-Sunday weeks, not the still-open current week.
        $thisWeek=now()->startOfWeek(Carbon::MONDAY);
        $w2start=$thisWeek->copy()->subWeek();
        $w1start=$thisWeek->copy()->subWeeks(2);
        foreach(Zone::where('is_active',true)->get() as $zone){
            $id=(string)$zone->id;
            $rates=[];
            foreach([$w1start,$w2start] as $from){
                $until=$from->copy()->addWeek();
                $logs=PickupInspection::where('zone_id',$id)->whereNull('archived_at')
                    ->where('inspected_at','>=',$from)->where('inspected_at','<',$until)->get();
                $total=$logs->count();
                $passed=$logs->filter(fn($entry)=>(bool)$entry->segregated)->count();
                $rates[]=$total>0?100*$passed/$total:null;
            }
            if($rates[0]===null || $rates[1]===null || $rates[0]>=60 || $rates[1]>=60) continue;
            $key='linis:red-two-weeks:'.$id.':'.$w2start->toDateString();
            foreach($this->staffForZone($id) as $recipient){
                $created+=$this->noticeOnce($key.':'.(string)$recipient->id,$recipient,
                    'Segregation alert: two consecutive low-compliance weeks',
                    $zone->name.' recorded below 60% segregation compliance in both previous completed weeks. Review the underlying inspections.',
                    null,$id,$dry);
            }
        }
        $this->info(($dry?'Potential':'New').' notifications: '.$created);
        return self::SUCCESS;
    }

    private function staffForZone(string $zoneId)
    {
        $roles=Role::whereIn('code',['superadmin','admin','coordinator'])->get()
            ->map(fn($role)=>(string)$role->id)->all();
        if(!$roles)return collect();
        return User::where('is_active',true)->whereIn('role_id',$roles)->get()
            ->filter(fn($user)=>in_array($user->role_code,['superadmin','admin'],true)
                || (string)$user->zone_id===$zoneId);
    }

    private function noticeOnce(string $key,User $recipient,string $title,string $message,?string $reportId,?string $zoneId,bool $dry):int
    {
        if(Notice::where('alert_key',$key)->exists())return 0;
        if(!$dry){
            Notice::create(['alert_key'=>$key,'recipient_user_id'=>(string)$recipient->id,
                'report_id'=>$reportId,'zone_id'=>$zoneId,'title'=>$title,'message'=>$message,
                'is_read'=>false,'created_at'=>now()]);
        }
        return 1;
    }
}
