@extends('layouts.app')
@section('title','Monthly Garbage Fees')
@section('content')
@php($isStaff=in_array($role,['superadmin','admin','coordinator'],true))
@php($canGenerate=in_array($role,['superadmin','admin'],true))
@php($canRecord=$canGenerate)
<div class="heading">
<div>
<span class="eyebrow">HOUSEHOLD SERVICES · BILLING</span>
<h1>Monthly garbage fees</h1>
<p>{{ $isStaff ? 'Review household charges, record confirmed payments and monitor collection balances.' : 'Review your household fees, recorded payments and the official receiving accounts for your area.' }}</p>
</div>
<div class="actions">
<a class="btn outline" href="{{route('fees.history.index')}}">{{$isStaff?'All-month history':'My payment tracker'}}</a>
@if($canGenerate)
@if($role==='superadmin')<a class="btn outline" href="{{route('fees.settings.index')}}">City rates</a>@endif
<a class="btn outline" href="{{route('fees.accounts.index')}}">Receiving accounts</a>
<a class="btn primary" href="{{route('fees.submissions.index')}}">Verify online payments @if($pendingVerificationCount>0)· {{$pendingVerificationCount}} pending@endif</a>
@endif
</div>
</div>
<div class="notice">Garbage-fee balances change only after an authorized collector verifies an online transfer or records a cash payment. Uploaded payment receipts remain pending until independently confirmed.</div>
@if($role==='resident')
<section class="card fee-online-submit" id="submit-online-payment">
    <div class="fee-online-heading">
        <div>
            <span class="eyebrow">ONLINE TRANSFER</span>
            <h2 class="paneltitle">Submit online payment</h2>
            <p class="muted">Transfer through your GCash, bank or e-wallet app, then send your receipt for verification.</p>
        </div>
        <span class="badge grey">Staff verification required</span>
    </div>
    @if($residentHouseholdCount===0)
        <div class="fee-billing-help">
            <strong>Your resident account isn't linked to an active household yet.</strong>
            <p>Ask the barangay administrator to link your account in Household Registry before submitting a garbage-fee payment.</p>
        </div>
    @elseif($receivingAccounts->isEmpty())
        <div class="fee-billing-help">
            <strong>No approved online receiving account is available for your household.</strong>
            <p>Please ask the barangay administrator to publish a verified bank or e-wallet account for your city. Do not transfer funds to an unverified destination.</p>
        </div>
    @elseif($residentOutstanding->isEmpty())
        <div class="fee-billing-help">
            <strong>No eligible unpaid billing month is available for receipt submission.</strong>
            <p>This may mean your bills are already paid, a transfer receipt is waiting for verification, or your barangay has not generated a charge for the selected household. See <a href="{{route('fees.history.index')}}">My Payment Tracker</a> for all billed months.</p>
        </div>
    @else
        <div class="fee-payment-steps">
            <span><strong>1</strong> Choose a billed month</span>
            <span><strong>2</strong> Transfer to the approved account</span>
            <span><strong>3</strong> Upload proof</span>
        </div>
        <form method="POST" action="{{route('fees.submissions.store')}}" enctype="multipart/form-data"
            class="fee-online-form" data-fee-online-form>
            @csrf
            <div class="fee-online-fields">
                <div class="field">
                    <label for="fee-submit-charge">Household and billing month *</label>
                    <select class="form-control" id="fee-submit-charge" name="charge_id" required>
                        <option value="">Choose unpaid month</option>
                        @foreach($residentOutstanding as $billOption)
                            <option value="{{$billOption->id}}" data-fee-city="{{$billOption->city_id}}"
                                data-fee-balance="{{\App\Services\FeeLedger::balance($billOption)}}"
                                @selected(old('charge_id')===(string)$billOption->id)>
                                {{$billOption->household?->reference??'Household'}} · {{\Carbon\Carbon::parse($billOption->billing_month.'-01')->format('F Y')}}
                                · Balance {{\App\Services\FeeLedger::money(\App\Services\FeeLedger::balance($billOption))}}
                            </option>
                        @endforeach
                    </select>
                    <small class="hint">Only months with an issued, outstanding charge can be submitted.</small>
                </div>
                <div class="field">
                    <label for="fee-submit-account">Receiving bank / e-wallet *</label>
                    <select class="form-control" id="fee-submit-account" name="receiving_account_id" required>
                        <option value="">Select your approved payment destination</option>
                        @foreach($receivingAccounts as $account)
                            <option value="{{$account->id}}" data-fee-city="{{$account->city_id}}"
                                @selected(old('receiving_account_id')===(string)$account->id)>
                                {{$account->provider}} · {{$account->account_name}}
                            </option>
                        @endforeach
                    </select>
                    <small class="hint">Check the account number and optional QR code in <a href="#fee-payment-destinations">Where to pay</a> before transferring.</small>
                </div>
                <div class="field">
                    <label for="fee-submit-amount">Amount transferred (PHP) *</label>
                    <input class="form-control" id="fee-submit-amount" type="number" step="0.01" min="0.01"
                        name="amount" placeholder="0.00" value="{{old('amount')}}" required>
                    <small class="hint" data-fee-balance-hint>Choose a billing month to see the outstanding amount.</small>
                </div>
                <div class="field">
                    <label for="fee-submit-date">Transfer date *</label>
                    <input class="form-control" id="fee-submit-date" type="date" name="paid_on"
                        value="{{old('paid_on',now()->toDateString())}}" max="{{now()->toDateString()}}" required>
                </div>
                <div class="field">
                    <label for="fee-submit-reference">GCash / bank transaction reference *</label>
                    <input class="form-control" id="fee-submit-reference" name="transaction_reference"
                        minlength="5" maxlength="100" value="{{old('transaction_reference')}}"
                        placeholder="Reference from your completed transfer" autocomplete="off" required>
                </div>
                <div class="field">
                    <label for="fee-submit-proof">Transfer receipt / screenshot *</label>
                    <input class="form-control" id="fee-submit-proof" name="receipt" type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf" required>
                    <small class="hint" data-fee-proof-hint>JPG, PNG, WebP or PDF · 5 MB maximum.</small>
                </div>
            </div>
            <div class="field">
                <label for="fee-submit-note">Additional details <span class="fee-optional">Optional</span></label>
                <textarea class="form-control" id="fee-submit-note" name="note" rows="2" maxlength="400"
                    placeholder="Any helpful details for verifying your transfer">{{old('note')}}</textarea>
            </div>
            <div class="fee-online-action">
                <p class="muted">Submitting a receipt does not mark a bill as paid. Staff must confirm the money was received.</p>
                <button class="btn primary" type="submit">Submit receipt for verification</button>
            </div>
        </form>
    @endif

    @if($residentSubmissions->isNotEmpty())
        <div class="fee-online-submissions">
            <h3>My submitted online payments</h3>
            <div class="fee-review-scroll">
                <table class="content-table fee-resident-proof-table">
                    <thead><tr><th>Billing month</th><th>Transferred</th><th>Reference</th><th>Status</th><th>Proof</th></tr></thead>
                    <tbody>
                    @foreach($residentSubmissions as $proof)
                        <tr>
                            <td>{{$proof->charge?->billing_month??'—'}}</td>
                            <td>{{\App\Services\FeeLedger::money((int)$proof->amount_centavos)}}</td>
                            <td>{{$proof->submission_code}}</td>
                            <td>
                                <span class="badge {{$proof->status==='pending'?'amber':($proof->status==='rejected'?'grey':'')}}">
                                    {{match($proof->status){'pending'=>'Pending verification','verified'=>'Verified','rejected'=>'Rejected',default=>'Under review'}}}
                                </span>
                                @if($proof->status==='rejected' && $proof->review_note)
                                    <small class="fee-review-small">Reason: {{$proof->review_note}}</small>
                                @endif
                            </td>
                            <td><a href="{{route('fees.submissions.receipt',$proof->id)}}" target="_blank"
                                rel="noopener noreferrer">View receipt</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
