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
        foreach (['Demo Purok Pine'=>'pine','Demo Purok Ridge'=>'ridge','Demo Purok Creek'=>'creek'] as $name=>$key) {
            $zones[$name] = Zone::where('demo_key','LINIS-DEMO-ZONE-'.$key)->firstOrFail();
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
        $newCount = 0;
        $upgradedCount = 0;
        foreach ($users as [$name, $email, $role, $zone]) {
            if (! $roles->has($role)) {
                throw new \RuntimeException('Missing role: '.$role.'. Run RoleSeeder first.');
            }

            // Preserve existing passwords and roles on repeat runs. Only previously
            // created fictional demo users STILL using the old 1234 are upgraded.
            $account = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => preg_replace('/^Demo /','',$name),
                    'password' => Hash::make('password1234'),
                    'role_id' => (string) $roles[$role]->id,
                    'zone_id' => $zone ? (string) $zones[$zone]->id : null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            if ($account->wasRecentlyCreated) {
                $newCount++;
                continue;
            }

            // Old fixture migration: do NOT change a real account sharing the email,
            // or a demo account whose password was deliberately changed by the user.
            $displayName=preg_replace('/^Demo /','',$name);
            $isExpectedDemo = in_array((string)$account->name,[$name,$displayName],true)
                && (string)$account->role_id === (string)$roles[$role]->id
                && (string)($account->zone_id ?? '') === ($zone ? (string)$zones[$zone]->id : '');
            if ($isExpectedDemo && Hash::check('1234', (string)$account->password)) {
                $account->password=Hash::make('password1234');
                $upgradedCount++;
            }
            if ($isExpectedDemo && (string)$account->name===$name) {
                $account->name=$displayName;
            }
            if ($account->isDirty()) $account->save();
        }
        $this->command?->warn('DEMO ONLY: '.count($users).' fictional identities, '.$newCount.' newly created and '.$upgradedCount.' upgraded from the old 1234 password to password1234. Other existing accounts were untouched.');
    }
}
