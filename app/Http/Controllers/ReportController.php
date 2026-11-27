<?php
namespace App\Http\Controllers;
use App\Models\{Report,Zone,WasteCategory,Schedule,StatusHistory,User,Assignment};
use App\Services\{ReportWorkflow,ReportPdf};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class ReportController extends Controller
{
    public function scope(Request $request)
    {
        $q=Report::query();$role=$request->user()->role_code;$id=(string)$request->user()->id;
        if(!$request->boolean('include_archived') || !in_array($role,['superadmin','admin'],true))$q->whereNull('archived_at');
        if($role==='eco_aide')abort(403,'Eco-aides cannot access resident case records.');
        if($role==='coordinator'){
            abort_unless($request->user()->zone_id,403,'A coordinator zone must be assigned.');
            $q->where('zone_id',(string)$request->user()->zone_id);
        }
        if($role==='resident')$q->where('resident_id',$id);
        if($role==='crew')$q->where('assigned_crew_id',$id);
        if($request->filled('zone_id')&&$role!=='resident')$q->where('zone_id',$request->query('zone_id'));
        if($request->filled('status'))$q->where('current_status',$request->query('status'));
        if($request->filled('from'))$q->where('incident_date','>=',$request->query('from'));
        if($request->filled('to'))$q->where('incident_date','<=',$request->query('to'));
        return $q;
    }
    private function authorizeReport(Request $request, Report $report): void
    {
        $role=$request->user()->role_code;$id=(string)$request->user()->id;
        if($role==='eco_aide')abort(403,'Eco-aides cannot access resident case records.');
        if($role==='coordinator')abort_unless($request->user()->zone_id && (string)$report->zone_id===(string)$request->user()->zone_id,404);
        if($role==='resident')abort_unless((string)$report->resident_id===$id,404);
        if($role==='crew')abort_unless((string)$report->assigned_crew_id===$id,404);
    }
    public function index(Request $request)
    {
        $reports=$this->scope($request)->orderBy('submitted_at','desc')->paginate(15)->withQueryString();
        $zones=Zone::where('is_active',true)->get();return view('reports.index',compact('reports','zones'));
    }
    public function create(Request $request)
    {
        $user=$request->user();
        abort_unless($user->role_code==='resident',403);
        $zones=Zone::where('is_active',true)->get();$categories=WasteCategory::where('is_active',true)->get();
        $schedules=Schedule::where('publication_status','published')->where('zone_id',(string)$user->zone_id)->get();
        return view('reports.create',compact('zones','categories','schedules'));
    }
    public function store(Request $request,ReportWorkflow $workflow)
    {
        abort_unless($request->user()->role_code==='resident',403);
        $data=$request->validate(['zone_id'=>'required|string','category_id'=>'required|string','schedule_id'=>'nullable|string',
          'incident_date'=>'required|date|before_or_equal:today',
          'location'=>'required|string|max:250','description'=>'required|string|max:4000',
          'photo'=>'nullable|image|mimes:jpeg,png,webp|max:4096','privacy'=>'accepted',
          'incident_type'=>'required|in:missed_collection,illegal_dumping',
          'latitude'=>'nullable|required_if:incident_type,illegal_dumping|numeric|between:-90,90',
          'longitude'=>'nullable|required_if:incident_type,illegal_dumping|numeric|between:-180,180']);
        if((string)$request->user()->zone_id!==$data['zone_id'])throw ValidationException::withMessages(['zone_id'=>'Reports must use the zone assigned to your account.']);
        if(!Zone::find($data['zone_id'])||!WasteCategory::find($data['category_id']))throw ValidationException::withMessages(['category_id'=>'Select a valid zone and waste category.']);
        if(!empty($data['schedule_id'])){
            $schedule=Schedule::find($data['schedule_id']);
            if(!$schedule || $schedule->zone_id!==$data['zone_id'] || $schedule->category_id!==$data['category_id'] || $schedule->publication_status!=='published')throw ValidationException::withMessages(['schedule_id'=>'Please choose a matching published schedule.']);
        }
        if(\Illuminate\Support\Carbon::parse($data['incident_date'])->lt(now()->subDays(config('swm.max_report_age_days'))->startOfDay()))throw ValidationException::withMessages(['incident_date'=>'This date is outside the permitted reporting period.']);
        $path=null;
        if($request->hasFile('photo'))$path=$request->file('photo')->store('report-evidence','local');
        try {
            $report=$workflow->execute(function() use($request,$data,$workflow,$path){
                $report=Report::create([
                  'tracking_id'=>'SWM-'.now()->format('Y').'-'.strtoupper(Str::random(10)),
                  'resident_id'=>(string)$request->user()->id,'zone_id'=>$data['zone_id'],'category_id'=>$data['category_id'],
                  'schedule_id'=>$data['schedule_id']??null,'incident_date'=>$data['incident_date'],
                  'location'=>$data['location'],'description'=>$data['description'],'photo_path'=>$path,
                  'incident_type'=>$data['incident_type'],'latitude'=>$data['latitude']??null,'longitude'=>$data['longitude']??null,
                  'current_status'=>'Submitted','submitted_at'=>now(),
                ]);
                StatusHistory::create(['report_id'=>(string)$report->id,'changed_by'=>(string)$request->user()->id,
                  'actor_type'=>'user','from_status'=>null,'to_status'=>'Submitted','reason'=>'Report submitted','changed_at'=>now()]);
                $workflow->transition($report,'Under Review','Queued for review');
                return $report;
            });
        }catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
        return redirect()->route('reports.show',$report)->with('status','Report submitted. Keep your reference: '.$report->tracking_id);
    }
    public function show(Request $request,string $report)
    {
        $report=Report::findOrFail($report);$this->authorizeReport($request,$report);
        $history=StatusHistory::where('report_id',(string)$report->id)->orderBy('changed_at','asc')->get();
        $assignments=Assignment::where('report_id',(string)$report->id)->orderBy('assigned_at','desc')->get();
        $crews=User::where('role_id',(string)\App\Models\Role::where('code','crew')->firstOrFail()->id)->where('is_active',true)->get();
        return view('reports.show',compact('report','history','assignments','crews'));
    }
    public function edit(Request $request,string $report)
    {
        $report=Report::findOrFail($report);$this->authorizeReport($request,$report);
        abort_unless($request->user()->role_code==='resident' && $report->current_status==='Under Review',403,'Only an unreviewed report can be amended.');
        $categories=WasteCategory::where('is_active',true)->get();
        return view('reports.edit',compact('report','categories'));
    }
    public function update(Request $request,string $report)
    {
        $report=Report::findOrFail($report);$this->authorizeReport($request,$report);
        abort_unless($request->user()->role_code==='resident' && $report->current_status==='Under Review',403,'This report is already under staff action.');
        $data=$request->validate(['location'=>'required|string|max:250','description'=>'required|string|max:4000',
           'incident_date'=>'required|date|before_or_equal:today','category_id'=>'required|string','schedule_id'=>'nullable|string',
           'incident_type'=>'required|in:missed_collection,illegal_dumping',
           'latitude'=>'nullable|required_if:incident_type,illegal_dumping|numeric|between:-90,90',
           'longitude'=>'nullable|required_if:incident_type,illegal_dumping|numeric|between:-180,180']);
        if(!WasteCategory::find($data['category_id']))throw ValidationException::withMessages(['category_id'=>'Please select a valid category.']);
        if(!empty($data['schedule_id'])){
            $schedule=Schedule::find($data['schedule_id']);
            if(!$schedule || (string)$schedule->zone_id!==(string)$report->zone_id || (string)$schedule->category_id!==(string)$data['category_id'] || $schedule->publication_status!=='published')throw ValidationException::withMessages(['schedule_id'=>'Choose a matching published schedule.']);
        }
        if(\Illuminate\Support\Carbon::parse($data['incident_date'])->lt(now()->subDays(config('swm.max_report_age_days'))->startOfDay()))throw ValidationException::withMessages(['incident_date'=>'This date is outside the allowed reporting window.']);
        $fields=['location','description','incident_date','category_id','schedule_id','incident_type','latitude','longitude'];
        $before=[];foreach($fields as $field)$before[$field]=$report->$field;
        $next=[];foreach($fields as $field)$next[$field]=$data[$field]??null;
        if($before===$next)return back()->with('status','No changes to save.');
        $changes=$report->amendments??[];
        $changes[]=['before'=>$before,'after'=>$next,'actor_id'=>(string)$request->user()->id,'changed_at'=>now()->toIso8601String()];
        $report->fill($next);$report->amendments=$changes;$report->save();
        return redirect()->route('reports.show',$report)->with('status','Your report was amended while awaiting coordinator review.');
    }
    public function archive(Request $request,string $report)
    {
        abort_unless($request->user()->canAdmin(),403);
        $report=Report::findOrFail($report);
        abort_unless(in_array($report->current_status,['Rejected','Resolved','Closed'],true),422,'Only finalized cases can be archived.');
        $report->archived_at=now();$report->archived_by=(string)$request->user()->id;$report->save();
        return redirect()->route('reports.index')->with('status','Report archived (not destroyed).');
    }
    public function print(Request $request,string $report,ReportPdf $pdf)
    {
        $report=Report::findOrFail($report);$this->authorizeReport($request,$report);
        $history=StatusHistory::where('report_id',(string)$report->id)->orderBy('changed_at')->get();
        $filename='case-'.preg_replace('/[^A-Za-z0-9_-]/','-',(string)$report->tracking_id).'.pdf';
        return $pdf->respond('print.report',compact('report','history'),$filename,$request->boolean('download'));
    }
    public function evidence(Request $request,string $report)
    {
        $report=Report::findOrFail($report);$this->authorizeReport($request,$report);
        abort_unless($report->photo_path&&Storage::disk('local')->exists($report->photo_path),404);
        return Storage::disk('local')->download($report->photo_path);
    }
    public function printableSummary(Request $request,ReportPdf $pdf)
    {
        abort_unless($request->user()->canManage(),403);
        $records=$this->scope($request)->orderBy('incident_date','desc')->limit(2000)->get();
        $total=$records->count();$byStatus=$records->groupBy('current_status')->map->count();
        return $pdf->respond('print.summary',compact('records','total','byStatus'),'collection-report-summary-'.now()->format('Ymd-His').'.pdf',$request->boolean('download'),'landscape');
    }
}
