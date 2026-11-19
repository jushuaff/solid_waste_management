<?php
namespace App\Services;
use App\Models\{Zone,WasteCategory,CollectionPoint,Schedule,ScheduleException,LocalRule,User,Role};
class ResourceRegistry
{
    /** Each field is an explicit allow-list entry; no user-selected model classes or columns. */
    public static function all(): array
    {
        return [
            'zones'=>['title'=>'Collection Zones','model'=>Zone::class,'admin'=>true,'fields'=>[
                'name'=>['label'=>'Zone / Purok','rules'=>'required|string|max:120'],
                'description'=>['label'=>'Description','type'=>'textarea','rules'=>'nullable|string|max:1200'],
                'is_active'=>['label'=>'Active','type'=>'boolean','rules'=>'boolean'],
            ]],
            'categories'=>['title'=>'Waste Categories','model'=>WasteCategory::class,'admin'=>true,'fields'=>[
                'local_label'=>['label'=>'Local category label','rules'=>'required|string|max:120'],
                'national_type'=>['label'=>'Classification','type'=>'enum','options'=>['residual'=>'Residual / regular','recyclable'=>'Reusable / recyclable','biodegradable'=>'Biodegradable','special'=>'Special waste','other'=>'Other'],'rules'=>'required|in:residual,recyclable,biodegradable,special,other'],
                'responsible_office'=>['label'=>'Responsible office','rules'=>'nullable|string|max:200'],
                'is_active'=>['label'=>'Active','type'=>'boolean','rules'=>'boolean'],
            ]],
            'points'=>['title'=>'Collection Points','model'=>CollectionPoint::class,'fields'=>[
                'zone_id'=>['label'=>'Collection zone','type'=>'relation','model'=>Zone::class,'display'=>'name','rules'=>'required|string'],
                'name'=>['label'=>'Point name','rules'=>'required|string|max:150'],
                'landmark'=>['label'=>'Landmark / address','rules'=>'nullable|string|max:250'],
                'instructions'=>['label'=>'Collection instructions','type'=>'textarea','rules'=>'nullable|string|max:2000'],
                'is_active'=>['label'=>'Active','type'=>'boolean','rules'=>'boolean'],
            ]],
            'schedules'=>['title'=>'Recurring Schedules','model'=>Schedule::class,'fields'=>[
                'zone_id'=>['label'=>'Zone','type'=>'relation','model'=>Zone::class,'display'=>'name','rules'=>'required|string'],
                'category_id'=>['label'=>'Waste category','type'=>'relation','model'=>WasteCategory::class,'display'=>'local_label','rules'=>'required|string'],
                'collection_point_id'=>['label'=>'Designated point (only if applicable)','type'=>'relation','model'=>CollectionPoint::class,'display'=>'name','rules'=>'nullable|string'],
                'weekday'=>['label'=>'Collection day','type'=>'enum','options'=>[0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'],'rules'=>'required|integer|between:0,6'],
                'start_time'=>['label'=>'Start time','type'=>'time','rules'=>'required|date_format:H:i'],
                'end_time'=>['label'=>'End time','type'=>'time','rules'=>'required|date_format:H:i'],
                'collection_method'=>['label'=>'Pickup method','type'=>'enum','options'=>['designated_point'=>'Designated collection point','truck_handover'=>'Meet the truck','curbside'=>'Curbside / house-front'],'rules'=>'required|in:designated_point,truck_handover,curbside'],
                'effective_from'=>['label'=>'Effective from','type'=>'date','rules'=>'required|date'],
                'effective_until'=>['label'=>'Effective until (optional)','type'=>'date','rules'=>'nullable|date|after_or_equal:effective_from'],
                'instructions'=>['label'=>'Resident instructions','type'=>'textarea','rules'=>'nullable|string|max:1500'],
                'publication_status'=>['label'=>'Publication','type'=>'enum','options'=>['draft'=>'Draft','published'=>'Published','archived'=>'Archived'],'rules'=>'required|in:draft,published,archived'],
            ]],
            'exceptions'=>['title'=>'One-Date Schedule Exceptions','model'=>ScheduleException::class,'fields'=>[
                'schedule_id'=>['label'=>'Affected schedule','type'=>'relation','model'=>Schedule::class,'display'=>'id','rules'=>'required|string'],
                'affected_date'=>['label'=>'Affected date','type'=>'date','rules'=>'required|date'],
                'action'=>['label'=>'Change type','type'=>'enum','options'=>['cancel'=>'Cancellation','time_change'=>'Time change','replacement'=>'Replacement pickup'],'rules'=>'required|in:cancel,time_change,replacement'],
                'replacement_date'=>['label'=>'Replacement date (if applicable)','type'=>'date','rules'=>'nullable|date'],
                'replacement_start_time'=>['label'=>'Replacement start (if applicable)','type'=>'time','rules'=>'nullable|date_format:H:i'],
                'replacement_end_time'=>['label'=>'Replacement end (if applicable)','type'=>'time','rules'=>'nullable|date_format:H:i'],
                'reason'=>['label'=>'Reason / instructions','type'=>'textarea','rules'=>'required|string|max:2000'],
                'publication_status'=>['label'=>'Publication','type'=>'enum','options'=>['draft'=>'Draft','published'=>'Published'],'rules'=>'required|in:draft,published'],
            ]],
            'rules'=>['title'=>'Resident Rules & Notices','model'=>LocalRule::class,'fields'=>[
                'zone_id'=>['label'=>'Specific zone (blank = all)','type'=>'relation','model'=>Zone::class,'display'=>'name','rules'=>'nullable|string'],
                'category_id'=>['label'=>'Specific category (blank = all)','type'=>'relation','model'=>WasteCategory::class,'display'=>'local_label','rules'=>'nullable|string'],
                'title'=>['label'=>'Rule title','rules'=>'required|string|max:180'],
                'content'=>['label'=>'Rule details / preparation guidelines','type'=>'textarea','rules'=>'required|string|max:8000'],
                'effective_from'=>['label'=>'Effective from','type'=>'date','rules'=>'required|date'],
                'effective_until'=>['label'=>'Effective until','type'=>'date','rules'=>'nullable|date|after_or_equal:effective_from'],
                'publication_status'=>['label'=>'Publication','type'=>'enum','options'=>['draft'=>'Draft','published'=>'Published','archived'=>'Archived'],'rules'=>'required|in:draft,published,archived'],
            ]],
            'users'=>['title'=>'Accounts & Roles','model'=>User::class,'admin'=>true,'fields'=>[
                'name'=>['label'=>'Full name','rules'=>'required|string|max:150'],
                'email'=>['label'=>'Email address','type'=>'email','rules'=>'required|email|max:190'],
                'role_id'=>['label'=>'Role','type'=>'relation','model'=>Role::class,'display'=>'name','rules'=>'required|string'],
                'zone_id'=>['label'=>'Assigned zone','type'=>'relation','model'=>Zone::class,'display'=>'name','rules'=>'nullable|string'],
                'contact_number'=>['label'=>'Contact number (optional)','rules'=>'nullable|string|max:35'],
                'password'=>['label'=>'Password (required when creating, blank to keep)','type'=>'password','rules'=>'nullable|string|min:12|max:120'],
                'is_active'=>['label'=>'Active','type'=>'boolean','rules'=>'boolean'],
            ]],
        ];
    }
    public static function get(string $key): array
    {
        abort_unless(isset(self::all()[$key]),404);
        return self::all()[$key];
    }
}
