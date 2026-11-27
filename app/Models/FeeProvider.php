<?php
namespace App\Models;

class FeeProvider extends Document
{
    protected $table='fee_providers';
    protected $casts=['is_active'=>'boolean'];
}
