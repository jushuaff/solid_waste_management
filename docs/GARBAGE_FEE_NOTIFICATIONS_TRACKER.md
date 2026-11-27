# Garbage-fee notifications, monthly tracking and receiving institutions

## Payment status and notifications

The ledger is a **staff-confirmed payment tracker**, not a GCash/card checkout. Residents cannot click "Pay now" and create an automatically confirmed payment.

| Event | Who is notified | When |
| --- | --- | --- |
| Monthly charge generated | Linked household resident | When a new charge is issued |
| Current-month payment reminder | Linked resident with a balance | At most once at the 10th-day stage, and once at the 20th-day stage |
| Older-month arrears reminder | Linked resident with billed older months still outstanding | Once per calendar month per linked household; old balances are combined |
| Verified payment recorded | Linked resident; authorized supervisors other than the recorder | Immediately after a staff member records and acknowledges external/cash verification |
| Monthly fee fully paid | Linked resident | Immediately after the confirmed payment reduces the billed month's balance to zero |
| Payment entry reversed | Linked resident | When a superadmin records a correction/void |
| Published receiving account changed | Residents linked to households in that city | When active receiving instructions change |

**Note:** No charge means the month is **not yet billed**, not automatically "Unpaid". Partial payments reduce balances; fully paid billed months do not trigger reminders. Reminders never disclose another household's payment information.

### Start scheduled reminders

The command is registered under `app/Console/Commands/GarbageFeeReminders.php`, and the scheduler entry is in `routes/console.php`, configured for **9:00 AM Asia/Manila every day**.

For your Windows local environment, open a *second* PowerShell terminal from the project directory and run:

```powershell
php artisan fees:send-reminders --dry-run
php artisan schedule:work
```

Use `php artisan schedule:run` once per minute in the normal production cron/Windows Task Scheduler configuration. On local development, `schedule:work` must remain running to deliver future scheduled reminders. The reminder command can also be manually tested:

```powershell
php artisan fees:send-reminders --dry-run --date=2026-11-20
php artisan fees:send-reminders
```

**Reminder schedule is relative to billing month:**
- Current month, day 1–9: no mid-month reminder
- Day 10–19: first current-month reminder for each billed month with a balance
- Day 20 onward: second reminder stage
- On the first command execution of each subsequent month: one consolidated older-balance notice for any prior billed months still unpaid

Each message is deduplicated by a notice `alert_key`, billing stage, household and recipient. Live multi-instance synchronization and scheduling need deployment testing before using it for actual collections.

## Household payment history

**Menu:** Garbage Fee Payments → **All-month history** or **My Payment Tracker**, or the separate sidebar **Payment History** / **My Payment Tracker** item.

- **Superadmin/admin:** Search households; filter by city, zone, month range and status. Review a summary of Unpaid/Partial/Paid counts and remaining balances; open a household's billed-month records.
- **Coordinator:** View month-by-month records for their assigned zone only.
- **Resident:** View only households linked to their user account, including amount billed, confirmed payments, remaining balance and status in each billed month.

The tracker lists actual generated monthly charges. It does not invent bills for months without records. Retained payment history is calculated from non-voided payment entries, including partial payments.

## Receiving-account provider dropdown

**Menu:** Receiving Accounts.

The former independent *Payment Method* field has been removed from receiving-account creation and editing. Select a single **Bank / E-wallet Provider** from grouped options, then enter the approved account name and number and optionally upload its private QR code. The system derives whether it is a wallet or bank transfer from the institution choice.

The built-in list contains **common Philippine banks, digital banks and e-wallets**. It is intentionally **not represented as a complete or current BSP licensing register**. Superadmins can expand the selection using **+ Add bank or e-wallet provider**, choose Bank or E-wallet, and save. They can enable/disable added providers later. Barangay admins can select providers and manage receiving accounts but cannot add an institution to the dropdown.

For verification of institutions and current participation, use authoritative sources:
- [BSP Financial Inclusion / BSP-supervised institutions](https://www.bsp.gov.ph/Pages/InclusiveFinance/FinancialProductsServices.aspx)
- [BSP financial institution verification guide](https://www.bsp.gov.ph/SitePages/FinancialStability/BSPVerifier.aspx)
- [BSP InstaPay participants directory](https://www.bsp.gov.ph/PaymentAndSettlement/Instapay%20Participants.pdf)

An institution appearing in the dropdown does **not** mean the barangay is authorized to receive government payments into an account there. The account owner and remittance process must be formally verified.

## Local upgrade

```powershell
cd D:\Websites\solid_waste_management
git pull origin main
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=garbage-fees
php artisan fees:send-reminders --dry-run
php artisan test --filter=FeeLedgerTest
```

Do not use `migrate:fresh`, `db:wipe`, or delete payment collections.

**Important:** Local actual MongoDB, browser, scheduler, role permissions and financial reconciliation checks have not been run through this GitHub-only source update.

## Acceptance tests still required

- [ ] Create city rate/zone/household charge and confirm the resident tracker shows Unpaid, not an invented previous month.
- [ ] Apply partial verified payments, check Partial status/balance; complete payment and verify Paid and resident confirmation notice.
- [ ] Test a reversed payment, confirming the amount returns to the unpaid balance without deleting the original ledger entry.
- [ ] Send a reminder with a billed but unpaid month. Repeat it and confirm no duplicate.
- [ ] Verify Paid records do not receive reminders.
- [ ] Verify previous months consolidate to one arrears notice per household per calendar month.
- [ ] Verify cross-account and cross-zone access (including guessed URLs and QR image URLs) returns 403/404.
- [ ] Verify no resident can enter a manual payment or alter other residents' financial records.
- [ ] Confirm original receiving accounts (including legacy GCash records) still show and remain editable.
- [ ] Add provider as superadmin; confirm admin sees it but cannot add one.
- [ ] Verify QR uploads, private storage, active status and correct institution type.
- [ ] Test reminder scheduling with Windows Task Scheduler / cron and PHP configured for `Asia/Manila`.
- [ ] Test duplicate/overpaid transactions and concurrent staff payment recordings before handling real money.
