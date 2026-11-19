@extends('layouts.app')
@section('title','Notifications')
@section('content')
<div class="heading"><div><span class="eyebrow">IN-APP UPDATES</span><h1>Notifications</h1><p>Report progress, schedule changes and published local guidelines.</p></div></div>
<div class="card">@forelse($notices as $n)<div class="listrow"><span class="roundico">◷</span><div class="listmain"><b>{{$n->title}}</b><small>{{$n->message}}</small><small>{{$n->created_at}}</small></div>@if(!$n->is_read)<form method="POST" action="{{route('notifications.read',$n->id)}}">@csrf<button class="btn small outline">Mark read</button></form>@else<span class="badge grey">Read</span>@endif</div>
@empty<p class="muted">No notifications yet.</p>@endforelse<div class="pager">{{$notices->links('components.pagination')}}</div></div>
@endsection
