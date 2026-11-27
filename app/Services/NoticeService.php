<?php
namespace App\Services;
use App\Models\{Notice,User,Role,Report,Schedule,LocalRule,ScheduleException};
class NoticeService
{
    public function report(Report $report, string $status): void
    {
        // Notify the resident whenever an incident status changes.
        Notice::create([
            'recipient_user_id'=>(string)$report->resident_id,
            'report_id'=>(string)$report->id,
            'title'=>'Report '.$status,
            'message'=>'Your report '.$report->tracking_id.' is now '.$status.'.',
            'is_read'=>false,'created_at'=>now(),
        ]);

        // New/exception/resolved incidents require attention from designated office staff.
        if(in_array($status,['Under Review','Exception','Resolved'],true)){
            $codes=['superadmin','admin','coordinator'];
            $roleIds=Role::whereIn('code',$codes)->get()
                ->map(fn($role)=>(string)$role->id)->all();
            if($roleIds){
                $staff=User::where('is_active',true)->whereIn('role_id',$roleIds)->get();
                foreach($staff as $recipient){
                    if($recipient->role_code==='coordinator'
                        && (!$recipient->zone_id || (string)$recipient->zone_id!==(string)$report->zone_id)){
                        continue;
                    }
                    Notice::create([
                        'recipient_user_id'=>(string)$recipient->id,
                        'report_id'=>(string)$report->id,
                        'title'=>match($status){
                            'Under Review'=>'New incident report',
                            'Exception'=>'Collection issue needs attention',
                            default=>'Collection report resolved',
                        },
                        'message'=>'Report '.$report->tracking_id.' is now '.$status.'.',
                        'is_read'=>false,'created_at'=>now(),
                    ]);
                }
            }
        }

        // The assigned crew receives a direct task notification.
        if($status==='Assigned' && $report->assigned_crew_id){
            $crew=User::find($report->assigned_crew_id);
            if($crew && $crew->is_active && $crew->role_code==='crew'){
                Notice::create([
                    'recipient_user_id'=>(string)$crew->id,
                    'report_id'=>(string)$report->id,
                    'title'=>'New collection assignment',
                    'message'=>'You have been assigned incident '.$report->tracking_id.'.',
                    'is_read'=>false,'created_at'=>now(),
                ]);
            }
        }
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
