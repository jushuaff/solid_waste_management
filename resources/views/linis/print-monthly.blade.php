@extends('layouts.print')
@section('title','LINIS Monthly Waste Monitoring Summary')
@section('content')
<p><strong>Reporting period:</strong> {{ $from->format('F j, Y') }} – {{ $to->format('F j, Y') }}</p>
<p><strong>Basis:</strong> Online-recorded eco-aide pickup inspections and MRF weigh-in entries. This is a prototype monitoring summary, not a formally certified statutory report.</p>
<h2>Monthly totals</h2>
<table><tbody>
<tr><th>Recorded inspections</th><td>{{ number_format($results['inspections']) }}</td></tr>
<tr><th>Properly segregated inspections</th><td>{{ number_format($results['segregated']) }}</td></tr>
<tr><th>Segregation rate</th><td>{{ $results['compliance']===null?'No observations':number_format($results['compliance'],2).'%' }}</td></tr>
<tr><th>Total received at MRF (kg)</th><td>{{ number_format($results['mrf_kg'],3) }}</td></tr>
<tr><th>Recorded diverted weight (kg)</th><td>{{ number_format($results['diverted_kg'],3) }}</td></tr>
<tr><th>Record-based diversion rate</th><td>{{ $results['diversion']===null?'No weigh-ins':number_format($results['diversion'],2).'%' }}</td></tr>
</tbody></table>
<h2>Breakdown by zone / purok</h2>
<table><thead><tr><th>Zone</th><th>Inspections</th><th>Segregated</th><th>Segregation %</th><th>MRF kg</th><th>Diverted kg</th><th>Recorded diversion %</th></tr></thead><tbody>
@foreach($results['zones'] as $row)
<tr><td>{{ $row['zone']->name }}</td><td>{{ $row['inspections'] }}</td><td>{{ $row['segregated'] }}</td>
<td>{{ $row['compliance']===null?'—':number_format($row['compliance'],2) }}</td>
<td>{{ number_format($row['mrf_kg'],3) }}</td><td>{{ number_format($row['diverted_kg'],3) }}</td>
<td>{{ $row['diversion']===null?'—':number_format($row['diversion'],2) }}</td></tr>
@endforeach
</tbody></table>
<p>Rate definitions: segregation = segregated inspection entries ÷ all observed inspection entries; record-based diversion = diverted MRF kilograms ÷ received MRF kilograms. Missing observations are shown as no data, not zero compliance.</p>
<p class="foot"><strong>Review:</strong> ____________________ &nbsp; <strong>Date:</strong> ____________________</p>
@endsection
