<?php
namespace App\Models;
class StatusHistory extends Document
{
    protected $casts = ['changed_at'=>'datetime'];
    protected $table = 'status_histories';


}
