@extends('layouts.app')
@section('title','Reports & Tracking')
@section('content')
<div class="heading"><div><span class="eyebrow">CASE MANAGEMENT</span><h1>Collection and disposal reports</h1><p>Review report status, assignment and recorded outcomes.</p></div><div class="actions">@if(auth()->user()->role_code==='resident')<a href="{{route('reports.create')}}" class="btn primary">＋ Submit report</a>@endif
@if(auth()->user()->canManage())
<a target="_blank" rel="noopener" href="{{route('reports.print-summary',request()->query())}}" class="btn outline">Preview summary PDF</a>
<a href="{{route('reports.print-summary',array_merge(request()->query(),['download'=>1]))}}" class="btn outline">Download summary PDF</a>
@endif</div></div>
<div class="card"><form method="GET" class="filterbar report-filters">
@if(auth()->user()->role_code!=='resident')<div class="field"><label>Zone</label><select name="zone_id" class="form-control"><option value="">All zones</option>@foreach($zones as $z)<option value="{{$z->id}}" @selected(request('zone_id')===$z->id)>{{$z->name}}</option>@endforeach</select></div>@endif
<div class="field"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(config('swm.report_statuses') as $s)<option @selected(request('status')===$s)>{{$s}}</option>@endforeach</select></div>
<div class="field"><label>From incident date</label><input class="form-control" type="date" name="from" value="{{request('from')}}"></div><div class="field"><label>To date</label><input class="form-control" type="date" name="to" value="{{request('to')}}"></div>
@if(auth()->user()->canAdmin())<label class="checkline filter-archived"><input type="checkbox" name="include_archived" value="1" @checked(request('include_archived'))> Include archived</label>@endif
<button class="btn primary" type="submit">Apply filters</button><a class="btn" href="{{route('reports.index')}}">Clear</a>
</form><div style="overflow:auto"><table class="content-table"><thead><tr><x-sortable-th key-name="reference" label="Reference" />
<x-sortable-th key-name="zone_location" label="Zone / Location" />
<x-sortable-th key-name="incident_date" label="Incident Date" />
<x-sortable-th key-name="status" label="Status" />
<th scope="col">Actions</th></tr></thead><tbody>
@forelse($reports as $report)<tr><td><strong>{{$report->tracking_id}}</strong><div class="help">{{ ($report->incident_type??'missed_collection')==='illegal_dumping'?'Illegal dumping':'Missed pickup' }} · {{optional($report->submitted_at)->format('M d, Y')?:$report->submitted_at}}</div></td><td>{{$report->zone?->name}}<div class="help">{{$report->location}}</div></td><td>{{$report->incident_date}}</td><td><span class="pill">{{$report->current_status}}</span></td><td><a class="btn small outline" href="{{route('reports.show',$report)}}">Open report</a></td></tr>
@empty<tr><td colspan="5"><p class="muted">No reports match these filters.</p></td></tr>@endforelse</tbody></table></div>
<div class="pager">{{$reports->links('components.pagination')}}</div></div>
@endsection
