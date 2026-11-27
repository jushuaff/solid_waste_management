<?php
namespace App\Http\Controllers;

use App\Models\{Notice,Report};
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
        if($notice->schedule_id || $notice->exception_id) return redirect()->route('schedules.index');
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
