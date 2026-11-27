# User Accounts & Role Guide — Barangay Solid Waste Management System

> **Start here for local MIT 103 / MIT 104 demonstrations.** The login accounts below come from `database/seeders/DemoUsersSeeder.php`; the permitted actions are based on the application's current Laravel routes, controllers and navigation. This guide describes the **online** system. Offline logging and WebSockets are not yet included.

## 1. Sample login accounts (local testing only)

**Login URL:** [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)

**Default password for all newly seeded accounts:** `password1234`

| User role | Sample account / login email | Assigned area |
| --- | --- | --- |
| **Superadministrator** | Superadministrator — `superadmin@linis.test` | All areas |
| **Barangay Administrator** | Barangay Administrator — `admin@linis.test` | All areas |
| Barangay Administrator | Assistant Administrator — `admin2@linis.test` | All areas |
| **Waste Coordinator** | Coordinator Pine — `coordinator.pine@linis.test` | Purok Pine |
| Waste Coordinator | Coordinator Ridge — `coordinator.ridge@linis.test` | Purok Ridge |
| **Collection Crew** | Collection Crew Pine — `crew.pine@linis.test` | Purok Pine |
| Collection Crew | Collection Crew Ridge — `crew.ridge@linis.test` | Purok Ridge |
| Collection Crew | Collection Crew Creek — `crew.creek@linis.test` | Purok Creek |
| **Eco-Aide / MRF Staff** | Eco-Aide Pine — `ecoaide.pine@linis.test` | Purok Pine |
| Eco-Aide / MRF Staff | Eco-Aide Ridge — `ecoaide.ridge@linis.test` | Purok Ridge |
| Eco-Aide / MRF Staff | Eco-Aide Creek — `ecoaide.creek@linis.test` | Purok Creek |
| **Resident** | Resident Pine 1 — `resident.pine1@linis.test` | Purok Pine |
| Resident | Resident Pine 2 — `resident.pine2@linis.test` | Purok Pine |
| Resident | Resident Ridge 1 — `resident.ridge1@linis.test` | Purok Ridge |
| Resident | Resident Ridge 2 — `resident.ridge2@linis.test` | Purok Ridge |
| Resident | Resident Creek 1 — `resident.creek1@linis.test` | Purok Creek |
| Resident | Resident Creek 2 — `resident.creek2@linis.test` | Purok Creek |

**Important:** These 17 fictional accounts are created only when the local demo seeders have run. `password1234` is the **initial** password; it may differ if an account holder or superadministrator already changed it. Reseeding does **not** reset manually changed passwords. These `.test` emails are not real inboxes, so password-reset email delivery must be tested separately with a mailbox you control.

**Security:** This file is stored in a GitHub repository; the listed credentials must **never** be reused for actual barangay users or an internet-accessible deployment. Keep sample accounts on an isolated local/testing instance only.

### How to generate the accounts if missing

In your local `.env`:

```dotenv
APP_ENV=local
SWM_DEMO_SEED=true
DB_CONNECTION=mongodb
MONGODB_URI=mongodb://127.0.0.1:27017
MONGODB_DATABASE=solid_waste_management
```

Then run in PowerShell:

```powershell
cd D:\Websites\solid_waste_management
php artisan config:clear
php artisan migrate
php artisan db:seed --class=DemoDataSeeder
php artisan serve --host=127.0.0.1
```

The seeder is intentionally blocked outside `local` and `testing` or when `SWM_DEMO_SEED` is disabled. **Do not run `migrate:fresh`, `db:wipe`, or delete collections to reseed.** The original production-safe seeders remain in the project. See [the seeder reference](database/seeders/README.md) for details.

---

## 2. What each role does

### A. Superadministrator (`superadmin`)

**Purpose:** Overall technical and organizational oversight of the application.

- View and maintain **all user accounts**, including other superadministrators.
- Create users; assign or change roles, collection zones and active/inactive status.
- Change another user's password through **User Accounts → Change password**; confirm the acting superadministrator's own password. Change their own password via **My Profile**.
- Configure collection zones/puroks, waste categories, collection points, recurring schedules, schedule exceptions and published local rules.
- Review incident reports and PDF summaries; manage their status and crew assignments.
- Access household records, pickup inspections, MRF weigh-ins, compliance metrics and monthly PDF monitoring summaries.
- Receive administrative notifications about reports requiring attention.

**Boundary:** Uses the same resident-report workflow rules and data validation as other authorized staff; broad privileges are not a substitute for audit and security review.

### B. Barangay Administrator (`admin`)

**Purpose:** Manage regular barangay operations and personnel.

- Create, edit and deactivate **non-superadministrator** accounts; assign ordinary roles and zones.
- Manage collection zones, waste categories, collection points, schedules, exceptions and local rules.
- Review reported collection/disposal incidents, assign or reassign crews, reject or close cases, and review report PDF outputs.
- View/manage household records, inspections, MRF weigh-ins and the monitoring dashboard; download monthly monitoring PDFs.
- Receive new-incident and operational notifications.

