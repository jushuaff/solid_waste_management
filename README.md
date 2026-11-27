# Barangay Solid Waste Collection & Monitoring System

**Laravel 13 · MongoDB (`mongodb/laravel-mongodb` 5.12+) · Blade · PHP 8.3+**

A configurable Philippine barangay waste-management application based on the MIT 103 research and the expanded HTML design prototype. The frontend uses the same dark-teal sidebar, white content cards, green accents, responsive layouts and resident/staff workflow as the provided prototype. **It is a real Laravel application, not the standalone HTML demo.**

> **Status:** Source-complete development candidate, *not yet production-certified*. The PHP files were syntax-checked in the preparation environment; full dependency installation, MongoDB integration, email delivery and browser end-to-end testing **must be run on your machine** because Composer, the MongoDB PHP extension and a running MongoDB server were unavailable in the build environment. Do not process real residents' information before these checks, organizational approval and a privacy review.

## Technical choices

- **One deployment per adopting barangay.** Zones, collection points, time windows, accepted categories and local rules are configurable in the application. This is **not** yet a hosted nationwide multi-tenant platform; separate deployments isolate each barangay's citizen data.
- MongoDB database: `solid_waste_management`. The existing `users` collection is preserved by the migration.
- Server-rendered **Blade** views with a refined version of the existing prototype's CSS, requiring no Node frontend build step.
- Session-based Laravel authentication, CSRF protection, `throttle` on authentication and reporting, password hashing, authorization by role and report ownership, in-app notifications, and email password reset.
- No WebSockets; server-refresh notifications and database-driven activity history.

## MIT 103 + MIT 104 (online-first LINIS)

The same Laravel and MongoDB deployment now includes both MIT 103 collection scheduling/report tracking and MIT 104 online eco-aide inspections, household registry, MRF weigh-ins, data-derived segregation and diversion metrics, scheduled alerts, and browser-printable monthly monitoring summaries. Official RA 9003 compliance reporting, end-to-end tests, and live deployment are **not verified**. Offline PWA, offline synchronization, and WebSockets are explicitly deferred.

- **Implementation checklist:** [MIT103-104.MD](MIT103-104.MD)
- **Windows PHP 8.4 / XAMPP setup:** [docs/PHP84_WINDOWS_SETUP.md](docs/PHP84_WINDOWS_SETUP.md)
- **Staff views:** `/linis/dashboard`, `/linis/households`, `/linis/inspections`, `/linis/weigh-ins`
- **Monthly summary:** `/linis/monthly-report?month=2026-11` (browser Print / Save as PDF)
- **Online alert command:** `php artisan linis:check-alerts --dry-run`; then configure `php artisan schedule:run` every minute to run the hourly command.
- **Eco-aide role:** rerun `php artisan db:seed --class=RoleSeeder` after migration and assign active eco-aide accounts and zones from the administrator UI.

The implementation is source-complete at the module level but not a certified, deployed system. Back up the MongoDB database and run integration and authorization tests before live use.

## Development-only demo seeders for MIT 103/104

The original seeders (roles, initial strong-password administrator and reference categories) remain in source control. An additional **opt-in** `DemoDataSeeder` creates **17 fictional role accounts**, three zones, nine schedules, local rules, households, inspection records, MRF weigh-ins and sample incident histories. Every newly created demo account initially has password **`password1234`**, as requested for **local testing only**.

See [database/seeders/README.md](database/seeders/README.md) for the complete test-account list and safeguard instructions. Existing data remain intact; known old demo passwords `1234` upgrade to `password1234` while independently changed passwords stay untouched.

