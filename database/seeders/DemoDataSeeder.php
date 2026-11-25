<?php
namespace Database\Seeders;

use Database\Seeders\Concerns\AllowsDemoSeeding;
use Illuminate\Database\Seeder;

/**
 * Repeatable fictional data for combined MIT 103 / MIT 104 online demonstrations.
 * No truncates, drops, password resets, or deletes. Never use on real resident data.
 */
class DemoDataSeeder extends Seeder
{
    use AllowsDemoSeeding;

    public function run(): void
    {
        $this->assertDemoSeedingAllowed();

        $this->call([
            RoleSeeder::class,
            DemoConfigurationSeeder::class,
            DemoUsersSeeder::class,
            DemoOperationsSeeder::class,
        ]);

        $this->command?->warn('Demo seed complete. Test logins use password 1234 ONLY in a protected local environment.');
    }
}