**Restrictions:**
- **Cannot see superadministrator accounts** in Users listing, search or pagination.
- Cannot directly edit/delete a superadministrator account by URL, assign the superadministrator role, or access the superadministrator-only Change Password action.

### C. Waste Coordinator (`coordinator`)

**Purpose:** Coordinate collection schedules, reported incidents and crew work for a collection area.

- Manage and publish recurring collection schedules, schedule changes, local rules and collection points.
- Review incident reports in the assigned zone; verify concerns, assign/reassign collection crew, reject invalid reports or close eligible cases.
- Read/create/update household registry records; enter or review pickup inspections and MRF weigh-ins.
- View the segregation/compliance monitoring dashboard and monthly PDF report.
- Receive alerts and updates relevant to operational follow-up.

**Area:** Demo coordinators are assigned to **Pine** or **Ridge**. **Current limitation:** some older management/configuration paths still need consistent zone-scoped authorization tests and enforcement; do not assume every management CRUD screen is properly isolated yet.

### D. Collection Crew (`crew`)

**Purpose:** Execute assigned pickup follow-ups and record field outcomes.

- View incident tasks assigned to their individual crew account.
- Start work on assigned cases and record collection **Resolved** or **Exception** outcomes; these change the report's status/history.
- Record pickup/segregation inspection observations through the online inspection module.
- Read household reference information for their permitted zone.
- View collection schedules, local guidelines and notifications about new assignments.

**Restrictions:** Cannot manage user accounts, change barangay settings, record MRF weights, or directly assign/reassign/reject/close administrative cases. The crew has no LINIS compliance-dashboard link. Ordinary crew staff should not modify other workers' inspection records; that narrower write-permission rule remains an identified testing/hardening item.

### E. Eco-Aide / MRF Staff (`eco_aide`)

**Purpose:** Monitor segregation at collection and record materials recovery measurements.

- Record pickup inspections by zone, optionally referencing a household and attaching inspection evidence.
- Record MRF weigh-ins by waste category, received kilograms and diverted kilograms.
- View their assigned-zone household reference list (read-only).
- View segregation and recorded waste-diversion statistics in the **Compliance Dashboard**.
- Consult schedules/rules and receive monitoring notifications.

**Restrictions:** Does **not** access private resident incident cases or crew-assignment controls. Cannot manage users, zones or published schedules/rules. The module currently permits some same-zone editing by other authorized field staff; ownership-specific authorization still needs hardening.

### F. Resident (`resident`)

**Purpose:** Stay informed about local waste rules and follow up on service problems.

- View published schedules showing collection day/time, waste classification, pickup method and designated locations where applicable.
- Read the published local rules and special schedule changes.
- Report a **missed collection** or **illegal dumping** concern, with location details and optional map pin.
- Attach photos/videos as evidence (up to 10 images totaling 10 MiB and up to 3 video clips totaling 15 MiB).
- Receive a tracking reference, view **their own** incident/status history and download their permitted report PDF.
- Amend and add/remove attachments to their own report **only while it is awaiting review**.
- Open notifications about case progress, schedules and rules; update their own profile/password.

**Restrictions:** Cannot view another resident's private report/evidence; cannot assign crew, process reports, or modify official schedule and inspection records.

---

## 3. Access overview

Legend: **Manage** = read and create/update where the module permits; **View** = read only; **Own/Assigned** = limited by owner or assigned task; **—** = not provided by that role. Permissions are implemented across routes/controllers, **not all yet confirmed by a complete test suite**.

| Module | Superadmin | Barangay Admin | Coordinator | Crew | Eco-Aide | Resident |
| --- | --- | --- | --- | --- | --- | --- |
| Users and roles | Manage all | Manage except superadmin | — | — | — | Own profile |
| Zones and waste categories | Manage | Manage | — | — | — | — |
| Schedules, exceptions, collection points, rules | Manage | Manage | Manage¹ | View published | View published | View published |
| Incident reports and evidence | Manage | Manage | Assigned-zone review | Assigned cases | — | Own cases |
| Crew assignment / incident status | Manage | Manage | Manage in zone | Assigned outcome only | — | Track own |
| Household registry | Manage | Manage | Manage | View zone | View zone | — |
| Pickup inspections | Manage | Manage | Manage | Manage in zone² | Manage in zone² | — |
| MRF weigh-ins | Manage | Manage | Manage | — | Manage in zone² | — |
| Compliance dashboard | View | View | View zone | — | View zone | — |
| Monthly LINIS PDF | Download | Download | Download | — | — | — |
| Notifications | Own inbox | Own inbox | Own inbox | Own inbox | Own inbox | Own inbox |

¹ Some coordinator configuration routes have unresolved zone-scope gaps; keep this a test item before live deployment.  
² Inspection and MRF edit ownership is not fully narrowed to the original recorder. This is a known permission-hardening item.

---

## 4. Suggested role-by-role demonstration

