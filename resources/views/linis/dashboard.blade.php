@extends('layouts.app')
@section('title','LINIS Monitoring Dashboard')
@section('content')
<div class="heading"><div><span class="eyebrow">WASTE MANAGEMENT</span><h1>Segregation & MRF monitoring</h1>
<p>Period: {{ $from->format('F j') }}–{{ $to->format('F j, Y') }} · Inspection and materials recovery overview.</p></div>
@if(in_array(auth()->user()->role_code,['superadmin','admin','coordinator']))<div class="actions">
<a class="btn outline" target="_blank" rel="noopener" href="{{route('linis.print-monthly',['month'=>$month])}}">Preview monthly PDF</a>
<a class="btn primary" href="{{route('linis.print-monthly',['month'=>$month,'download'=>1])}}">Download monthly PDF</a>
</div>@endif</div>
<div class="notice">Figures reflect available inspection and weigh-in records. Review the data for completeness before submitting official reports.</div>
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
<x-sortable-th key-name="zone" label="Zone" />
<x-sortable-th key-name="inspections" label="Inspections" />
<x-sortable-th key-name="segregated" label="Properly segregated" />
<x-sortable-th key-name="compliance" label="Compliance" />
<x-sortable-th key-name="mrf_weight" label="MRF received (kg)" />
<x-sortable-th key-name="diverted" label="Diverted (kg)" />
<x-sortable-th key-name="diversion" label="Recorded diversion" /></tr></thead><tbody>
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
