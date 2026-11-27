<?php
namespace App\Http\Controllers;
use App\Models\{Schedule,ScheduleException,LocalRule,Zone,WasteCategory,CollectionPoint};
use Illuminate\Http\Request;
use App\Services\ReportPdf;
class ResidentGuideController extends Controller
{
    public function schedules(Request $request)
    {
        $user=$request->user();$isResident=$user->role_code==='resident';
        $zones=Zone::where('is_active',true)->orderBy('name')->get();$categories=WasteCategory::where('is_active',true)->orderBy('local_label')->get();
        $zone=$isResident?(string)($user->zone_id??''):(string)$request->query('zone_id','');
        $category=(string)$request->query('category_id','');
        $q=Schedule::where('publication_status','published');
        if($zone)$q->where('zone_id',$zone);
        if($category)$q->where('category_id',$category);
        $today=now()->toDateString();
        $q->where('effective_from','<=',$today)->where(function($query) use($today){$query->whereNull('effective_until')->orWhere('effective_until','>=',$today);});
        $schedules=$q->orderBy('weekday')->orderBy('start_time')->get();
        $scheduleIds=$schedules->pluck('id')->map(fn($id)=>(string)$id)->all();
        $exceptions=$scheduleIds?ScheduleException::whereIn('schedule_id',$scheduleIds)->where('publication_status','published')->where('affected_date','>=',$today)->orderBy('affected_date')->get():collect();
        return view('schedules.index',compact('zones','categories','schedules','exceptions','zone','category','isResident'));
    }
    public function rules(Request $request)
    {
        $zone=$request->user()->role_code==='resident'?(string)($request->user()->zone_id??''):(string)$request->query('zone_id','');
        $q=LocalRule::where('publication_status','published');$today=now()->toDateString();
        $q->where('effective_from','<=',$today)->where(fn($q)=>$q->whereNull('effective_until')->orWhere('effective_until','>=',$today));
        if($zone)$q->where(fn($q)=>$q->whereNull('zone_id')->orWhere('zone_id',$zone));
        $rules=$q->orderBy('published_at','desc')->get();
        return view('rules.index',compact('rules'));
    }
    public function printSchedules(Request $request, ReportPdf $pdf)
    {
        $data=(array)$this->schedules($request)->getData();
        return $pdf->respond('print.schedules',$data,'collection-schedules-'.now()->format('Ymd').'.pdf',$request->boolean('download'),'landscape');
    }
}
