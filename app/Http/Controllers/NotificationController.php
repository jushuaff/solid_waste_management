<?php
namespace App\Http\Controllers;

use App\Models\{Notice,Report,Schedule,ScheduleException,GarbageFeeCharge,Household};
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function owned(Request $request,string $id):Notice
    {
        // Restrict read and open actions by recipient, including guessed notice IDs.
        return Notice::where('recipient_user_id',(string)$request->user()->id)->findOrFail($id);
    }

    public function index(Request $request)
    {
        $notices=Notice::where('recipient_user_id',(string)$request->user()->id)
            ->orderBy('created_at','desc')->paginate(20);
        return view('notifications.index',compact('notices'));
    }

    public function read(Request $request,string $id)
    {
        $notice=$this->owned($request,$id);
        $this->markRead($notice);
        return back()->with('status','Notification marked as read.');
    }

    public function open(Request $request,string $id)
    {
        $notice=$this->owned($request,$id);
        $this->markRead($notice);

        // Only local named routes are used; no untrusted redirect URLs.
        if($notice->fee_city_id){
            // Recipient ownership was verified above; the fee page independently
            // scopes the signed-in resident to their linked household accounts.
            return redirect()->route('fees.index');
        }
        if($notice->fee_charge_id){
            $charge=GarbageFeeCharge::find($notice->fee_charge_id);
            if($charge){
                $role=$request->user()->role_code;
                $canOpen=in_array($role,['superadmin','admin'],true)
                    || ($role==='coordinator' && $request->user()->zone_id
                        && (string)$request->user()->zone_id===(string)$charge->zone_id)
                    || ($role==='resident' && (string)(Household::find($charge->household_id)?->resident_user_id??'')
                        ===(string)$request->user()->id);
                if($canOpen){
                    if(str_starts_with((string)($notice->alert_key??''),'fee:overdue:')){
                        return redirect()->route('fees.history.index');
                    }
                    return redirect()->route('fees.index',[
                        'month'=>$charge->billing_month,
                        'highlight'=>(string)$charge->id
                    ]);
                }
            }
            return redirect()->route('notifications.index')
                ->with('status','This household charge is no longer accessible to your account.');
        }
        if($notice->report_id){
            $report=Report::find($notice->report_id);
            if($report){
                $user=$request->user();
                $role=$user->role_code;
                $allowed=match($role){
                    'superadmin','admin'=>true,
                    'coordinator'=>(bool)$user->zone_id && (string)$user->zone_id===(string)$report->zone_id,
                    'crew'=>(string)$user->id===(string)$report->assigned_crew_id,
                    'resident'=>(string)$user->id===(string)$report->resident_id,
                    default=>false,
                };
                if($allowed)return redirect()->route('reports.show',$report);
                return redirect()->route('notifications.index')
                    ->with('status','The report is no longer assigned to your account.');
            }
        }
        if($notice->rule_id) return redirect()->route('rules.index');
        if($notice->schedule_id || $notice->exception_id){
            // Schedule-change notices point to the underlying recurring schedule.
            $scheduleId=(string)($notice->schedule_id??'');
            if(!$scheduleId && $notice->exception_id){
                $exception=ScheduleException::find($notice->exception_id);
                $scheduleId=(string)($exception?->schedule_id??'');
            }
            $schedule=$scheduleId?Schedule::find($scheduleId):null;
            if(!$schedule){
                return redirect()->route('schedules.index')
                    ->with('status','This notice refers to a schedule that is no longer available. Review the current collection schedules.');
            }

            // Never bypass the resident's zone when following an old notification.
            if($request->user()->role_code==='resident'
                && (string)($request->user()->zone_id??'')!==(string)$schedule->zone_id){
                return redirect()->route('schedules.index')
                    ->with('status','This schedule no longer applies to your collection area. View your current schedules below.');
            }

            $today=now()->toDateString();
            if($schedule->publication_status!=='published'
                || (string)$schedule->effective_from>$today
                || ($schedule->effective_until && (string)$schedule->effective_until<$today)){
                return redirect()->route('schedules.index')
                    ->with('status','The schedule mentioned in this notification is not currently published or effective. Review the latest available schedules.');
            }

            return redirect()->route('schedules.index',['highlight'=>(string)$schedule->id]);
        }
        if($notice->zone_id && in_array($request->user()->role_code,['superadmin','admin','coordinator','eco_aide'],true)){
            return redirect()->route('linis.dashboard');
        }
        return redirect()->route('notifications.index');
    }

    private function markRead(Notice $notice):void
    {
        if(!(bool)$notice->is_read){
            $notice->is_read=true;
            $notice->read_at=now();
            $notice->save();
        }
    }
}
