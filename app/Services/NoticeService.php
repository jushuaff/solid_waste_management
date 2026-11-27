<?php
namespace App\Services;

use App\Models\{Notice,User,Role,Report,Schedule,LocalRule,ScheduleException,Zone,Household,GarbageFeeCharge,FeePaymentSubmission};

class NoticeService
{
    /**
     * Case audience: resident owner, all active barangay administrators / superadmins,
     * the coordinator assigned to the zone, and the currently assigned crew.
     * Never deliver another resident's incident details or expose private cases to eco-aides.
     */
    private function caseRecipients(Report $report, bool $includeResident = true): array
    {
        $roles=Role::whereIn('code',['superadmin','admin','coordinator'])->get()
            ->keyBy('code');
        $ids=array_filter(array_map(fn($code)=>(string)($roles->get($code)?->id??''),['superadmin','admin','coordinator']));
        $audience=[];
        if($ids){
            foreach(User::where('is_active',true)->whereIn('role_id',$ids)->get() as $staff){
                if($staff->role_code==='coordinator' &&
                    (!$staff->zone_id || (string)$staff->zone_id!==(string)$report->zone_id))continue;
                $audience[(string)$staff->id]=$staff;
            }
        }
        if($includeResident && $report->resident_id){
            $resident=User::find($report->resident_id);
            if($resident && $resident->is_active && $resident->role_code==='resident')$audience[(string)$resident->id]=$resident;
        }
        if($report->assigned_crew_id){
            $crew=User::find($report->assigned_crew_id);
            if($crew && $crew->is_active && $crew->role_code==='crew'){
                $audience[(string)$crew->id]=$crew;
            }
        }
        return $audience;
    }

    private function send(User $recipient, string $title, string $message, array $links = []): void
    {
        Notice::create(array_merge([
            'recipient_user_id'=>(string)$recipient->id,
            'title'=>$title,'message'=>$message,
            'is_read'=>false,'created_at'=>now(),
        ],$links));
    }

    public function report(Report $report, string $status): void
    {
        $title=match($status){
            'Under Review'=>'New incident report',
            'Verified'=>'Report verified',
            'Assigned'=>'Collection task assigned',
            'In Progress'=>'Collection work started',
            'Resolved'=>'Report resolved',
            'Exception'=>'Collection follow-up needed',
            'Closed'=>'Report closed',
            'Rejected'=>'Report rejected',
            default=>'Report status updated',
        };
        foreach($this->caseRecipients($report) as $recipient){
            $isResident=(string)$recipient->id===(string)$report->resident_id;
            $isCrew=(string)$recipient->id===(string)$report->assigned_crew_id;
            $recipientTitle=$isResident?'Your report: '.$status:($isCrew && $status==='Assigned'?'New collection assignment':$title);
            $message=($isCrew && $status==='Assigned'?'You were assigned report ':'Incident ').$report->tracking_id
                .' is now '.$status.'.';
            $this->send($recipient,$recipientTitle,$message,['report_id'=>(string)$report->id]);
        }
    }

    /** Notify staff when a resident changes report details or evidence before staff action. */
    public function reportEdited(Report $report,string $reason='Report information updated'):void
    {
        foreach($this->caseRecipients($report,false) as $recipient){
            $this->send($recipient,$reason,'Incident '.$report->tracking_id.' has been updated. Review the latest information.',
                ['report_id'=>(string)$report->id]);
        }
    }

    /** When an assigned case changes crew, do not link the old crew to a now-forbidden case. */
    public function assignmentWithdrawn(Report $report,string $oldCrewId):void
    {
        $user=User::find($oldCrewId);
        if($user && $user->is_active && $user->role_code==='crew'){
            $this->send($user,'Collection assignment updated',
                'Incident '.$report->tracking_id.' is no longer assigned to you.');
        }
    }

    public function reportArchived(Report $report):void
    {
        foreach($this->caseRecipients($report) as $recipient){
            $this->send($recipient,'Incident archived','Incident '.$report->tracking_id.' has been archived.',
                ['report_id'=>(string)$report->id]);
        }
    }

