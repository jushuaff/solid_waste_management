<?php
namespace App\Models;
class Notice extends Document
{
    protected $table = 'notifications';
    const UPDATED_AT = null;
    public function recipient(){return $this->belongsTo(User::class,'recipient_user_id');}
}