@endif

@if($canGenerate)
<div class="card" style="margin-bottom:18px">
<h2 class="paneltitle">Generate monthly household charges</h2>
<form method="POST" action="{{route('fees.generate')}}" class="filterbar">
@csrf
<label class="field">City / municipality
<select class="form-control" name="city_id" required>
<option value="">Select city</option>
@foreach($cities as $city)<option value="{{$city->id}}">{{$city->name}}</option>@endforeach
</select></label>
<label class="field">Billing month <input class="form-control" type="month" name="month" value="{{$month}}" required></label>
<button class="btn primary" type="submit" onclick="return confirm('Create charges for all active households in this city for the selected month? Existing charges will be retained.')">Generate monthly charges</button>
</form>
<p class="muted" style="margin-top:12px">Existing monthly charges are not regenerated. Rate changes affect only new billing months or as permitted by the applicable ordinance.</p>
</div>
@endif
<div class="card" style="margin-bottom:18px">
<form method="GET" class="filterbar">
<label class="field">Reporting month
<input class="form-control" type="month" name="month" value="{{$month}}"></label>
@if($isStaff)
<label class="field">City
<select class="form-control" name="city_id"><option value="">All cities</option>
@foreach($cities as $city)<option value="{{$city->id}}" @selected((string)request('city_id')===(string)$city->id)>{{$city->name}}</option>@endforeach
</select></label>
<label class="field">Collection zone
<select class="form-control" name="zone_id"><option value="">All allowed zones</option>
@foreach($zones as $zone)<option value="{{$zone->id}}" @selected((string)request('zone_id')===(string)$zone->id)>{{$zone->name}}</option>@endforeach
</select></label>
@endif
<label class="field">Payment status
<select class="form-control" name="status">
<option value="">All statuses</option>
@foreach(['Unpaid','Partial','Paid','Pending Verification'] as $value)<option value="{{$value}}" @selected($status===$value)>{{$value}}</option>@endforeach
</select></label>
<button class="btn outline" type="submit">Apply</button>
<a class="btn" href="{{route('fees.index')}}">Reset</a>
</form>
</div>
<div class="cards3">
<div class="card"><div class="statlab">Household charges</div><div class="statval">{{number_format($charges->total())}}</div><div class="statsub">In selected month and filters</div></div>
<div class="card"><div class="statlab">Amount charged</div><div class="statval">{{\App\Services\FeeLedger::money($totalDue)}}</div><div class="statsub">Original monthly charge amounts</div></div>
<div class="card"><div class="statlab">Remaining balance</div><div class="statval">{{\App\Services\FeeLedger::money(max(0,$totalDue-$totalPaid))}}</div><div class="statsub">{{\App\Services\FeeLedger::money($totalPaid)}} recorded payments</div></div>
</div>
<div class="card" style="margin-top:18px">
<h2 class="paneltitle">Household payment tracking</h2>
<div style="overflow-x:auto">
<table class="content-table">
<thead><tr>
<x-sortable-th key-name="household" label="Household" />
<x-sortable-th key-name="city" label="City" />
<x-sortable-th key-name="zone" label="Zone" />
<x-sortable-th key-name="due" label="Monthly fee" />
<x-sortable-th key-name="paid" label="Recorded payments" />
<x-sortable-th key-name="balance" label="Balance" />
<x-sortable-th key-name="status" label="Status" />
<th scope="col">Details</th>
</tr></thead>
<tbody>
@forelse($charges as $row)
@php($bill=$row['charge'])
<tr id="charge-{{$bill->id}}" @class(['fee-highlight'=>request('highlight')===(string)$bill->id])>
<td><strong>{{$bill->household?->reference??'Household unavailable'}}</strong></td>
<td>{{$bill->city?->name??'—'}}</td>
<td>{{$bill->zone?->name??'—'}}</td>
<td>{{\App\Services\FeeLedger::money((int)$bill->amount_centavos)}}</td>
<td>{{\App\Services\FeeLedger::money($row['paid'])}}</td>
<td><strong>{{\App\Services\FeeLedger::money($row['balance'])}}</strong></td>
<td>
<span class="badge {{$row['status']==='Unpaid'?'amber':($row['status']==='Partial'?'grey':'')}}">{{$row['status']}}</span>
@if($row['pending'])
<span class="badge amber fee-pending-badge" title="Receipt submitted; no funds credited yet">Pending Verification</span>
@endif
</td>
<td><details class="fee-details" @if(request('highlight')===(string)$bill->id) open @endif>
<summary>View</summary>
<div class="fee-details-content">
<p><strong>Billing month:</strong> {{$bill->billing_month}}</p>
<p><strong>Monthly fee:</strong> {{\App\Services\FeeLedger::money((int)$bill->amount_centavos)}} · <strong>Balance:</strong> {{\App\Services\FeeLedger::money($row['balance'])}}</p>
@if($row['pending'])<p class="fee-pending-note">An online payment receipt has been submitted and is awaiting administrator verification. The remaining balance will only change after confirmation.</p>@endif
@if($bill->payments->isNotEmpty())
<h4>Recorded payment history</h4>
<div style="overflow-x:auto"><table class="content-table">
<thead><tr><th>Payment date</th><th>Method</th><th>Amount</th><th>Reference</th><th>Entry status</th>@if($role==='superadmin')<th>Correction</th>@endif</tr></thead>
<tbody>
@foreach($bill->payments->sortByDesc('recorded_at') as $payment)
<tr>
<td>{{$payment->paid_on}}</td>
<td>{{strtoupper($payment->method)}}</td>
<td>{{\App\Services\FeeLedger::money((int)$payment->amount_centavos)}}</td>
<td><small>{{$payment->receipt_code}}</small></td>
<td>{{$payment->status==='void'?'Reversed':'Recorded'}}</td>
@if($role==='superadmin')<td>
@if($payment->status==='recorded')
<form method="POST" action="{{route('fees.payments.void',$payment->id)}}" class="stack">
@csrf
<input class="form-control" type="text" name="reason" required minlength="10" maxlength="500" placeholder="Reason for reversal">
<button class="btn small outline" type="submit" onclick="return confirm('Reverse this payment entry? The audit history will be preserved.')">Reverse entry</button>
</form>
@else <span class="muted">—</span> @endif
</td>@endif
</tr>
@endforeach
</tbody></table></div>
@else
<p class="muted">No payments recorded for this month.</p>
@endif
@if($canRecord && $row['balance']>0)
<h4>Record cash payment</h4>
<p class="muted">Use this only for cash received at the authorized collection office. Online transfers are confirmed in the Verify Online Payments queue.</p>
<form method="POST" action="{{route('fees.payments.store',$bill->id)}}" class="stack">
@csrf
<input type="hidden" name="method" value="cash">
<div class="grid2">
<div class="field"><label>Cash amount received (PHP) *</label>
<input class="form-control" name="amount" type="number" step="0.01" min="0.01" max="{{number_format($row['balance']/100,2,'.','')}}" required>
</div>
<div class="field"><label>Payment date *</label>
<input class="form-control" type="date" name="paid_on" value="{{now()->toDateString()}}" max="{{now()->toDateString()}}" required></div>
<div class="field"><label>Official receipt number (if issued)</label>
<input class="form-control" name="official_receipt_number" maxlength="100" autocomplete="off"></div>
<div class="field"><label>Internal note (optional)</label>
<input class="form-control" name="note" maxlength="500"></div>
</div>
<label class="checkline"><input type="checkbox" name="acknowledged" value="1" required> I have received and verified this cash payment against the office's cash collection record.</label>
<button class="btn primary" type="submit">Record cash payment</button>
</form>
@endif
</div></details></td>
</tr>
@empty
<tr><td colspan="8" class="muted">No household charges found for this month. {{ $role==='resident'?'Use My Payment Tracker to review other billed months, or contact the barangay if a bill is expected.':'Check your month/filters, or generate monthly charges for the applicable city.' }}</td></tr>
@endforelse
</tbody></table>
</div>
<div class="pager">{{$charges->links('components.pagination')}}</div>
</div>

