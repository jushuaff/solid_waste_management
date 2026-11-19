@extends('layouts.print')
@section('title','Published Collection Schedules')
@section('content')
<p>Only schedules currently published and effective are listed. Exception notices should be reviewed before collection.</p>
<table><thead><tr><th>Zone</th><th>Day</th><th>Time</th><th>Waste category</th><th>Method / point</th><th>Instructions</th></tr></thead><tbody>@foreach($schedules as $s)<tr><td>{{$s->zone?->name}}</td><td>{{config('swm.days')[$s->weekday]??''}}</td><td>{{$s->start_time}}–{{$s->end_time}}</td><td>{{$s->category?->local_label}}</td><td>{{config('swm.collection_methods')[$s->collection_method]??''}} @if($s->point) — {{$s->point->name}}@endif</td><td>{{$s->instructions}}</td></tr>@endforeach</tbody></table>
@if($exceptions->isNotEmpty())<h2>Published schedule exceptions</h2><table><thead><tr><th>Affected date</th><th>Action</th><th>Replacement date / time</th><th>Reason</th></tr></thead><tbody>@foreach($exceptions as $e)<tr><td>{{$e->affected_date}}</td><td>{{$e->action}}</td><td>{{$e->replacement_date}} {{$e->replacement_start_time}}–{{$e->replacement_end_time}}</td><td>{{$e->reason}}</td></tr>@endforeach</tbody></table>@endif
@endsection
