@extends('layouts.print')
@section('title','Collection Reporting Summary')
@section('content')
<p>Filtered incident reports. Up to 2,000 latest matching records are included in this printout.</p>
<h2>Overview</h2><p>Total matching records: <strong>{{number_format($total)}}</strong></p>
<table><thead><tr><th>Report status</th><th>Number of reports</th></tr></thead><tbody>@foreach($byStatus as $status=>$count)<tr><td>{{$status}}</td><td>{{number_format($count)}}</td></tr>@endforeach</tbody></table>
<h2>Incident register</h2><table><thead><tr><th>Tracking Reference</th><th>Incident date</th><th>Zone</th><th>Category</th><th>Status</th></tr></thead><tbody>@foreach($records as $r)<tr><td>{{$r->tracking_id}}</td><td>{{$r->incident_date}}</td><td>{{$r->zone?->name}}</td><td>{{$r->category?->local_label}}</td><td>{{$r->current_status}}</td></tr>@endforeach</tbody></table>
@endsection
