# LINIS / MIT 103–104: Permanent LOCAL Development Seeders

These files remain under `database/seeders/` in source control; **none are deleted**. Demo data are opt-in and intended solely for a local, fictional classroom demonstration.

## Security boundary

**All 17 fictional demo users are initially created with the requested password `password1234`.** That password is deliberately insecure. The demo seeders refuse to run unless BOTH conditions are true:

1. `APP_ENV=local` or `APP_ENV=testing`
2. `SWM_DEMO_SEED=true`

Do not enable demo seeding on a shared server or expose `php artisan serve` to the public network. Keep `APP_URL=http://127.0.0.1:8000`. Never import these weak-password accounts into a live system with real people. **`InitialAdminSeeder.php` remains unchanged:** its real initial administrator requires a strong password of at least 12 characters; it is separate from the fictional `superadmin@linis.test` account.

## Run from Windows PowerShell

In `D:\Websites\solid_waste_management\.env`, set:

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=solid_waste_management
SWM_DEMO_SEED=true
```

Then:

```powershell
cd D:\Websites\solid_waste_management
git pull origin main
composer install
php artisan config:clear
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
php artisan serve --host=127.0.0.1
```

You can also run `php artisan db:seed` if your normal `INITIAL_ADMIN_PASSWORD` is a valid strong password; it will call the original production-safe seeders **and** the opt-in demo seeder. Running `DemoDataSeeder` directly does not depend on the separate secure initial-admin seed.

When demo data are no longer wanted, set `SWM_DEMO_SEED=false` and run `php artisan config:clear`. This **disables future demo seeding but does not delete existing records**. Review and remove demo records manually in a safe, verified environment before any live deployment.

## Seeded test accounts (all default password: `password1234`)

| Role | Name / email | Purok |
|---|---|---|
| Superadministrator | Demo Superadministrator — `superadmin@linis.test` | All |
| Barangay admin | Demo Barangay Administrator — `admin@linis.test` | All |
| Barangay admin | Demo Assistant Administrator — `admin2@linis.test` | All |
| Coordinator | Demo Coordinator Pine — `coordinator.pine@linis.test` | Pine |
| Coordinator | Demo Coordinator Ridge — `coordinator.ridge@linis.test` | Ridge |
| Crew | Demo Collection Crew Pine — `crew.pine@linis.test` | Pine |
| Crew | Demo Collection Crew Ridge — `crew.ridge@linis.test` | Ridge |
| Crew | Demo Collection Crew Creek — `crew.creek@linis.test` | Creek |
| Eco-aide / MRF | Demo Eco-Aide Pine — `ecoaide.pine@linis.test` | Pine |
| Eco-aide / MRF | Demo Eco-Aide Ridge — `ecoaide.ridge@linis.test` | Ridge |
| Eco-aide / MRF | Demo Eco-Aide Creek — `ecoaide.creek@linis.test` | Creek |
| Resident | Demo Resident Pine 1 — `resident.pine1@linis.test` | Pine |
| Resident | Demo Resident Pine 2 — `resident.pine2@linis.test` | Pine |
| Resident | Demo Resident Ridge 1 — `resident.ridge1@linis.test` | Ridge |
| Resident | Demo Resident Ridge 2 — `resident.ridge2@linis.test` | Ridge |
| Resident | Demo Resident Creek 1 — `resident.creek1@linis.test` | Creek |
| Resident | Demo Resident Creek 2 — `resident.creek2@linis.test` | Creek |

These emails use the reserved `.test` domain; they do not receive real email. Forgot-password SMTP delivery should be tested separately using an address/mailbox you control.

## Fictional sample records

| Collection / module | Approximate demo records |
|---|---:|
| Roles | 6 role definitions (original RoleSeeder retained) |
| Users | 17 fictional accounts |
| Zones / puroks | 3 |
| Waste categories | 3 original reference categories |
| Collection points | 3 |
| Published recurring schedules | 9 (different days, times and 3 collection methods) |
| Schedule exceptions | 1 |
| Published local rules | 4 |
| Households | 18 fictional references |
| Eco-aide pickup inspections | 45 (15 per week across three sample weeks) |
| MRF weigh-ins | 18 (two month periods) |
| Resident case reports | 8 different statuses, including fictional dumping |
| Case status histories | Matching status transitions for all 8 sample cases |
| Crew assignments | Matching records for cases that reached Assigned |
| Notifications | At least 8 resident demo notices |

Sample schedules are **not official Baguio or other Philippine collection arrangements**.

The sample Ridge inspection rate is intentionally below 60% for the last two *completed* weeks, allowing the alert command to be tested:

```powershell
php artisan linis:check-alerts --dry-run
```

For actual notices, the scheduler must be configured and running (see the root README). These command outputs have **not** been verified against your machine yet.

## Repeatability and protection

- Seeders use `firstOrCreate` with deterministic `demo_key` markers or fictional email addresses.
- Rerunning them **does not reset user-changed passwords, overwrite edited demo records or delete anything**. Verified demo users still using the previous `1234` are automatically upgraded to `password1234`.
- New weekly inspection/monthly weigh-in periods may add new date-scoped examples when run at a later date; existing sample records stay untouched.
- Do **not** run `db:wipe`, `migrate:fresh`, or manual collection drops to reseed.
- These are development-only fixtures; regulatory reporting metrics and user permission boundaries still require integration testing.