```dotenv
APP_ENV=local
SWM_DEMO_SEED=true
```

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
```

The demo seeder refuses to run outside the `local`/`testing` environment or without the explicit flag. It does not delete any collections. The existing `InitialAdminSeeder` still requires a strong, independently configured password. Never use these demonstration accounts on a public deployment.

## Real PDF previews and downloads (MIT 103 / MIT 104)

Reports now use server-rendered PDFs with [barryvdh/laravel-dompdf](https://packagist.org/packages/barryvdh/laravel-dompdf) `^3.1.2` (Laravel 13 compatible). No npm or browser-only Print-to-PDF step is required.

* **Preview PDF** streams a genuine PDF with `Content-Disposition: inline` and opens the browser's native PDF viewer in a new tab.
* **Download PDF** returns `Content-Disposition: attachment` for a saved `.pdf` file.
* PDF links are available on the individual report detail, filtered case summary, published collection schedules and LINIS monthly monitoring dashboard.
* The `download=1` query parameter chooses attachment; existing authenticated routes and filters are preserved. PDF responses have private no-store cache headers. The report route enforces ownership and coordinator-zone restrictions and denies eco-aide access to resident reports.
* PDF rendering uses local files and disabled remote loading; a purpose-built DomPDF layout replaces the earlier HTML/JavaScript print toolbar.
* Monthly monitoring PDFs are still **academic prototype summaries**, not verified government-approved RA 9003 submission forms.

After pulling the new code, install the added Composer dependency:

```powershell
git pull origin main
# If a composer.lock already exists locally:
composer update barryvdh/laravel-dompdf --with-all-dependencies
# Otherwise (no composer.lock), use: composer install
php artisan optimize:clear
```

The Composer lockfile should be committed once resolved on the development machine, for reproducible deployments. If Composer reports missing PHP `ext-gd` or `ext-mbstring`, enable the proper PHP 8.4 modules in `php.ini` (do not disable TLS or ignore platform requirements).

**Superadministrator account password management:** User Accounts now has a separate **Change password** action (except your own account, which uses My Profile). Only the superadministrator may reset another user's password. It requires confirmation of the acting superadministrator's current password and a 12-character minimum for the new one; ordinary user-edit forms no longer change passwords.

**Unverified:** Run browser and authorization tests with local MongoDB before sharing case PDFs or administrator tools outside the development environment.

## Application modules

| Module | What works in the source |
|---|---|
| Authentication | Resident registration, login, logout, role-aware sidebar, profile edit, password update, forgot-password SMTP email |
| Accounts | Admin CRUD for users, assign roles and zones, activate/deactivate users |
| Zones & waste categories | Admin CRUD, reusable local classifications and responsible offices |
| Collection points | CRUD with landmarks and resident collection instructions |
| Recurring schedules | CRUD, multiple weekday times, zone and category, designated point / meet-the-truck / curbside, effective dates, draft/publish/archive |
| Schedule exceptions | CRUD, cancellation, time change and replacement schedules, published notices |
| Local rules | CRUD, zone/category scoping, effective dates, revision, draft/publish/archive, resident guide |
| Missed pickups | Resident submit with optional private photo, tracking reference, report list, status history, coordinator verification and assignment, crew start/resolve/exception, coordinator reassignment or closure |
| Notifications | Database records and read/unread control; schedule/rule/exception publication notices and report-status notices |
| Printing | Print/save as PDF via browser for published collection schedules, filtered report summaries and individual report history |

For public accountability, a filed case **cannot be permanently deleted through the user interface** after submission. Instead it progresses to Rejected, Resolved, or Closed with a reason and history. Referenced or published configuration items are also protected from destructive deletion; they can be deactivated, archived or revised.

## Prerequisites (Windows or Linux)

1. PHP **8.3 or later**, with `ext-mongodb` (current compatible version), `mbstring`, `openssl`, `fileinfo`, and other Laravel required extensions.
2. Composer 2.
3. MongoDB Community Server or MongoDB Atlas; ensure the MongoDB user has collection/index permissions.
4. SMTP credentials if you want real forgot-password mail (Mailpit/log transport works for development).
5. A copy of the repository `jushuaff/solid_waste_management`, or this archive extracted into a new empty directory.

### 1. Clone the source repository

The application source is maintained in GitHub. Clone the repository or pull the latest version:

```powershell
git clone https://github.com/jushuaff/solid_waste_management.git
cd solid_waste_management
```

If already cloned, run `git pull origin main` before installing dependencies.

### 2. Install dependencies and set the environment

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` (never commit it):

```dotenv
APP_NAME="Barangay Solid Waste Management"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=solid_waste_management
MONGODB_TRANSACTIONS=false

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="Barangay SWM"

INITIAL_ADMIN_NAME="System Administrator"
INITIAL_ADMIN_EMAIL="admin@example.com"
INITIAL_ADMIN_PASSWORD="CHOOSE-A-STRONG-UNIQUE-PASSWORD"
```

**Do not use the example password in a real deployment.** Set a different password with at least 12 characters. For real email, change `MAIL_MAILER=smtp`, and fill `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_SCHEME` in your local `.env`. The default mail `log` transport writes password-reset links to `storage/logs/laravel-*.log` for local testing only.

