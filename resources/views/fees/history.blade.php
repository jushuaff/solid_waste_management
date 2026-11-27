@extends('layouts.app')
@section('title','Household Payment History')
@section('content')
@php($isStaff=$role!=='resident')
<div class="heading">
<div>
<span class="eyebrow">GARBAGE FEE COLLECTION</span>
<h1>{{ $isStaff?'Household payment history':'My monthly payment tracker' }}</h1>
<p>{{ $isStaff?'Review paid, partially paid and unpaid billing months by household.':'Follow your billed months, confirmed payments and outstanding balances in one place.' }}</p>
</div>
<a class="btn outline" href="{{route('fees.index')}}">← Current month</a>
</div>
<div class="notice">
Only months with an issued household charge appear here. A month without an issued charge is <strong>not automatically an unpaid balance</strong>. Payments reflect authorized staff verification, not an automatic wallet or card checkout.
</div>
<div class="card" style="margin-bottom:18px">
<form class="filterbar" method="GET">
@if($isStaff)
<label class="field">Household reference
<input class="form-control" type="search" name="q" maxlength="100" value="{{request('q')}}" placeholder="Search household"></label>
@if(in_array($role,['superadmin','admin'],true))
<label class="field">City
<select name="city_id" class="form-control"><option value="">All cities</option>
@foreach($cities as $city)<option value="{{$city->id}}" @selected(request('city_id')===(string)$city->id)>{{$city->name}}</option>@endforeach
</select></label>
<label class="field">Zone / Purok
<select name="zone_id" class="form-control"><option value="">All zones</option>
@foreach($zones as $zone)<option value="{{$zone->id}}" @selected(request('zone_id')===(string)$zone->id)>{{$zone->name}}</option>@endforeach
</select></label>
@endif
@endif
<label class="field">From month
<input class="form-control" type="month" name="from" value="{{request('from')}}"></label>
<label class="field">To month
<input class="form-control" type="month" name="to" value="{{request('to')}}"></label>
<label class="field">Payment status
<select name="status" class="form-control">
<option value="">All months</option>
@foreach(['Unpaid','Partial','Paid'] as $choice)
<option value="{{$choice}}" @selected($filterStatus===$choice)>{{$choice}}</option>
@endforeach
</select></label>
<button class="btn primary" type="submit">Apply</button>
<a class="btn outline" href="{{route('fees.history.index')}}">Reset</a>
</form>
</div>
<div class="cards3">
<div class="card"><div class="statlab">Unpaid months</div><div class="statval">{{number_format($unpaidCount)}}</div><div class="statsub">Months with no confirmed payment</div></div>
<div class="card"><div class="statlab">Partially paid months</div><div class="statval">{{number_format($partialCount)}}</div><div class="statsub">{{number_format($paidCount)}} fully paid month(s)</div></div>
<div class="card"><div class="statlab">Outstanding balance</div><div class="statval">{{\App\Services\FeeLedger::money($totalBalance)}}</div><div class="statsub">Balance across displayed filters</div></div>
</div>

@if($isStaff)
<div class="card" style="margin-top:18px">
<h2 class="paneltitle">Households and outstanding months</h2>
<p class="muted">The list is ordered by unpaid balance. Select a household to review its billed months.</p>
<div style="overflow-x:auto">
<table class="content-table">
<thead><tr><th>Household</th><th>City / Zone</th><th>Unpaid</th><th>Partial</th><th>Paid</th><th>Outstanding</th><th>View</th></tr></thead>
<tbody>
@forelse($summary as $item)
<tr>
<td><strong>{{$item['household']?->reference??'Household unavailable'}}</strong></td>
<td>{{$item['city']?->name??'—'}}<small class="muted" style="display:block">{{$item['zone']?->name??''}}</small></td>
<td>{{$item['unpaid_months']}}</td>
<td>{{$item['partial_months']}}</td>
<td>{{$item['paid_months']}}</td>
<td>{{\App\Services\FeeLedger::money($item['outstanding'])}}</td>
<td><a class="btn small outline" href="{{route('fees.history.index',['household_id'=>$item['household']?->id])}}">View months</a></td>
</tr>
@empty<tr><td colspan="7" class="muted">No household billing history matches these filters.</td></tr>@endforelse
</tbody></table></div>
</div>
@endif
<div class="card" style="margin-top:18px">
<h2 class="paneltitle">{{ $isStaff?'Monthly household charge history':'My billed months' }}</h2>
<div style="overflow-x:auto">
<table class="content-table">
<thead><tr><th>Month</th><th>Household</th><th>City / Zone</th><th>Amount billed</th><th>Confirmed paid</th><th>Balance</th><th>Status</th><th>Details</th></tr></thead>
<tbody>
@forelse($months as $row)
@php($bill=$row['charge'])
<tr>
<td><strong>{{\Carbon\Carbon::parse($bill->billing_month.'-01')->format('F Y')}}</strong></td>
<td>{{$bill->household?->reference??'—'}}</td>
<td>{{$bill->city?->name??'—'}}<small class="muted" style="display:block">{{$bill->zone?->name??''}}</small></td>
<td>{{\App\Services\FeeLedger::money((int)$bill->amount_centavos)}}</td>
<td>{{\App\Services\FeeLedger::money($row['paid'])}}</td>
<td><strong>{{\App\Services\FeeLedger::money($row['balance'])}}</strong></td>
<td><span class="badge {{$row['status']==='Unpaid'?'amber':($row['status']==='Partial'?'grey':'')}}">{{$row['status']}}</span></td>
<td><a class="btn small outline" href="{{route('fees.index',['month'=>$bill->billing_month,'highlight'=>(string)$bill->id])}}">View ledger</a></td>
</tr>
@empty
<tr><td colspan="8" class="muted">No billing history found. The barangay may not have generated charges for these months.</td></tr>
@endforelse
</tbody></table>
</div>
<div class="pager">{{$months->links('components.pagination')}}</div>
</div>
@endsection
