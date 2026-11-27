@extends('layouts.app')
@section('title','Collection Schedules')
@section('content')
<div class="heading"><div><span class="eyebrow">YOUR AREA & WASTE TYPE</span><h1>Collection schedules</h1><p>Published days, hours, collection methods and handover instructions.</p></div><div class="actions">
<a class="btn outline printlink" target="_blank" rel="noopener" href="{{route('schedules.print',request()->query())}}">Preview PDF</a>
<a class="btn outline printlink" href="{{route('schedules.print',array_merge(request()->query(),['download'=>1]))}}">Download PDF</a>
</div></div>
<div class="notice">Each barangay can configure its own days, accepted categories, designated collection points, truck-handover or curbside collection and special schedules.</div>
<div class="card" style="margin-bottom:20px"><form method="GET" class="filterbar" style="flex-wrap:wrap">
@if(!$isResident)<div class="field"><label for="zone_id">Collection zone</label><select id="zone_id" name="zone_id" class="form-control"><option value="">All zones</option>@foreach($zones as $z)<option value="{{$z->id}}" @selected($zone===$z->id)>{{$z->name}}</option>@endforeach</select></div>@else<div><span class="statlab">My zone</span><div class="statval" style="font-size:18px">{{auth()->user()->zone?->name ?? 'Not assigned'}}</div></div>@endif
<div class="field"><label for="category_id">Waste category</label><select name="category_id" id="category_id" class="form-control"><option value="">All categories</option>@foreach($categories as $c)<option value="{{$c->id}}" @selected($category===$c->id)>{{$c->local_label}}</option>@endforeach</select></div>
<button class="btn primary" style="align-self:end">Filter</button></form></div>
<div class="scheduleGrid">
@forelse($schedules as $s)<article class="daycard"><span class="badge">{{ucfirst($s->publication_status)}}</span><h3>{{config('swm.days')[$s->weekday]??'Day'}}</h3><div class="date">{{$s->zone?->name}} · {{$s->category?->local_label}}</div><p><strong>{{config('swm.collection_methods')[$s->collection_method]??$s->collection_method}}</strong></p><div class="time">◷ {{$s->start_time}} – {{$s->end_time}}</div>
@if($s->point)<p><strong>Collection point:</strong> {{$s->point->name}} — {{$s->point->landmark}}</p>@endif
@if($s->instructions)<p>{{$s->instructions}}</p>@endif
<small class="muted">Effective: {{$s->effective_from}} to {{$s->effective_until?:'until revised'}}</small></article>
@empty<div class="card" style="grid-column:1/-1"><p class="muted">No published collection schedule matches this filter yet. Contact barangay staff for the latest official guidance.</p></div>@endforelse
</div>
@if($exceptions->isNotEmpty())<div class="card" style="margin-top:22px"><h2 class="paneltitle">Special collection changes</h2><div class="stack">@foreach($exceptions as $e)<div class="notice"><strong>{{strtoupper(str_replace('_',' ',$e->action))}} · {{$e->affected_date}}</strong> — {{$e->reason}} @if($e->replacement_date) | New date: {{$e->replacement_date}} @endif @if($e->replacement_start_time) | New time: {{$e->replacement_start_time}}–{{$e->replacement_end_time}} @endif</div>@endforeach</div></div>@endif
<div class="card" style="margin-top:22px"><h2 class="paneltitle">Know the local rules</h2><p class="muted">Read published guidance on segregation, placement and acceptable waste before preparing your garbage.</p><a href="{{route('rules.index')}}" class="btn primary">Read rules & guidelines →</a></div>
@endsection