    /** Public guidance: everyone in the affected zone plus admins; never notify unrelated residents. */
    public function publication(object $item,string $kind,string $action='published',?string $previousZone=null):void
    {
        $currentZone=$item instanceof ScheduleException
            ?(string)($item->schedule?->zone_id??'')
            :(string)($item->zone_id??'');
        $affected=[$currentZone];
        if($previousZone!==null && $previousZone!==$currentZone)$affected[]=$previousZone;
        $isGlobal=in_array('',$affected,true);
        $roles=Role::whereIn('code',['resident','crew','eco_aide','coordinator','superadmin','admin'])
            ->get()->keyBy('code');
        $roleIds=$roles->map(fn($r)=>(string)$r->id)->all();
        if(!$roleIds)return;

        $title=$kind.' '.match($action){
            'updated'=>'updated',
            'withdrawn'=>'withdrawn',
            default=>'published',
        };
        $details=match(true){
            $item instanceof Schedule=>trim(($item->zone?->name??'All zones').' · '.
                ($item->category?->local_label??'Waste collection').' · '.
                (config('swm.days')[(int)$item->weekday]??'Scheduled day').' '.
                ($item->start_time??'').'–'.($item->end_time??'')),
            $item instanceof ScheduleException=>'Affected date: '.($item->affected_date??'').
                '. '.($item->reason??''),
            $item instanceof LocalRule=>(string)($item->title??'Collection guidelines'),
            default=>'',
        };
        $message=match($action){
            'updated'=>'An existing '.$kind.' was updated.',
            'withdrawn'=>'A previously published '.$kind.' was withdrawn.',
            default=>'A new '.$kind.' was published.',
        };
        $message.= ' '.\Illuminate\Support\Str::limit($details,250).' Please review the latest barangay guidance.';
        $links=[
            'schedule_id'=>$item instanceof Schedule?(string)$item->id:null,
            'rule_id'=>$item instanceof LocalRule?(string)$item->id:null,
            'exception_id'=>$item instanceof ScheduleException?(string)$item->id:null,
        ];
        $users=User::where('is_active',true)->whereIn('role_id',array_values($roleIds))->get();
        foreach($users as $recipient){
            $role=$recipient->role_code;
            // Barangay administrators receive all published updates for oversight.
            if(!in_array($role,['superadmin','admin'],true) &&
                !$isGlobal && (! $recipient->zone_id ||
                    ! in_array((string)$recipient->zone_id,$affected,true)))continue;
            $this->send($recipient,$title,$message,$links);
        }
    }

    /**
     * Receiving-account changes affect where payments are sent.
     * Alert only residents linked to the affected city's household zones.
     * Do not expose account numbers in the notification itself.
     */
    public function paymentInstructionsChanged(string $cityId):void
    {
        $zoneIds=Zone::where('fee_city_id',$cityId)->pluck('id')
            ->map(fn($id)=>(string)$id)->all();
        if(!$zoneIds)return;

        $ids=Household::whereIn('zone_id',$zoneIds)->where('is_active',true)
            ->whereNull('archived_at')->pluck('resident_user_id')
            ->filter()->map(fn($id)=>(string)$id)->unique()->all();
        if(!$ids)return;

        $residentRole=Role::where('code','resident')->first();
        if(!$residentRole)return;
        foreach(User::findMany(array_values($ids)) as $recipient){
            if((string)$recipient->role_id!==(string)$residentRole->id || !$recipient->is_active)continue;
            $this->send($recipient,'Garbage fee payment instructions updated',
                'The receiving-account information for your city has changed. Verify the account details before making your next payment.',
                ['fee_city_id'=>$cityId]);
        }
    }

