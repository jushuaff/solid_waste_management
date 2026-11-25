@extends('layouts.app')
@section('title', ['households'=>'Household Registry','inspections'=>'Pickup Inspections','weigh-ins'=>'MRF Weigh-ins'][$module] ?? 'LINIS')
@section('content')
@php($titles=['households'=>'Household Registry','inspections'=>'Eco-Aide Pickup Inspections','weigh-ins'=>'MRF Waste Weigh-ins'])
@php($mayWrite=($module!=='households' || in_array(auth()->user()->role_code,['superadmin','admin','coordinator'],true)))
<div class="heading"><div><span class="eyebrow">LINIS · ONLINE OPERATIONS</span><h1>{{ $titles[$module] }}</h1>
<p>Data are stored in MongoDB. Updates are audited and deletion archives operational records.</p></div>
@if($mayWrite)<a class="btn primary" href="{{ route('linis.create',$module) }}">＋ New record</a>@endif</div>
<div class="card">
<form class="filterbar" method="GET">
<label class="field">Zone <select class="form-control" name="zone_id"><option value="">All allowed zones</option>
@foreach($zones as $zone)
<option value="{{ $zone->id }}" @selected(request('zone_id')==(string)$zone->id)>{{ $zone->name }}</option>
@endforeach
</select></label>
@if($module!=='households')
<label class="field">From <input class="form-control" type="date" name="from" value="{{ request('from') }}"></label>
<label class="field">To <input class="form-control" type="date" name="to" value="{{ request('to') }}"></label>
@endif
<button class="btn outline">Filter</button><a class="btn" href="{{ route('linis.index',$module) }}">Reset</a>
</form>
<div style="overflow-x:auto"><table class="content-table"><thead><tr><th>Zone</th>
@if($module==='households')<th>Reference</th><th>Location</th><th>Active</th>
@elseif($module==='inspections')<th>Inspection date</th><th>Household</th><th>Segregation</th><th>Evidence</th>
@else<th>Measurement date</th><th>Waste category</th><th>Weight (kg)</th><th>Diverted (kg)</th>
@endif
<th>Actions</th></tr></thead><tbody>
@forelse($records as $row)
<tr><td>{{ $row->zone?->name??'—' }}</td>
@if($module==='households')
<td>{{ $row->reference }}</td><td>{{ $row->location_description }}</td><td>{{ $row->is_active?'Yes':'No' }}</td>
@elseif($module==='inspections')
<td>{{ $row->inspected_at?->format('M j, Y H:i') }}</td><td>{{ $row->household?->reference??'Purok-level' }}</td>
<td><span class="badge {{ $row->segregated?'':'red' }}">{{ $row->segregated?'Segregated':'Non-segregated' }}</span></td>
<td>@if($row->photo_path)<a class="btn small outline" href="{{ route('linis.evidence',$row->id) }}">Private photo</a>@else — @endif</td>
@else
<td>{{ $row->measured_at?->format('M j, Y H:i') }}</td>
<td>{{ $row->category?->local_label??'—' }}</td><td>{{ number_format((float)$row->weight_kg,3) }}</td><td>{{ number_format((float)$row->diverted_kg,3) }}</td>
@endif
<td><div class="actions">@if($mayWrite)<a class="btn small outline" href="{{ route('linis.edit',[$module,$row->id]) }}">Edit</a>
<form method="POST" action="{{ route('linis.destroy',[$module,$row->id]) }}" onsubmit="return confirm('Archive this record? It will no longer contribute to current metrics.')">
@csrf @method('DELETE')<button class="btn small danger">Archive</button></form>@else <span class="muted">Read-only</span> @endif</div></td></tr>
@empty<tr><td colspan="6" class="muted">No records found.</td></tr>@endforelse
</tbody></table></div>
<div class="pager">{{ $records->links('components.pagination') }}</div>
</div>
@endsection
