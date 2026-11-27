@extends('layouts.app')
@section('title','Amend Report '.$report->tracking_id)
@section('content')
<div class="heading"><div><span class="eyebrow">REPORT AMENDMENT</span><h1>Edit {{ $report->tracking_id }}</h1><p>You can amend a report only before coordinator verification. Changes are retained in the report's amendment history.</p></div><a class="btn outline" href="{{route('reports.show',$report)}}">← Back</a></div>
<div class="card" style="max-width:830px"><form method="POST" action="{{route('reports.update',$report)}}" enctype="multipart/form-data" class="stack">@csrf @method('PUT')
<div><label class="form-label">Concern type</label><select name="incident_type" class="form-control" required>
<option value="missed_collection" @selected(old('incident_type',$report->incident_type??'missed_collection')==='missed_collection')>Missed collection</option>
<option value="illegal_dumping" @selected(old('incident_type',$report->incident_type)==='illegal_dumping')>Illegal dumping</option>
</select></div>
<div><label class="form-label">Incident date</label><input type="date" name="incident_date" class="form-control" max="{{now()->toDateString()}}" value="{{old('incident_date',$report->incident_date)}}" required></div>
<div><label class="form-label">Waste category</label><select class="form-control" name="category_id" required>@foreach($categories as $c)<option value="{{$c->id}}" @selected(old('category_id',$report->category_id)===$c->id)>{{$c->local_label}}</option>@endforeach</select></div>
<div><label class="form-label">Schedule reference (optional)</label><input class="form-control" name="schedule_id" value="{{old('schedule_id',$report->schedule_id)}}" placeholder="Leave blank if unknown"><p class="help">Optional identifier of a matching published schedule.</p></div>
<div><label class="form-label">Street, pickup point or landmark</label><input class="form-control" name="location" value="{{old('location',$report->location)}}" required></div>
<div class="grid2"><div><label class="form-label">Latitude (required for dumping)</label><input class="form-control" type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude',$report->latitude) }}"></div>
<div><label class="form-label">Longitude (required for dumping)</label><input class="form-control" type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude',$report->longitude) }}"></div></div>
<div><label class="form-label">Description</label><textarea name="description" class="form-control" required>{{old('description',$report->description)}}</textarea></div>
@include('reports.partials.evidence-upload',['usage'=>$usage])
<p class="help">You can remove existing attachments from your incident details page while the report is awaiting review.</p>
<button class="btn primary">Save changes to incident</button></form></div>
@endsection
