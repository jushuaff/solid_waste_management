<?php
namespace App\Models;
class Schedule extends Document
{
    protected $table = 'schedules';

    public function zone(){return $this->belongsTo(Zone::class,'zone_id');} public function category(){return $this->belongsTo(WasteCategory::class,'category_id');} public function point(){return $this->belongsTo(CollectionPoint::class,'collection_point_id');}
}
