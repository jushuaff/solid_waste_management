<?php
namespace App\Http\Controllers;

use App\Models\{Household, PickupInspection, MrfWeighIn, Zone, WasteCategory, User};
use App\Services\{LinisMetrics,ReportPdf};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LinisController extends Controller
{
    private const MODEL = ['households'=>Household::class, 'inspections'=>PickupInspection::class, 'weigh-ins'=>MrfWeighIn::class];

    private function model(string $module):string
    {
        abort_unless(isset(self::MODEL[$module]),404);
        return self::MODEL[$module];
    }

    private function authorizeModule(Request $request,string $module,bool $write=false):void
    {
        $role=$request->user()->role_code;
        $permitted=match($module) {
            'households'=>$write?['superadmin','admin','coordinator']:['superadmin','admin','coordinator','eco_aide','crew'],
            'inspections'=>['superadmin','admin','coordinator','eco_aide','crew'],
            'weigh-ins'=>['superadmin','admin','coordinator','eco_aide'],
            default=>[]
        };
        abort_unless(in_array($role,$permitted,true),403);
    }

    private function zoneScope(Request $request,$query)
    {
        $role=$request->user()->role_code;
        if(in_array($role,['eco_aide','crew'],true) ||
          ($role==='coordinator' && $request->user()->zone_id)) {
            abort_unless($request->user()->zone_id,403,'Assign a working zone to this staff account first.');
            $query->where('zone_id',(string)$request->user()->zone_id);
        }
        return $query;
    }

    private function allowedZone(Request $request,string $zoneId):void
    {
        abort_unless(Zone::whereKey($zoneId)->where('is_active',true)->exists(),422,'Select an active zone.');
        $role=$request->user()->role_code;
        if(in_array($role,['eco_aide','crew'],true) || ($role==='coordinator' && $request->user()->zone_id)) {
            abort_unless($request->user()->zone_id && (string)$request->user()->zone_id===$zoneId,403,'Outside your assigned zone.');
        }
    }

    private function find(Request $request,string $module,string $id,bool $write=false)
    {
        $this->authorizeModule($request,$module,$write);
        $model=$this->model($module);
        return $this->zoneScope($request,$model::query())->findOrFail($id);
    }

    public function index(Request $request,string $module)
    {
        $this->authorizeModule($request,$module);
        $model=$this->model($module);
        $q=$this->zoneScope($request,$model::query())->whereNull('archived_at');
        if($request->filled('zone_id')){
            $this->allowedZone($request,(string)$request->query('zone_id'));
            $q->where('zone_id',$request->query('zone_id'));
        }
        if($request->filled('from') && $module!=='households')$q->where($module==='inspections'?'inspected_at':'measured_at','>=',\Carbon\Carbon::parse($request->query('from'))->startOfDay());
        if($request->filled('to') && $module!=='households')$q->where($module==='inspections'?'inspected_at':'measured_at','<=',\Carbon\Carbon::parse($request->query('to'))->endOfDay());
        $records=$q->orderBy('created_at','desc')->paginate(20)->withQueryString();
        $zones=Zone::where('is_active',true)->orderBy('name')->get();
        return view('linis.index',compact('records','zones','module'));
    }

    public function create(Request $request,string $module)
    {
        $this->authorizeModule($request,$module,true);
        $this->model($module);
        $record=null;
        return $this->form($request,$module,$record);
    }

    public function edit(Request $request,string $module,string $id)
    {
        $record=$this->find($request,$module,$id,true);
        abort_if($record->archived_at,403,'Archived records cannot be edited.');
        return $this->form($request,$module,$record);
    }

    private function form(Request $request,string $module,$record)
    {
        $zones=Zone::where('is_active',true)->orderBy('name')->get();
        if(in_array($request->user()->role_code,['eco_aide','crew'],true) ||
          ($request->user()->role_code==='coordinator' && $request->user()->zone_id)){
            $zones=$zones->filter(fn($z)=>(string)$z->id===(string)$request->user()->zone_id);
        }
        $categories=WasteCategory::where('is_active',true)->get();
        $households=Household::where('is_active',true)->whereNull('archived_at')->get()->filter(
           fn($h)=>$zones->contains(fn($z)=>(string)$z->id===(string)$h->zone_id)
        );
        return view('linis.form',compact('module','record','zones','categories','households'));
    }

    private function validated(Request $request,string $module):array
    {
        $base=['zone_id'=>'required|string'];
        $rules=match($module){
            'households'=>[
                'reference'=>'required|string|max:100',
                'location_description'=>'required|string|max:250',
                'is_active'=>'sometimes|boolean'],
            'inspections'=>[
                'household_id'=>'nullable|string',
                'inspected_at'=>'required|date|before_or_equal:now',
                'segregated'=>'required|boolean',
                'note'=>'nullable|string|max:2000',
                'photo'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:4096'],
            'weigh-ins'=>[
                'category_id'=>'required|string',
                'measured_at'=>'required|date|before_or_equal:now',
                'weight_kg'=>'required|numeric|gt:0|max:10000000',
                'diverted_kg'=>'required|numeric|min:0|max:10000000',
                'note'=>'nullable|string|max:2000'],
            default=>abort(404)
        };
        $data=$request->validate(array_merge($base,$rules));
        $this->allowedZone($request,(string)$data['zone_id']);
        if($module==='households'){
            $data['is_active']=$request->boolean('is_active');
        } elseif($module==='inspections'){
            if(!empty($data['household_id'])){
                $h=Household::find($data['household_id']);
                if(!$h || (string)$h->zone_id!==(string)$data['zone_id'] || !$h->is_active || $h->archived_at)throw ValidationException::withMessages(['household_id'=>'Select an active household in the chosen zone.']);
            }
            $data['segregated']=$request->boolean('segregated');
            $data['inspected_at']=\Carbon\Carbon::parse($data['inspected_at']);
        } else {
            abort_unless(WasteCategory::whereKey($data['category_id'])->where('is_active',true)->exists(),422,'Invalid waste category.');
            if((float)$data['diverted_kg']>(float)$data['weight_kg'])throw ValidationException::withMessages(['diverted_kg'=>'Diverted weight cannot exceed the total weight.']);
            $data['weight_kg']=(float)$data['weight_kg'];
            $data['diverted_kg']=(float)$data['diverted_kg'];
            $data['measured_at']=\Carbon\Carbon::parse($data['measured_at']);
        }
        return $data;
    }

    public function store(Request $request,string $module)
    {
        $this->authorizeModule($request,$module,true);
        $model=$this->model($module);
        $data=$this->validated($request,$module);
        if($module==='households'){
            $data['reference']=trim($data['reference']);
            if(Household::where('zone_id',$data['zone_id'])->where('reference',$data['reference'])->exists()){
                throw ValidationException::withMessages(['reference'=>'Household reference already exists in this zone.']);
            }
        } elseif($module==='inspections'){
            $data['inspector_user_id']=(string)$request->user()->id;
            if($request->hasFile('photo'))$data['photo_path']=$request->file('photo')->store('linis/violations','local');
            unset($data['photo']);
        } else $data['recorded_by']=(string)$request->user()->id;
        $data['change_history']=[];
        try {$record=$model::create($data);}
        catch(\Throwable $e){if(!empty($data['photo_path']))Storage::disk('local')->delete($data['photo_path']);throw $e;}
        return redirect()->route('linis.index',$module)->with('status','Record saved.');
    }

    public function update(Request $request,string $module,string $id)
    {
        $record=$this->find($request,$module,$id,true);
        abort_if($record->archived_at,403);
        $data=$this->validated($request,$module);
        if($module==='households'){
            $duplicate=Household::where('zone_id',$data['zone_id'])->where('reference',$data['reference'])->first();
            if($duplicate && (string)$duplicate->id!==(string)$record->id)throw ValidationException::withMessages(['reference'=>'Household reference already exists in this zone.']);
        }
        if($module==='inspections'){
            if($request->hasFile('photo'))$data['photo_path']=$request->file('photo')->store('linis/violations','local');
            unset($data['photo']);
        }
        $before=[];foreach(array_keys($data) as $key){if($key==='photo_path')continue;$before[$key]=$record->$key;}
        $changes=(array)($record->change_history??[]);
        $changes[]=['by'=>(string)$request->user()->id,'at'=>now()->toIso8601String(),'before'=>$before];
        $record->fill($data);$record->change_history=$changes;
        try{$record->save();}catch(\Throwable $e){if(isset($data['photo_path']))Storage::disk('local')->delete($data['photo_path']);throw $e;}
        return redirect()->route('linis.index',$module)->with('status','Updated with revision history.');
    }

    public function destroy(Request $request,string $module,string $id)
    {
        $record=$this->find($request,$module,$id,true);
        if(!$record->archived_at){
            $record->archived_at=now();
            $record->archived_by=(string)$request->user()->id;
            $record->save();
        }
        return redirect()->route('linis.index',$module)->with('status','Record archived; historical data retained.');
    }

    public function evidence(Request $request,string $id)
    {
        $record=$this->find($request,'inspections',$id);
        abort_unless($record->photo_path && Storage::disk('local')->exists($record->photo_path),404);
        return Storage::disk('local')->download($record->photo_path);
    }

    public function dashboard(Request $request,LinisMetrics $metrics)
    {
        abort_unless(in_array($request->user()->role_code,['superadmin','admin','coordinator','eco_aide'],true),403);
        $month=(string)$request->query('month',now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month),422,'Invalid month.');
        $from=\Carbon\Carbon::createFromFormat('!Y-m',$month)->startOfMonth();
        $to=$from->copy()->endOfMonth();
        $results=$metrics->forPeriod($request->user(),$from,$to);
        return view('linis.dashboard',compact('results','month','from','to'));
    }
    public function monthlyReport(Request $request,LinisMetrics $metrics,ReportPdf $pdf)
    {
        abort_unless(in_array($request->user()->role_code,['superadmin','admin','coordinator'],true),403);
        $month=(string)$request->query('month',now()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month),422,'Invalid month.');
        $from=\Carbon\Carbon::createFromFormat('!Y-m',$month)->startOfMonth();$to=$from->copy()->endOfMonth();
        $results=$metrics->forPeriod($request->user(),$from,$to);
        return $pdf->respond('linis.print-monthly',compact('results','month','from','to'),'linis-monthly-monitoring-'.$month.'.pdf',$request->boolean('download'),'landscape');
    }
}
