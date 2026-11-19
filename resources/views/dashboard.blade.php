@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<div class="heading"><div><span class="eyebrow">COLLECTION & MONITORING</span><h1>Hello, {{auth()->user()->name}} 👋</h1><p>View collection schedules, assigned tasks, and recent report activity.</p></div>@if(auth()->user()->role_code==='resident')<a class="btn primary" href="{{route('reports.create')}}">＋ Report missed pickup</a>@endif</div>
<div class="cards3">
@foreach(['Under review'=>$counts['under_review'],'Active tasks'=>$counts['active'],'Resolved reports'=>$counts['resolved']] as $label=>$count)
<div class="card"><div class="statlab">{{$label}}</div><div class="statval">{{number_format($count)}}</div><div class="statsub">Live MongoDB records</div></div>
@endforeach
</div>
<div class="cols"><div class="stack"><div class="card"><div class="row-between"><h2 class="paneltitle">Published collection schedules</h2><a href="{{route('schedules.index')}}" class="btn small outline">All schedules</a></div>
@if($schedules->isEmpty())<p class="muted">No published schedules yet. An authorized coordinator can add them.</p>@endif
@foreach($schedules as $s)<div class="listrow"><span class="roundico">▣</span><div class="listmain"><b>{{config('swm.days')[$s->weekday] ?? $s->weekday}} · {{$s->start_time}}–{{$s->end_time}}</b><small>{{$s->zone?->name}} · {{$s->category?->local_label}} · {{config('swm.collection_methods')[$s->collection_method] ?? $s->collection_method}}</small></div></div>@endforeach
</div>
<div class="card"><div class="row-between"><h2 class="paneltitle">Recent reports</h2><a href="{{route('reports.index')}}" class="btn small outline">View all</a></div>
@if($recent->isEmpty())<p class="muted">No reports found for your role.</p>@endif
@foreach($recent as $r)<div class="listrow"><span class="roundico">◎</span><div class="listmain"><b><a href="{{route('reports.show',$r)}}">{{$r->tracking_id}}</a></b><small>{{$r->zone?->name}} · {{$r->location}}</small></div><span class="badge">{{$r->current_status}}</span></div>@endforeach
</div></div>
<div class="stack"><div class="card"><h2 class="paneltitle">Quick guide</h2><div class="listrow"><span class="roundico">1</span><div class="listmain"><b>Check schedules</b><small>Day, time, waste type and collection method</small></div></div><div class="listrow"><span class="roundico">2</span><div class="listmain"><b>Read local rules</b><small>Understand what to prepare and where</small></div></div><div class="listrow"><span class="roundico">3</span><div class="listmain"><b>Report and track</b><small>Monitor a missed pickup through closure</small></div></div></div>
<div class="card"><div class="row-between"><h2 class="paneltitle">Recent notifications</h2><a class="btn small outline" href="{{route('notifications.index')}}">Open inbox</a></div>
@forelse($notices as $n)<div class="listrow"><div class="listmain"><b>{{$n->title}}</b><small>{{$n->message}}</small></div></div>@empty<p class="muted">No notifications yet.</p>@endforelse</div></div></div>
@endsection
