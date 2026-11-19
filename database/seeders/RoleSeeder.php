<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Role;
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superadmin'=>'Super Administrator','admin'=>'Barangay Administrator','coordinator'=>'Waste Coordinator','crew'=>'Collection Crew','resident'=>'Resident'] as $code=>$name) {
            Role::firstOrCreate(['code'=>$code],['name'=>$name]);
        }
    }
}
