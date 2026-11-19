@extends('layouts.app')
@section('title','Local Rules & Guidelines')
@section('content')
<div class="heading"><div><span class="eyebrow">BARANGAY GUIDELINES</span><h1>Collection rules and preparation guides</h1><p>Only active, published guidance appears here.</p></div></div>
<div class="stack">
@forelse($rules as $r)<article class="card"><div class="row-between"><h2 class="paneltitle">{{$r->title}}</h2><span class="badge">Published · v{{$r->version??1}}</span></div>
<p class="statlab">{{$r->zone?->name??'All zones'}} · {{$r->category?->local_label??'All waste categories'}}</p>
<div class="rule-body">{{$r->content}}</div>
<p class="footnote">Effective {{$r->effective_from}} @if($r->effective_until) through {{$r->effective_until}} @endif</p>
</article>
@empty<div class="card"><p class="muted">There are no published rules yet. Ask the waste coordinator for collection preparation guidelines.</p></div>@endforelse
</div>
@endsection