    /** Confirmed outside-app payments also create oversight notices for staff. */
    public function feePaymentRecorded(GarbageFeeCharge $charge,int $amount,string $method,
        string $status,string $actorId):void
    {
        $roles=Role::whereIn('code',['superadmin','admin','coordinator'])->get()
            ->map(fn($r)=>(string)$r->id)->all();
        if(!$roles)return;
        foreach(User::where('is_active',true)->whereIn('role_id',$roles)->get() as $person){
            if((string)$person->id===$actorId)continue;
            if($person->role_code==='coordinator'
                && (string)$person->zone_id!==(string)$charge->zone_id)continue;
            $this->send($person,'Household fee payment verified',
                'A '.strtoupper($method).' payment of '.\App\Services\FeeLedger::money($amount).
                ' was recorded for household '.($charge->household?->reference??'account').
                ' ('.$charge->billing_month.'). Current status: '.$status.'.',
                ['fee_charge_id'=>(string)$charge->id]);
        }
    }

    /** Only admin and superadmin are authorized to verify submitted transfer evidence. */
    public function feeSubmissionReceived(FeePaymentSubmission $entry):void
    {
        $roleIds=Role::whereIn('code',['superadmin','admin'])->get()
            ->map(fn($r)=>(string)$r->id)->all();
        if(!$roleIds)return;
        foreach(User::where('role_id','!=','')->whereIn('role_id',$roleIds)
            ->where('is_active',true)->get() as $person){
            $this->send($person,'Online fee payment awaiting verification',
                'Household '.($entry->household?->reference??'payment').
                ' submitted a transfer receipt for '.($entry->charge?->billing_month??'a billing month').
                '. Review the proof before confirming the payment.',
                ['fee_submission_id'=>(string)$entry->id]);
        }
    }

    public function feeSubmissionReviewed(FeePaymentSubmission $entry,int $balance):void
    {
        $resident=User::find($entry->resident_user_id);
        if(!$resident || !$resident->is_active || $resident->role_code!=='resident')return;
        if($entry->status==='verified'){
            $title=$balance===0?'Garbage fee fully paid':'Online payment verified';
            $message='Your online transfer of '.\App\Services\FeeLedger::money((int)$entry->amount_centavos).
                ' for '.($entry->charge?->billing_month??'your billing month').
                ' has been confirmed. Remaining balance: '.\App\Services\FeeLedger::money($balance).
                '. View the payment tracker for details.';
        }else{
            $title='Payment receipt needs correction';
            $message='Your uploaded transfer receipt for '.($entry->charge?->billing_month??'your billing month').
                ' was not accepted. Reason: '.($entry->review_note??'Please contact the collection office.').
                ' You can submit another receipt.';
        }
        $this->send($resident,$title,$message,['fee_charge_id'=>(string)$entry->charge_id]);
    }

    /** Record material segregation problems so responsible barangay staff can follow up. */
    public function segregationAlert(string $zoneId,string $reference):void
    {
        $zone=Zone::find($zoneId);
        $roles=Role::whereIn('code',['superadmin','admin','coordinator'])->get()
            ->pluck('id')->map(fn($id)=>(string)$id)->all();
        if(!$roles)return;
        foreach(User::where('is_active',true)->whereIn('role_id',$roles)->get() as $recipient){
            if($recipient->role_code==='coordinator' &&
               (string)$recipient->zone_id!==$zoneId)continue;
            $this->send($recipient,'Segregation concern recorded',
                'A non-segregated pickup was recorded in '.($zone?->name??'a collection area').
                '. Review the inspection log ('.$reference.').',
                ['zone_id'=>$zoneId]);
        }
    }

    /** Significant MRF adjustments (not every daily weigh-in) need supervisor review. */
    public function mrfAdjustment(string|array $zones):void
    {
        $zones=array_values(array_unique(array_filter(array_map('strval',(array)$zones))));
        if(!$zones)return;
        $roles=Role::whereIn('code',['superadmin','admin','coordinator'])->get()
            ->pluck('id')->map(fn($id)=>(string)$id)->all();
        if(!$roles)return;
        foreach(User::where('is_active',true)->whereIn('role_id',$roles)->get() as $recipient){
            if($recipient->role_code==='coordinator' &&
               !in_array((string)$recipient->zone_id,$zones,true))continue;
            $this->send($recipient,'MRF record adjusted',
                'A materials recovery weigh-in was changed or archived. Review the monitoring dashboard.',
                ['zone_id'=>$recipient->role_code==='coordinator'?(string)$recipient->zone_id:$zones[0]]);
        }
    }
}
