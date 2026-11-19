<?php
namespace Database\Seeders;
use App\Models\{Role,User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email=strtolower((string)env('INITIAL_ADMIN_EMAIL','admin@example.com'));
        $password=(string)env('INITIAL_ADMIN_PASSWORD','');
        if(strlen($password)<12 || $password==='password123' || $password==='CHOOSE-A-STRONG-UNIQUE-PASSWORD'){
            throw new \RuntimeException('Set a unique INITIAL_ADMIN_PASSWORD of at least 12 characters in .env before seeding.');
        }
        if(!User::where('email',$email)->exists()){
            User::create([
                'name'=>(string)env('INITIAL_ADMIN_NAME','System Administrator'),
                'email'=>$email,'password'=>Hash::make($password),
                'role_id'=>(string)Role::where('code','superadmin')->firstOrFail()->id,
                'is_active'=>true,'email_verified_at'=>now(),
            ]);
        }
    }
}
