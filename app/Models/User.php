<?php
namespace App\Models;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;
class User extends Authenticatable
{
    use Notifiable;
    protected $connection = 'mongodb';
    protected $table = 'users';
    protected $fillable = ['name','email','password','role_id','zone_id','contact_number','is_active','email_verified_at'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['password'=>'hashed','is_active'=>'boolean','email_verified_at'=>'datetime'];
    public function role(){ return $this->belongsTo(Role::class,'role_id'); }
    public function zone(){ return $this->belongsTo(Zone::class,'zone_id'); }
    public function getRoleCodeAttribute(): string { return $this->role?->code ?? 'resident'; }
    public function canManage(): bool {return in_array($this->role_code,['superadmin','admin','coordinator'],true);}
    public function canAdmin(): bool {return in_array($this->role_code,['superadmin','admin'],true);}
}
