@extends('layouts.app')
@section('title','LINIS Monitoring Dashboard')
@section('content')
<div class="heading"><div><span class="eyebrow">LINIS · MIT 104</span><h1>Segregation & MRF monitoring</h1>
<p>Period: {{ $from->format('F j') }}–{{ $to->format('F j, Y') }} · Metrics derived from stored pickup inspections and weigh-ins.</p></div>
@if(in_array(auth()->user()->role_code,['superadmin','admin','coordinator']))<a class="btn primary" href="{{ route('linis.print-monthly',['month'=>$month]) }}">Print monthly report</a>@endif</div>
<div class="notice">Academic monitoring indicators only. These figures are based on logged observations and must not be presented as certified RA 9003 compliance or official waste diversion figures without LGU validation.</div>
<form class="filterbar" method="GET"><label class="field">Reporting month
<input type="month" class="form-control" name="month" value="{{ $month }}"></label>
<button class="btn outline">Apply</button></form>
<div class="cards3">
<div class="card"><div class="statlab">Logged inspections</div><div class="statval">{{ number_format($results['inspections']) }}</div><div class="statsub">{{ number_format($results['segregated']) }} properly segregated</div></div>
<div class="card"><div class="statlab">Segregation compliance</div><div class="statval">{{ $results['compliance']===null?'No data':number_format($results['compliance'],2).'%' }}</div><div class="statsub">Logged inspections only</div></div>
<div class="card"><div class="statlab">Recorded MRF diversion</div><div class="statval">{{ $results['diversion']===null?'No data':number_format($results['diversion'],2).'%' }}</div><div class="statsub">{{ number_format($results['diverted_kg'],3) }} / {{ number_format($results['mrf_kg'],3) }} kg</div></div>
</div>
<div class="card"><h2 class="paneltitle">Monitoring by zone / purok</h2>
<div style="overflow-x:auto"><table class="content-table"><thead><tr>
<th>Zone</th><th>Inspections</th><th>Properly segregated</th><th>Compliance</th><th>MRF received (kg)</th><th>Diverted (kg)</th><th>Recorded diversion</th></tr></thead><tbody>
@forelse($results['zones'] as $row)<tr>
<td>{{ $row['zone']->name }}</td><td>{{ $row['inspections'] }}</td><td>{{ $row['segregated'] }}</td>
<td><span class="badge {{ $row['color']==='red'?'red':($row['color']==='yellow'?'amber':($row['color']==='grey'?'grey':'')) }}">{{ $row['compliance']===null?'No data':number_format($row['compliance'],2).'%' }}</span></td>
<td>{{ number_format($row['mrf_kg'],3) }}</td><td>{{ number_format($row['diverted_kg'],3) }}</td>
<td>{{ $row['diversion']===null?'—':number_format($row['diversion'],2).'%' }}</td>
</tr>@empty<tr><td colspan="7">No configured zones available.</td></tr>@endforelse
</tbody></table></div></div>
<div class="actions" style="margin-top:18px">
<a class="btn outline" href="{{ route('linis.index','inspections') }}">Inspection logs</a>
<a class="btn outline" href="{{ route('linis.index','weigh-ins') }}">MRF weigh-ins</a>
<a class="btn outline" href="{{ route('linis.index','households') }}">Household registry</a></div>
@endsection
