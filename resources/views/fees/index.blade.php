@extends('layouts.app')
@section('title','Monthly Garbage Fees')
@section('content')
@php($isStaff=in_array($role,['superadmin','admin','coordinator'],true))
@php($canGenerate=in_array($role,['superadmin','admin'],true))
<div class="heading">
<div>
<span class="eyebrow">HOUSEHOLD SERVICES · BILLING</span>
<h1>Monthly garbage fees</h1>
<p>{{ $isStaff ? 'Review household charges, record confirmed payments and monitor collection balances.' : 'Review your household fees, recorded payments and the official receiving accounts for your area.' }}</p>
</div>
<div class="actions">
<a class="btn outline" href="{{route('fees.history.index')}}">{{$isStaff?'All-month history':'My payment tracker'}}</a>
</div>
@if($canGenerate)
<div class="actions">
@if($role==='superadmin')<a class="btn outline" href="{{route('fees.settings.index')}}">City rates</a>@endif
<a class="btn outline" href="{{route('fees.accounts.index')}}">Receiving accounts</a>
</div>
@endif
</div>
<div class="notice">This section records monthly charges and payments. Online transfers and card transactions are processed outside the application. A payment is reflected as paid only after an authorized staff member verifies and records it.</div>
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
@foreach(['Unpaid','Partial','Paid'] as $value)<option value="{{$value}}" @selected($status===$value)>{{$value}}</option>@endforeach
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
<td><span class="badge {{$row['status']==='Unpaid'?'amber':($row['status']==='Partial'?'grey':'')}}">{{$row['status']}}</span></td>
<td><details class="fee-details" @if(request('highlight')===(string)$bill->id) open @endif>
<summary>View</summary>
<div class="fee-details-content">
<p><strong>Billing month:</strong> {{$bill->billing_month}}</p>
<p><strong>Monthly fee:</strong> {{\App\Services\FeeLedger::money((int)$bill->amount_centavos)}} · <strong>Balance:</strong> {{\App\Services\FeeLedger::money($row['balance'])}}</p>
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
@if($isStaff && $row['balance']>0)
<h4>Record confirmed payment</h4>
<form method="POST" action="{{route('fees.payments.store',$bill->id)}}" class="stack">
@csrf
<div class="grid2">
<div class="field"><label>Payment method *</label>
<select name="method" class="form-control" required>
<option value="cash">Cash</option><option value="gcash">GCash transfer</option><option value="bank">Bank transfer / deposit</option><option value="card">Card (external terminal)</option>
</select></div>
<div class="field"><label>Amount received (PHP) *</label>
<input class="form-control" name="amount" type="number" step="0.01" min="0.01" max="{{number_format($row['balance']/100,2,'.','')}}" required>
</div>
<div class="field"><label>Payment date *</label>
<input class="form-control" type="date" name="paid_on" value="{{now()->toDateString()}}" max="{{now()->toDateString()}}" required></div>
<div class="field"><label>External transaction/reference</label>
<input class="form-control" name="external_reference" maxlength="100" autocomplete="off" placeholder="Required for GCash, Bank and Card"></div>
<div class="field"><label>Official receipt number (if issued)</label>
<input class="form-control" name="official_receipt_number" maxlength="100" autocomplete="off"></div>
<div class="field"><label>Internal note (optional)</label>
<input class="form-control" name="note" maxlength="500"></div>
</div>
<label class="checkline"><input type="checkbox" name="acknowledged" value="1" required> I have verified this payment through the cash collection record or external payment provider.</label>
<button class="btn primary" type="submit">Record verified payment</button>
</form>
@endif
</div></details></td>
</tr>
@empty
<tr><td colspan="8" class="muted">No household charges found for this month.</td></tr>
@endforelse
</tbody></table>
</div>
<div class="pager">{{$charges->links('components.pagination')}}</div>
</div>

@if(!$isStaff && $receivingAccounts->isNotEmpty())
<div class="card" style="margin-top:20px">
<h2 class="paneltitle">Where to pay</h2>
<p class="muted">Before transferring money, verify the account name and number. Keep your GCash or bank transaction reference. Payment status is updated after staff confirmation.</p>
<div class="fee-receiving-grid">
@foreach($receivingAccounts as $account)
<div class="fee-receiving-card">
<span class="badge">{{strtoupper($account->method)}}</span>
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
