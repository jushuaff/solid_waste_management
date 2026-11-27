<?php
namespace App\Http\Controllers;
use App\Models\{Report,Assignment,User,Role};
use App\Services\{ReportWorkflow,NoticeService};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class ReportWorkflowController extends Controller
{
    public function change(Request $request,string $report,ReportWorkflow $workflow,NoticeService $notices)
    {
        $report=Report::findOrFail($report);$actor=$request->user();
        $data=$request->validate(['action'=>'required|in:assign,reassign,reject,start,resolve,exception,close',
            'reason'=>'required|string|max:3000','crew_user_id'=>'nullable|string']);
        $action=$data['action'];$role=$actor->role_code;
        if($role==='coordinator'){
            abort_unless($actor->zone_id && (string)$actor->zone_id===(string)$report->zone_id,403,'Outside your assigned collection area.');
        }
        if(in_array($action,['assign','reassign','reject','close'],true))abort_unless(in_array($role,['coordinator','admin','superadmin'],true),403);
        else abort_unless($role==='crew',403);
        if(in_array($action,['assign','reassign'],true)){
            if(empty($data['crew_user_id']))throw ValidationException::withMessages(['crew_user_id'=>'Select a crew member.']);
            $expected=$action==='reassign'?'Exception':'Under Review';
            abort_unless(in_array($report->current_status,[$expected,'Verified'],true),422,'This case is no longer eligible for assignment.');
            $crewRole=Role::where('code','crew')->firstOrFail();
            $crew=User::find($data['crew_user_id']);
            if($crew && ((string)$crew->role_id!==(string)$crewRole->id || !$crew->is_active))$crew=null;
            if(!$crew)throw ValidationException::withMessages(['crew_user_id'=>'Select an active crew member.']);
            if($crew->zone_id && (string)$crew->zone_id!==(string)$report->zone_id)throw ValidationException::withMessages(['crew_user_id'=>'Crew is not assigned to the report zone.']);
            $oldCrewId=(string)($report->assigned_crew_id??'');
            $workflow->execute(function()use($workflow,$report,$action,$data,$actor,$crew){
                if($report->current_status==='Under Review')$workflow->transition($report,'Verified',$data['reason'],$actor);
                Assignment::create(['report_id'=>(string)$report->id,'crew_user_id'=>(string)$crew->id,'assigned_by'=>(string)$actor->id,'assigned_at'=>now()]);
                $report->assigned_crew_id=(string)$crew->id;$report->save();
                $workflow->transition($report,'Assigned',$data['reason'],$actor);
            });
            if($oldCrewId && $oldCrewId!==(string)$crew->id){
                $notices->assignmentWithdrawn($report,$oldCrewId);
            }
        } else {
            if(in_array($action,['start','resolve','exception'],true))abort_unless((string)$report->assigned_crew_id===(string)$actor->id,403);
            $next=match($action){'reject'=>'Rejected','start'=>'In Progress','resolve'=>'Resolved','exception'=>'Exception','close'=>'Closed'};
            $workflow->execute(function()use($workflow,$report,$next,$data,$actor){
                $workflow->transition($report,$next,$data['reason'],$actor);
                if(in_array($next,['Resolved','Exception'],true)){
                    $assignment=Assignment::where('report_id',(string)$report->id)->orderBy('assigned_at','desc')->first();
                    if($assignment){$assignment->outcome=$next;$assignment->outcome_note=$data['reason'];$assignment->completed_at=now();$assignment->save();}
                }
            });
        }
        return redirect()->route('reports.show',$report)->with('status','Report updated: '.$report->current_status);
    }
}
