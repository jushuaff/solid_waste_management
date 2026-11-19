<?php
namespace App\Models;
class PasswordResetToken extends Document
{
    protected $table = 'password_reset_tokens';
    protected $timestamps = false;
    protected $casts=['expires_at'=>'datetime'];

}
