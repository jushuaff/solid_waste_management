@extends('layouts.print')
@section('title','Case Report · '.$report->tracking_id)
@section('content')
<h2>Incident details</h2><table><tbody>
<tr><th>Tracking reference</th><td>{{$report->tracking_id}} ({{ ($report->incident_type??'missed_collection')==='illegal_dumping'?'Illegal dumping':'Missed collection' }})</td><th>Status</th><td class="status">{{$report->current_status}}</td></tr>
<tr><th>Zone</th><td>{{$report->zone?->name}}</td><th>Waste category</th><td>{{$report->category?->local_label}}</td></tr>
<tr><th>Incident date</th><td>{{$report->incident_date}}</td><th>Report submitted</th><td>{{$report->submitted_at}}</td></tr>
<tr><th>Location</th><td colspan="3">{{$report->location}}</td></tr>
<tr><th>Description</th><td colspan="3">{{$report->description}}</td></tr></tbody></table>
<h2>Recorded status history</h2><table><thead><tr><th>Time</th><th>Previous</th><th>New status</th><th>Note / reason</th></tr></thead><tbody>@foreach($history as $h)<tr><td>{{$h->changed_at}}</td><td>{{$h->from_status??'—'}}</td><td>{{$h->to_status}}</td><td>{{$h->reason}}</td></tr>@endforeach</tbody></table>
@endsection
