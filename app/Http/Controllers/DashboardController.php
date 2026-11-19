<?php
namespace App\Http\Controllers;
use App\Models\{Report,Schedule,Zone,Notice,Assignment,LocalRule};
use Illuminate\Http\Request;
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();$role=$user->role_code;
        $q=Report::query();
        if($role==='resident')$q->where('resident_id',(string)$user->id);
        if($role==='crew')$q->where('assigned_crew_id',(string)$user->id);
        $counts=['under_review'=>(clone $q)->where('current_status','Under Review')->count(),
          'active'=>(clone $q)->whereIn('current_status',['Verified','Assigned','In Progress','Exception'])->count(),
          'resolved'=>(clone $q)->where('current_status','Resolved')->count(),
          'total'=>(clone $q)->count()];
        $recent=$q->orderBy('submitted_at','desc')->limit(7)->get();
        $schedule=Schedule::where('publication_status','published');
        if($role==='resident' && $user->zone_id)$schedule->where('zone_id',(string)$user->zone_id);
        $schedules=$schedule->orderBy('weekday')->limit(5)->get();
        $notices=Notice::where('recipient_user_id',(string)$user->id)->orderBy('created_at','desc')->limit(5)->get();
        return view('dashboard',compact('counts','recent','schedules','notices'));
    }
}
