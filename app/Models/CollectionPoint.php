<?php
namespace App\Models;
class CollectionPoint extends Document
{
    protected $table = 'collection_points';

    public function zone(){return $this->belongsTo(Zone::class,'zone_id');}
}
