<?php
namespace App\Http\Controllers;
use App\Models\{User,Role,Zone,CollectionPoint,Schedule,ScheduleException,LocalRule,WasteCategory,Report};
use App\Services\{ResourceRegistry,NoticeService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ManagementController extends Controller
{
    private function definition(Request $request,string $resource):array
    {
        abort_unless($request->user()->canManage(),403);
        $spec=ResourceRegistry::get($resource);
        if(($spec['admin']??false) && !$request->user()->canAdmin()) abort(403);
        return $spec;
    }
    public function index(Request $request,string $resource)
    {
        $spec=$this->definition($request,$resource);
        $model=$spec['model'];$query=$model::query();
        if($search=trim((string)$request->query('q',''))){
            $field=match($resource){'rules'=>'title','users'=>'name','categories'=>'local_label','points'=>'name',default=>'name'};
            if(in_array($resource,['schedules','exceptions'],true))$query->where('publication_status',$search);
            else $query->where($field,'regex',new \MongoDB\BSON\Regex(preg_quote($search), 'i'));
        }
        $rows=$query->orderBy('created_at','desc')->paginate(15)->withQueryString();
        return view('management.index',compact('rows','spec','resource'));
    }
    public function create(Request $request,string $resource)
    {
        $spec=$this->definition($request,$resource);$item=null;$options=$this->options($spec);
        return view('management.form',compact('spec','resource','item','options'));
    }
    public function edit(Request $request,string $resource,string $id)
    {
        $spec=$this->definition($request,$resource);$model=$spec['model'];$item=$model::findOrFail($id);$options=$this->options($spec);
        return view('management.form',compact('spec','resource','item','options'));
    }
    private function options(array $spec):array
    {
        $out=[];foreach($spec['fields'] as $key=>$field){
            if(($field['type']??'')==='relation'){
                $model=$field['model'];$out[$key]=$model::orderBy($field['display']==='id'?'created_at':$field['display'])->get();
            }
        }return $out;
    }
    private function validated(Request $request,string $resource,array $spec,?object $item):array
    {
        if($resource==='users' && $item && $item->role_code==='superadmin' && $request->user()->role_code!=='superadmin')abort(403,'Only a superadministrator can modify superadministrator accounts.');
        $rules=[];foreach($spec['fields'] as $name=>$config){
            $rules[$name]=$config['rules'];
            if($resource==='users'&&$name==='password')$rules[$name]=$item?'prohibited':'required|string|min:12|max:120';
        }
        $data=$request->validate($rules);
        foreach($spec['fields'] as $name=>$config){
            if(($config['type']??'')==='boolean')$data[$name]=$request->boolean($name);
            if(($config['type']??'')==='relation' && array_key_exists($name,$data)){
                $value=$data[$name];if($value===null || $value===''){$data[$name]=null;continue;}
                $model=$config['model'];
                if(!$model::find($value))throw ValidationException::withMessages([$name=>'Please select a valid '.$config['label'].'.']);
            }
        }
        if($resource==='schedules'){
            if($data['end_time']<=$data['start_time'])throw ValidationException::withMessages(['end_time'=>'The end time must be after the start time.']);
            if($data['collection_method']==='designated_point'&&!($data['collection_point_id']??null))throw ValidationException::withMessages(['collection_point_id'=>'Choose a designated pickup point.']);
            if($data['collection_method']!=='designated_point')$data['collection_point_id']=null;
            if(($data['collection_point_id']??null)&&CollectionPoint::find($data['collection_point_id'])?->zone_id!==$data['zone_id'])throw ValidationException::withMessages(['collection_point_id'=>'The collection point must belong to this zone.']);
        }
        if($resource==='exceptions'){
            if($data['action']==='replacement'&&!($data['replacement_date']??null))throw ValidationException::withMessages(['replacement_date'=>'Provide the replacement date.']);
            if($data['action']==='time_change'&&(!($data['replacement_start_time']??null)||!($data['replacement_end_time']??null)))throw ValidationException::withMessages(['replacement_start_time'=>'Provide the new start and end times.']);
            if(($data['replacement_start_time']??null)&&($data['replacement_end_time']??null)&&$data['replacement_start_time'] >= $data['replacement_end_time'])throw ValidationException::withMessages(['replacement_end_time'=>'End time must be later.']);
        }
        if($resource==='users'){
            $data['email']=strtolower(trim($data['email']));
            $existing=User::where('email',$data['email'])->first();
            if($existing && (!$item || (string)$existing->id!==(string)$item->id))throw ValidationException::withMessages(['email'=>'Email address already exists.']);
            $role=Role::findOrFail($data['role_id']);
            if($role->code==='superadmin' && $request->user()->role_code!=='superadmin')abort(403,'Only superadministrators can assign this role.');
            if($item && $item->id===$request->user()->id && (!$data['is_active'] || $data['role_id']!==$request->user()->role_id))throw ValidationException::withMessages(['role_id'=>'You cannot deactivate or demote your own account.']);
            if(!empty($data['password']))$data['password']=Hash::make($data['password']);else unset($data['password']);
        }
        return $data;
    }
    public function store(Request $request,string $resource,NoticeService $notices)
    {
        $spec=$this->definition($request,$resource);$data=$this->validated($request,$resource,$spec,null);
        $model=$spec['model'];
        if(in_array($resource,['schedules','rules'],true)){
            $data['created_by']=(string)$request->user()->id;
            if($data['publication_status']==='published')$data['published_at']=now();
        }
        if($resource==='exceptions')$data['created_by']=(string)$request->user()->id;
        if($resource==='schedules')$data['revision']=1;
        if($resource==='rules')$data['version']=1;
        $item=$model::create($data);
        if(in_array($resource,['schedules','exceptions','rules'],true)&&($item->publication_status??null)==='published')$notices->publication($item,$spec['title']);
        return redirect()->route('management.index',$resource)->with('status','Record created.');
    }
    public function update(Request $request,string $resource,string $id,NoticeService $notices)
    {
        $spec=$this->definition($request,$resource);$model=$spec['model'];$item=$model::findOrFail($id);
        $data=$this->validated($request,$resource,$spec,$item);
        $publish=in_array($resource,['schedules','exceptions','rules'],true) && ($data['publication_status']??null)==='published';
        $changed=$item->getAttributes();
        $meaningful=false;foreach($data as $key=>$val){if((string)($changed[$key]??'')!==(string)($val??'')){$meaningful=true;break;}}
        if($resource==='schedules'&&$meaningful)$data['revision']=((int)($item->revision??1))+1;
        if($resource==='rules'&&$meaningful)$data['version']=((int)($item->version??1))+1;
        if($publish&&$meaningful && in_array($resource,['schedules','rules'],true))$data['published_at']=now();
        $item->update($data);
        if($publish&&$meaningful)$notices->publication($item,$spec['title']);
        return redirect()->route('management.index',$resource)->with('status','Record updated.');
    }

    /**
     * Dedicated administrator-only password reset flow; does not change profile fields.
     * Require the acting administrator's own current password for this sensitive action.
     */
    public function passwordForm(Request $request,string $id)
    {
        abort_unless($request->user()->role_code==='superadmin',403);
        $user=User::findOrFail($id);
        abort_if((string)$user->id===(string)$request->user()->id,403,'Use My Profile to change your own password.');
        return view('management.password',compact('user'));
    }

    public function changePassword(Request $request,string $id)
    {
        abort_unless($request->user()->role_code==='superadmin',403);
        $user=User::findOrFail($id);
        abort_if((string)$user->id===(string)$request->user()->id,403,'Use My Profile to change your own password.');
        $data=$request->validate([
            'current_password'=>['required','string'],
            'password'=>['required','string','min:12','max:120','confirmed'],
        ]);
        if(!Hash::check($data['current_password'],(string)$request->user()->password)){
            throw ValidationException::withMessages(['current_password'=>'Your administrator password is incorrect.']);
        }
        $user->password=Hash::make($data['password']);
        $user->setRememberToken(Str::random(60));
        $user->save();
        Log::notice('Administrator updated another user password',[
            'actor_user_id'=>(string)$request->user()->id,
            'target_user_id'=>(string)$user->id,
        ]);
        return redirect()->route('management.index','users')->with('status','Password updated for '.$user->name.'.');
    }

    public function destroy(Request $request,string $resource,string $id)
    {
        $spec=$this->definition($request,$resource);$model=$spec['model'];$item=$model::findOrFail($id);
        if($resource==='users'&&(string)$item->id===(string)$request->user()->id)abort(403,'You cannot delete your own account.');
        if($resource==='users' && $item->role_code==='superadmin' && $request->user()->role_code!=='superadmin')abort(403,'Only superadministrators may manage superadministrator accounts.');
        $protected=match($resource){
            'zones'=>Schedule::where('zone_id',$id)->exists()||Report::where('zone_id',$id)->exists()||User::where('zone_id',$id)->exists()||CollectionPoint::where('zone_id',$id)->exists(),
            'categories'=>Schedule::where('category_id',$id)->exists()||Report::where('category_id',$id)->exists(),
            'points'=>Schedule::where('collection_point_id',$id)->exists(),
            'schedules'=>Report::where('schedule_id',$id)->exists()||ScheduleException::where('schedule_id',$id)->exists(),
            'users'=>Report::where('resident_id',$id)->exists()||\App\Models\Assignment::where('crew_user_id',$id)->exists()||\App\Models\StatusHistory::where('changed_by',$id)->exists()||\App\Models\Notice::where('recipient_user_id',$id)->exists(),
            default=>false};
        if($protected) return back()->withErrors(['delete'=>'This record is in use; archive or deactivate it rather than deleting it.']);
        // To preserve provenance, only delete draft/unreferenced material.
        if(in_array($resource,['rules','exceptions'],true)&&$item->publication_status==='published')return back()->withErrors(['delete'=>'Published material cannot be deleted; mark it as draft/archived instead.']);
        $item->delete();return redirect()->route('management.index',$resource)->with('status','Unused record deleted.');
    }
}