### 3. Create MongoDB collections/indexes, then seed roles/admin

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan route:list
php artisan serve
```

Browse **http://127.0.0.1:8000/login**. Log in using your `INITIAL_ADMIN_EMAIL` and `INITIAL_ADMIN_PASSWORD`, then set up zones, categories, points, schedules, publication notices and accounts. Staff roles are admin, coordinator, crew, resident, superadmin.

**Existing `users` collection:** The migration checks for collections before creating them and does not overwrite existing `users` data or delete it in `down()`. You should back up that collection and inspect its document shape. Legacy user documents lacking `role_id`, `name`, `email` or hashed `password` may require careful mapping; migrations do **not** silently alter or delete them. Indexes on existing collections are not forced automatically because they can fail if legacy values are duplicated.

### 4. First manual acceptance walkthrough

1. Sign in as superadmin; create a real zone, waste categories and collection points.
2. Create three schedules for different days, time windows and categories; publish them.
3. Add and publish local rules and a one-date schedule exception. Verify they appear in the resident guide.
4. Create crew and coordinator accounts, assigning them to the zone.
5. Register a resident, select their zone and submit a missed-collection report.
6. Sign in as coordinator; review the report, verify and assign it to crew.
7. Sign in as crew; start work and submit Resolved or Exception with an explanation.
8. For Exception, sign in as coordinator; reassign or close with a documented reason.
9. Sign in as resident; see only their own reports and history.
10. Print the per-report record and the filtered summary; use browser “Save as PDF”.
11. Test forgot-password email, invalid login throttling, file-upload limits and unauthorized role access.

### Safety and deployment

The local defaults are **not production settings**. Before real data is used:

- Switch `.env` to `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`; use real verified SMTP and a strong APP_KEY.
- Use TLS and HTTPS end-to-end. Only expose Laravel's `public/` as the web document root, never the repository root.
- Protect backups, environment variables and logs; establish data retention, incident reporting and staff access policies consistent with RA 10173.
- Deploy MongoDB as a **replica set or Atlas cluster** and then enable `MONGODB_TRANSACTIONS=true` for atomic multi-document state/history/notification updates. Standalone MongoDB is supported for prototype development but offers weaker cross-document consistency.
- Configure daily backups, recovery tests, trusted admin accounts and least-privilege database credentials.
- Inspect existing MongoDB indexes and enforce a unique `email` index after verifying there are no duplicates in the pre-existing `users` collection.
- Set up a queue worker if email or resident notifications are later sent asynchronously. Current default is `QUEUE_CONNECTION=sync`.
- Review actual municipality/barangay schedule and waste collection procedures; sample data must not be represented as official.
- Only authorized users should download private photos. Validate consent/other legal basis, retention and deletion policies before accepting citizen evidence.

### Nginx example (Ubuntu)

```nginx
server {
    listen 80;
    server_name swm.example.org;
    root /var/www/solid_waste_management/public;
    index index.php;
    add_header X-Content-Type-Options nosniff always;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock; # Adjust to the server PHP version
    }
    location ~ /\. { deny all; }
}
```

**Configure HTTPS/SSL before use**, then run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Code style and tests

```powershell
php artisan route:list
php artisan test
vendor\bin\pint
```

The bundled unit test checks legal/illegal status transitions. **It does not substitute for MongoDB integration tests or a usability study.** MongoDB and SMTP integration tests must be run against a non-production instance before launch.

### Repository push (after you verify locally)

```powershell
git status -sb
git add .
git commit -m "Initialize Laravel MongoDB barangay waste management application"
git push origin main
```

Do not add `.env`, `vendor/`, or personal data files to Git. The `.gitignore` supplied in this package excludes them.

## Future update: WebSockets (not integrated)

A future release may use **Laravel Reverb** for server-sent notifications to resident and coordinator interfaces (schedule changes, report review, crew progress). Add authorization for private channels and events `ReportStatusUpdated`, `SchedulePublished` and `TaskAssigned`; keep persistence/auditing in MongoDB before broadcasting. Since this is intentionally deferred, no Reverb/Pusher dependencies or `REVERB_*` credentials are included now.

## Research traceability and UI reference

The prototype `design-reference/Barangay_SWM_Dynamic_Collection_Prototype_EXPANDED.html` is included solely as a **design reference**; it is not exposed through the production routes. The Blade application follows the researched scenarios: zone-specific multi-day schedules, time windows, three collection methods, local rules, exceptions, report lifecycle and printable summaries.

## Limitations requiring additional implementation or validation

- No SMS/WhatsApp delivery or WebSocket broadcast (deliberately excluded).
- No citywide GPS route optimization, waste weighing or IoT sensors.
- No nationwide multi-tenant management: deploy and configure an instance per adopting barangay.
- In-app updates are server-rendered; refresh the page to see new status changes.
- Browser print / Save as PDF is supported; server-generated PDF files are not bundled.
- Field data collection, official deployment approval, accessibility audit, load test and production backup/recovery exercises remain pending.

### Controlled report record changes

Residents may **amend** their reports only while `Under Review`; the previous and changed fields are retained in the report's embedded `amendments` history. Administrators may **archive** finalized cases (Rejected, Resolved, Closed), but cannot permanently erase them from the UI. These controls preserve the audit trail while providing practical create/read/update/archive operations.