1. **Resident:** Sign in as `resident.pine1@linis.test`. View the Pine collection schedule and rules, submit an incident with supporting evidence, and note its tracking reference.
2. **Coordinator:** Sign in as `coordinator.pine@linis.test`. Review the Pine report and assign the case to the Pine collection crew.
3. **Collection Crew:** Sign in as `crew.pine@linis.test`. Open the assignment and record its progress and outcome.
4. **Resident again:** Return to the Pine resident account to see the updated case status and unread notification dot; open the notification to clear it.
5. **Eco-Aide:** Sign in as `ecoaide.pine@linis.test`. Add a pickup inspection and MRF weigh-in; check the assigned-zone compliance dashboard.
6. **Barangay Administrator:** Sign in as `admin@linis.test`. Review operations and the monthly PDF; check User Accounts to verify the superadministrator is absent.
7. **Superadministrator:** Sign in as `superadmin@linis.test`. Confirm full account visibility and the separate password-change action.

For each step, sign out before switching to the next account or use separate browser profiles. The seeded records are **fictional**, intended for development demonstrations only.

### Resident online payment submissions

Residents can now submit **proof of an external GCash/bank/e-wallet transfer** for their own billed household month. They enter amount, date and transaction reference, choose the approved receiving account and upload JPG/PNG/WebP/PDF evidence (up to 5 MB). The submission is **Pending Verification** and their ledger balance remains unpaid until reviewed.

**Barangay admins/superadmins** have a dedicated **Verify Online Payments** queue. Only these roles may verify funds received and credit the household balance, or reject the proof with a reason. They alone may record a separate cash payment received at the office; residents cannot mark themselves paid. Coordinators may review zone-scoped payment history but cannot credit payments or approve receipts. See [full pending-payment workflow](docs/GARBAGE_FEE_NOTIFICATIONS_TRACKER.md).

### Payment tracking and reminders

**Payment History** (staff) and **My Payment Tracker** (residents) show Paid, Partial and Unpaid billing months. Barangay admins and superadmins may filter household arrears across billing months; coordinators are limited to their assigned zone. Residents see only their linked household's bills. Only issued monthly charges count as unpaid.

When staff record a verified payment, the linked resident receives a confirmation/fully-paid notification; oversight staff also receive role-appropriate alerts. The `fees:send-reminders` scheduled command sends deduplicated reminders on billed but unpaid balances after the 10th and 20th of each month, plus a monthly reminder for prior months.

Superadmins can add a missing **bank or e-wallet provider** to the receiving-account dropdown; barangay admins can use the provider list and edit authorized receiving accounts. See [fee tracker and provider guide](docs/GARBAGE_FEE_NOTIFICATIONS_TRACKER.md).

## Phase 2 — Garbage fees and payment destinations

The `Garbage Fee Payments` module is separate from RA 9003 compliance reporting. It is currently an **online payment-tracking module** for offline-confirmed Cash, GCash, bank and external card-terminal payments, not an online checkout.

| Role | Garbage-fee access |
| --- | --- |
| Superadministrator | Create city rates/ordinance references, assign zones, generate monthly charges, configure receiving accounts/QR, record payments and reverse erroneous entries with reasons |
| Barangay Administrator | Manage active GCash and bank receiving accounts with optional QR, generate charges from configured rates and record verified payments; cannot change city rates |
| Coordinator | Review and record confirmed payments for assigned-zone households only |
| Resident | View fees and payments for linked household only; see approved receiving account name/number and optional QR code for their city |
| Crew / Eco-Aide | No access to household payment history or receiving-account settings |

A staff member links residents through **Household Registry → Edit → Linked resident account**. Fee account changes create relevant resident notifications. Accounts are privately served, and payments are never auto-confirmed from a QR scan. See [full CR-01 setup and safeguards](docs/GARBAGE_FEES_PHASE2.md).

## 5. Notifications and reports

- A **small dot on the bell and Notifications menu** indicates unread items. Opening an item marks it as read and routes the user to the relevant record or page; the dot disappears when no unread notices remain.
- This is **page-refresh-based** notification behavior. Real-time WebSocket push is deferred.
- Individual incident reports, filtered summaries, collection schedules and monthly LINIS monitoring reports offer **Preview PDF** and **Download PDF** where the user's role is authorized.
- LINIS monitoring data are based on recorded inspections and MRF weights; the generated summary is **not a certified RA 9003 statutory report** without verification against the adopting LGU's approved format.

## 6. Scope and implementation notes

- **MIT 103:** Collection services, zone/category schedules, collection methods, rules and exceptions, incident reporting/tracking, review and crew workflow.
- **MIT 104:** Households/puroks, eco-aide inspections, MRF logs, segregation/diversion monitoring, scheduled alerts and monthly reports.
- **Online first:** Offline/PWA queue and WebSocket features are deferred.
- **Known outstanding work:** Independent local MongoDB CRUD/role tests, coordinator-zone permission hardening in older management paths, ownership-specific inspection editing controls, official PDF-format validation, and notification delivery testing.
- Further details: [MIT 103/104 checklist](MIT103-104.MD), [main setup README](README.md), and [demo seeders](database/seeders/README.md).

*Document reflects source-level behavior and seeded account definitions; it is not proof that every feature has passed end-to-end testing.*
