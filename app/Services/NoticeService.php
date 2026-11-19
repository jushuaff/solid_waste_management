<?php
namespace App\Services;
use App\Models\{Notice,User,Report,Schedule,LocalRule,ScheduleException};
class NoticeService
{
    public function report(Report $report, string $status): void
    {
        Notice::create(['recipient_user_id'=>(string)$report->resident_id,'report_id'=>(string)$report->id,
            'title'=>'Report '.$status,'message'=>'Your report '.$report->tracking_id.' is now '.$status.'.',
            'is_read'=>false,'created_at'=>now()]);
    }
    public function publication(object $item, string $kind): void
    {
        $zone=(string)($item->zone_id ?? '');
        if ($item instanceof ScheduleException) $zone=(string)($item->schedule?->zone_id ?? '');
        $query=User::where('is_active',true);
        $residentRole=\App\Models\Role::where('code','resident')->first();
        if (!$residentRole) return;
        $query->where('role_id',(string)$residentRole->id);
        if ($zone) $query->where('zone_id',$zone);
        // In production, queue notification fan-out for large populations.
        foreach ($query->get(['_id']) as $resident) {
            Notice::create([
                'recipient_user_id'=>(string)$resident->id,
                'schedule_id'=>$item instanceof Schedule?(string)$item->id:null,
                'rule_id'=>$item instanceof LocalRule?(string)$item->id:null,
                'exception_id'=>$item instanceof ScheduleException?(string)$item->id:null,
                'title'=>$kind.' published','message'=>'Your barangay has published a '.strtolower($kind).' update. Please review the relevant guide.',
                'is_read'=>false,'created_at'=>now(),
            ]);
        }
    }
}
