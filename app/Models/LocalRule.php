<?php
namespace App\Models;
class LocalRule extends Document
{
    protected $table = 'local_rules';

    public function zone(){return $this->belongsTo(Zone::class,'zone_id');} public function category(){return $this->belongsTo(WasteCategory::class,'category_id');}
}