@if(!$isStaff && $receivingAccounts->isNotEmpty())
<div class="card" id="fee-payment-destinations" style="margin-top:20px">
<h2 class="paneltitle">Where to pay</h2>
<p class="muted">Before transferring money, verify the account name and number. Keep your GCash or bank transaction reference. Payment status is updated after staff confirmation.</p>
<div class="fee-receiving-grid">
@foreach($receivingAccounts as $account)
<div class="fee-receiving-card">
<span class="badge">{{in_array($account->method,['gcash','wallet'],true)?'E-wallet':'Bank transfer'}}</span>
<h3 style="margin:10px 0 5px">{{$account->provider}}</h3>
<p><strong>{{$account->account_name}}</strong></p>
<p>Account number: <strong>{{$account->account_number}}</strong></p>
<p class="muted">{{$account->city?->name}}</p>
@if($account->instructions)<p>{{$account->instructions}}</p>@endif
@if($account->qr_path)<img class="fee-qr-preview" alt="Payment QR code for {{$account->provider}}" src="{{route('fees.accounts.qr',$account->id)}}">@endif
</div>
@endforeach
</div>
<div class="notice" style="margin-top:14px">For cash or card payments, visit the authorized barangay/city collection office. Do not enter card numbers, PINs, passwords or OTPs into this system.</div>
</div>
@elseif(!$isStaff)
<div class="card" style="margin-top:20px">
<h2 class="paneltitle">Payment instructions</h2>
<p class="muted">Online receiving-account details are not available for your linked household. Contact your barangay office for official payment instructions.</p>
</div>
@endif
@endsection
@section('scripts')
@if($role==='resident')
<script src="{{asset('assets/fee-payment.js')}}" defer></script>
@endif
@endsection
