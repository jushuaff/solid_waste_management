<?php
return [
 'demo_seed_enabled'=>filter_var(env('SWM_DEMO_SEED',false),FILTER_VALIDATE_BOOL),
 'max_report_age_days'=>(int)env('REPORT_MAX_AGE_DAYS',30),
 'mongo_transactions'=>(bool)env('MONGODB_TRANSACTIONS',false),
 'roles'=>['superadmin','admin','coordinator','crew','eco_aide','resident'],
 'collection_methods'=>['designated_point'=>'Designated collection point','truck_handover'=>'Meet the collection truck','curbside'=>'Curbside / house-front pickup'],
 'days'=>[0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'],
 'report_statuses'=>['Submitted','Under Review','Verified','Rejected','Assigned','In Progress','Resolved','Exception','Closed'],
];
