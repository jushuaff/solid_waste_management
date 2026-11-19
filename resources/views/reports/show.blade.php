@extends('layouts.app')
@section('title','Report '.$report->tracking_id)
@section('content')
<div class="heading"><div><span class="eyebrow">REPORT TRACKING</span><h1>{{$report->tracking_id}}</h1><p>Case status, assigned personnel and recorded outcome.</p></div><div class="actions"><span class="badge">{{$report->current_status}}</span><a target="_blank" class="btn outline" href="{{route('reports.print',$report)}}">🖨 Print this report</a></div></div>
<div class="actions" style="margin-bottom:15px">
@if(auth()->user()->role_code==='resident' && $report->current_status==='Under Review')<a class="btn outline" href="{{route('reports.edit',$report)}}">Edit pending report</a>@endif
@if(auth()->user()->canAdmin() && in_array($report->current_status,['Resolved','Closed','Rejected'],true) && !$report->archived_at)
<form method="POST" action="{{route('reports.archive',$report)}}" onsubmit="return confirm('Archive finalized report? The history will be retained.')">@csrf @method('DELETE')<button class="btn red">Archive record</button></form>
@endif
</div>
<div class="split"><div class="stack"><article class="card"><h2 class="paneltitle">Collection concern</h2>
<dl class="info-list"><dt>Tracking ID</dt><dd>{{$report->tracking_id}}</dd><dt>Status</dt><dd>{{$report->current_status}}</dd><dt>Collection zone</dt><dd>{{$report->zone?->name}}</dd><dt>Waste category</dt><dd>{{$report->category?->local_label}}</dd><dt>Incident date</dt><dd>{{$report->incident_date}}</dd><dt>Location / landmark</dt><dd>{{$report->location}}</dd><dt>Concern</dt><dd>{{$report->description}}</dd>
@if($report->photo_path)<dt>Photo evidence</dt><dd><a class="greenText" href="{{route('reports.evidence',$report)}}">Download attached photo</a></dd>@endif</dl></article>
<article class="card"><h2 class="paneltitle">Status history</h2>
<div class="timeline">@foreach($history as $event)<div class="event"><strong>{{$event->to_status}}</strong><time>{{ $event->changed_at?->format('M d, Y · h:i A') ?? $event->changed_at }} · {{$event->actor_type==='system'?'System':'Authorized user'}}</time><p>{{$event->reason}}</p></div>@endforeach</div>
</article>
@if($assignments->isNotEmpty())<article class="card"><h2 class="paneltitle">Assignment history</h2>@foreach($assignments as $a)<div class="listrow"><span class="roundico">▤</span><div class="listmain"><b>{{$a->crew?->name??'Crew member'}}</b><small>Assigned: {{$a->assigned_at}} | {{$a->outcome?:'Pending'}}</small><small>{{$a->outcome_note}}</small></div></div>@endforeach</article>@endif</div>
<div class="stack">
@if(in_array(auth()->user()->role_code,['coordinator','admin','superadmin','crew'],true))
<article class="card"><h2 class="paneltitle">Authorized workflow actions</h2><p class="muted">Only actions allowed by your role and the report's current status may be submitted.</p>
<form method="POST" action="{{route('reports.workflow',$report)}}" class="stack">@csrf
@if(in_array(auth()->user()->role_code,['coordinator','admin','superadmin'],true))
@if(in_array($report->current_status,['Under Review','Verified','Exception'],true))
<div><label class="form-label">Collection crew</label><select class="form-control" name="crew_user_id"><option value="">Choose crew member</option>@foreach($crews as $crew)<option value="{{$crew->id}}">{{$crew->name}} {{ $crew->zone?->name?'— '.$crew->zone->name:'' }}</option>@endforeach</select></div>
@endif
<div><label class="form-label">Coordinator decision</label><select name="action" class="form-control" required>
@if(in_array($report->current_status,['Under Review','Verified'],true))<option value="assign">Verify & assign</option>@endif
@if($report->current_status==='Under Review')<option value="reject">Reject with reason</option>@endif
@if($report->current_status==='Exception')<option value="reassign">Reassign crew</option><option value="close">Close with documented reason</option>@endif
</select></div>
@elseif(auth()->user()->role_code==='crew' && in_array($report->current_status,['Assigned','In Progress'],true))
<div><label class="form-label">Crew action</label><select name="action" class="form-control">@if($report->current_status==='Assigned')<option value="start">Start work</option>@endif @if($report->current_status==='In Progress')<option value="resolve">Resolved — collected</option><option value="exception">Exception — could not collect</option>@endif</select></div>
@endif
<div><label class="form-label">Decision / outcome note *</label><textarea name="reason" class="form-control" required placeholder="Describe the verification, task outcome or reason for closure..."></textarea></div><button class="btn primary">Save status update</button></form>
</article>
@endif
<div class="card"><h2 class="paneltitle">What happens next?</h2><p class="muted">The coordinator assesses submitted reports, assigns verified concerns, and reviews exceptions. Every permitted status change is recorded in the audit history.</p><a class="btn outline" href="{{route('reports.index')}}">← Return to report list</a></div>
</div></div>
@endsection
