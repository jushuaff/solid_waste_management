# Phase 2 — Household Garbage-Fee Tracking (CR-01)

**Status:** Application source added; local MongoDB/browser/financial controls acceptance testing still required before real use.

**New:** The app now includes an [all-month Household Payment History / Resident Payment Tracker](GARBAGE_FEE_NOTIFICATIONS_TRACKER.md), verified-payment notifications, scheduled arrears reminders and a configurable Philippine bank/e-wallet provider list.

**Change request:** CR-01, Nov 19, 2026. City / municipality monthly household rates are configured by the superadministrator. This module is **separate from RA 9003 compliance reports** and must not be mistaken for government-certified collection or payment-gateway processing.

## What the screens do

### Superadministrator
1. Open **City Garbage Fee Rates**.
2. Add the city / municipality and region.
3. Add the ordinance reference and monthly fee amount with an effective month (e.g., `2026-09`).
4. Assign existing collection zones/puroks to the city. A zone can belong to only one currently configured city.
5. Open **Receiving Accounts** to maintain approved payment destinations (GCash and bank) with optional QR codes. Barangay admins can also do this.
6. Open **Garbage Fee Payments** and choose the city + month; click **Generate monthly charges**. Reruns skip months already billed.
7. Review balances and any payment reversals; only a superadmin can void an entry, and must provide a reason.

### Barangay administrator
- Can register, edit, activate or deactivate city receiving accounts and optional QR codes, choosing the receiving institution from a grouped PH bank/e-wallet dropdown.
- Can generate monthly charges using the **superadmin's published city rates**; cannot set or override those rates.
- Can record manually verified **Cash**, **GCash** or **Card** payments against monthly household balances.
- Cannot delete previous payments or change rates/fee amounts on already generated charges.

### Coordinator
- Can view the monthly ledger and record verified payments for **their assigned collection zone only**.
- Cannot configure city rates, generate charges, change receiving accounts or reverse payments.

### Resident
- Must first be **linked to an active household reference** in **Household Registry → Edit/Create → Linked resident account**. The account must be an active resident in the same zone.
- Can view **only their linked household's** monthly charges, paid amount, remaining balance and ledger entries, including an all-month Paid/Partial/Unpaid tracker.
- Can see active bank/e-wallet receiving accounts (including optional QR images) for their household's city. The superadministrator can add custom providers if a particular institution is not listed.
- For **Cash** or **Card**, the app directs them to the official collection office.
- Cannot edit balances, mark themselves paid, or initiate card or wallet transfers from within the app.

### Crew / eco-aide
- Have no access to private household billing ledgers or receiving-account configuration.

## Online resident proof and staff verification

Residents submit **receipt/screenshot/PDF evidence** for an actual transfer made through the external bank or wallet. The record begins **Pending Verification** and is never treated as a completed payment before independent staff confirmation. An admin or superadmin sees it in **Verify Online Payments**, validates that the money reached the authorized receiving account, and either confirms the payment (crediting that month's ledger) or rejects it with an explanation. Both the month view and the all-month resident tracker show Pending Verification alongside the still-outstanding financial balance. Cash is entered separately by authorized admin/superadmin.

Full details, local checks and notification behavior: [Pending receipt / payment tracker guide](GARBAGE_FEE_NOTIFICATIONS_TRACKER.md).

## Payment methods and receiving details

| Method | Account settings | How money is received | What the app records |
| --- | --- | --- | --- |
| Cash | No online receiving account | In-person payment | Verified amount, date and staff entry |
| GCash | Account holder, mobile number, provider, optional QR | Resident transfers funds outside the app | Staff-verified amount and external reference |
| Card | No card number captured by the app | Physical/external card terminal | Staff-verified amount and terminal approval reference |
| Bank transfer/deposit | Bank provider, receiving account name/number, optional QR | Resident pays through banking outside the app | Staff-verified amount and external bank transaction reference |

**Important:** Monthly payment reminders depend on the Laravel scheduler and linked household resident accounts. These notifications do not send real SMS, email or initiate auto-collection.

No live PSP/gateway integration, automatic wallet confirmation, card tokenization, webhook, or official government receipt issuance is implemented. **Do not** enter PIN, CVV, OTP, card number, password, or wallet credentials. QR images are treated as payment *instructions*, not proof of payment.

## Data collections

- `fee_cities`: city/municipality keys and region.
- `fee_rates`: effective-dated monthly household fees, with ordinance reference.
- `fee_receiving_accounts`: active/inactive GCash or bank receiving accounts and optional private QR file path; history retained.
- `garbage_fee_charges`: unique per household and billing month, immutable original charged amount and rate snapshot.
- `garbage_fee_payments`: payment method, integer-centavo amount, recording staff, external transaction reference, manual official receipt reference, reversal reason/status.

**Existing collections are never wiped by this migration.** There is no delete endpoint for charges or payments. Previous fee-rate history remains intact.

All currency math uses integer centavos. An Unpaid/Partial/Paid status is calculated from non-voided ledger entries instead of stored as a freely editable checkbox.

## Configure your local test machine

```powershell
cd D:\Websites\solid_waste_management
git pull origin main
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=garbage-fees
php artisan serve --host=127.0.0.1
```

For optional QR uploads, PHP must allow files of at least 2 MB:
```ini
file_uploads = On
upload_max_filesize = 4M
post_max_size = 8M
```
The earlier **incident attachment** feature requires a higher limit (`upload_max_filesize=16M`, `post_max_size=32M`), so retain the larger settings if those are already configured.

The QR image is stored on the **private** local disk and delivered through an authenticated, access-controlled route; never create a public `storage:link` for this feature. Only JPG/PNG/WebP uploads up to 2 MiB are accepted.

## Testing checklist before any operational deployment

- [ ] Verify 403/404 on city rate changes by admin, receiving-account changes by coordinator, and non-owned household balances by resident.
- [ ] Verify active/inactive receiving accounts, QR upload/replacement/removal, and denied QR access from unrelated zones/cities.
- [ ] Verify city rate history for two successive months and that previously generated charges retain their original fee.
- [ ] Verify generating the same month twice does not duplicate charges, and inactive/archived households are skipped.
- [ ] Verify ₱ amounts with two decimal places, partial payments, overpayment rejection and the one-household-month uniqueness constraint.
- [ ] Verify external GCash/card reference requirement and duplicate reference handling.
- [ ] Verify payment reversal restores the balance without deleting a ledger entry.
- [ ] Verify notifications link back to the correct authorized household/month.
- [ ] Verify no account balance, wallet details, payer identity or QR image is exposed to unauthenticated clients or other residents.
- [ ] Verify accounting controls under **concurrent staff payments** and multiple application instances. MongoDB transactions/shared locking or an approved payment processor reconciliation process is required before handling actual collections.
- [ ] Verify the exact applicable ordinance wording (including coverage, exemptions, payment recipient, remittance arrangements, date of effect, and receipt compliance) with the local LGU.
- [ ] Confirm administrative authorization, identity verification of receiving accounts, cash reconciliation, retention, audit review, backup policy and privacy/security controls with authorized personnel.
- [ ] Verify screen layout on a desktop and phone, including authenticated QR image rendering.

The initial implementation is an **administrative payment-tracking prototype**. It is not an online checkout and has not passed the necessary public-sector accounting and production security review.
