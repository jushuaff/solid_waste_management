<?php
namespace Database\Seeders;

use App\Models\{Role, User, Zone};
use Database\Seeders\Concerns\AllowsDemoSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    use AllowsDemoSeeding;

    /** All accounts here are fictional and have a deliberately weak local-only password. */
    public function run(): void
    {
        $this->assertDemoSeedingAllowed();

        $zones = [];
        foreach (['Demo Purok Pine','Demo Purok Ridge','Demo Purok Creek'] as $name) {
            $zones[$name] = Zone::where('name', $name)->firstOrFail();
        }

        // name, email, role, assigned zone (null means barangay-wide authority).
        $users = [
            ['Demo Superadministrator','superadmin@linis.test','superadmin',null],
            ['Demo Barangay Administrator','admin@linis.test','admin',null],
            ['Demo Assistant Administrator','admin2@linis.test','admin',null],
            ['Demo Coordinator Pine','coordinator.pine@linis.test','coordinator','Demo Purok Pine'],
            ['Demo Coordinator Ridge','coordinator.ridge@linis.test','coordinator','Demo Purok Ridge'],
            ['Demo Collection Crew Pine','crew.pine@linis.test','crew','Demo Purok Pine'],
            ['Demo Collection Crew Ridge','crew.ridge@linis.test','crew','Demo Purok Ridge'],
            ['Demo Collection Crew Creek','crew.creek@linis.test','crew','Demo Purok Creek'],
            ['Demo Eco-Aide Pine','ecoaide.pine@linis.test','eco_aide','Demo Purok Pine'],
            ['Demo Eco-Aide Ridge','ecoaide.ridge@linis.test','eco_aide','Demo Purok Ridge'],
            ['Demo Eco-Aide Creek','ecoaide.creek@linis.test','eco_aide','Demo Purok Creek'],
            ['Demo Resident Pine 1','resident.pine1@linis.test','resident','Demo Purok Pine'],
            ['Demo Resident Pine 2','resident.pine2@linis.test','resident','Demo Purok Pine'],
            ['Demo Resident Ridge 1','resident.ridge1@linis.test','resident','Demo Purok Ridge'],
            ['Demo Resident Ridge 2','resident.ridge2@linis.test','resident','Demo Purok Ridge'],
            ['Demo Resident Creek 1','resident.creek1@linis.test','resident','Demo Purok Creek'],
            ['Demo Resident Creek 2','resident.creek2@linis.test','resident','Demo Purok Creek'],
        ];

        $roles = Role::all()->keyBy('code');
        foreach ($users as [$name, $email, $role, $zone]) {
            if (! $roles->has($role)) {
                throw new \RuntimeException('Missing role: '.$role.'. Run RoleSeeder first.');
            }

            // firstOrCreate means reruns NEVER reset passwords/roles of existing users.
            // This includes any existing account that happens to use a demo email.
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('1234'),
                    'role_id' => (string) $roles[$role]->id,
                    'zone_id' => $zone ? (string) $zones[$zone]->id : null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
        $this->command?->warn('DEMO ONLY: '.count($users).' sample user identities; new accounts use password 1234. Existing accounts were not changed.');
    }
}
