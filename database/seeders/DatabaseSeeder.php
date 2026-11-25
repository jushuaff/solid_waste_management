<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Existing seeders are intentionally retained for normal installations.
        $this->call([RoleSeeder::class,InitialAdminSeeder::class,ReferenceDataSeeder::class]);

        // Demo data is opt-in, and DemoDataSeeder independently checks local/testing.
        if (config('swm.demo_seed_enabled', false)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
