@extends('layouts.app')
@section('title', ($record?'Edit ':'Create ').(['households'=>'Household','inspections'=>'Inspection','weigh-ins'=>'MRF Weigh-in'][$module]))
@section('content')
<div class="heading"><div><span class="eyebrow">LINIS · ONLINE RECORD</span>
<h1>{{ $record?'Edit':'Record' }} {{ ['households'=>'household','inspections'=>'pickup inspection','weigh-ins'=>'MRF weigh-in'][$module] }}</h1>
<p>All records are tied to a collection zone. Do not use actual citizen data in coursework demonstrations.</p></div>
<a class="btn outline" href="{{ route('linis.index',$module) }}">← Back to list</a></div>
<div class="card" style="max-width:950px">
<form method="POST" enctype="multipart/form-data" action="{{ $record?route('linis.update',[$module,$record->id]):route('linis.store',$module) }}">
@csrf @if($record)@method('PUT')@endif
<div class="formgrid">
<div class="field"><label for="zone_id">Zone / Purok *</label>
<select id="zone_id" name="zone_id" required><option value="">Select a zone</option>
@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected((string)old('zone_id',$record?->zone_id)===(string)$zone->id)>{{ $zone->name }}</option>@endforeach
</select></div>
@if($module==='households')
<div class="field"><label for="reference">Household reference code *</label>
<input id="reference" name="reference" value="{{ old('reference',$record?->reference) }}" maxlength="100" required>
<span class="hint">Use a non-identifying local reference (e.g., PUROK-01-H001).</span></div>
<div class="field full"><label for="location_description">General location / landmark *</label>
<input id="location_description" name="location_description" maxlength="250" required value="{{ old('location_description',$record?->location_description) }}"></div>
<div class="field full"><label class="checkline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$record?->is_active??true))> Active household</label></div>
@elseif($module==='inspections')
<div class="field"><label for="inspected_at">Inspected at *</label>
<input id="inspected_at" type="datetime-local" name="inspected_at" required value="{{ old('inspected_at',$record?->inspected_at?->format('Y-m-d\TH:i')??now()->format('Y-m-d\TH:i')) }}"></div>
<div class="field"><label for="household_id">Household (optional)</label><select name="household_id" id="household_id">
<option value="">Purok-wide inspection</option>
@foreach($households as $household)<option value="{{ $household->id }}" @selected((string)old('household_id',$record?->household_id)===(string)$household->id)>{{ $household->reference }} · {{ $household->zone?->name }}</option>@endforeach
</select></div>
<div class="field"><label for="segregated">Segregation observed *</label>
<select id="segregated" name="segregated" required>
<option value="1" @selected((string)old('segregated',$record?->segregated===null?'1':(int)$record?->segregated)==='1')>Properly segregated</option>
<option value="0" @selected((string)old('segregated',(int)$record?->segregated)==='0')>Not segregated</option>
</select></div>
<div class="field"><label for="photo">Optional violation evidence</label>
<input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><span class="hint">JPG/PNG/WebP up to 4 MB; staff-only download.</span></div>
<div class="field full"><label for="note">Inspection notes</label><textarea name="note" id="note" rows="4">{{ old('note',$record?->note) }}</textarea></div>
@else
<div class="field"><label for="category_id">Waste category *</label><select id="category_id" name="category_id" required>
<option value="">Select category</option>
@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id',$record?->category_id)===(string)$category->id)>{{ $category->local_label }}</option>@endforeach
</select></div>
<div class="field"><label for="measured_at">Date and time of weigh-in *</label>
<input type="datetime-local" id="measured_at" name="measured_at" value="{{ old('measured_at',$record?->measured_at?->format('Y-m-d\TH:i')??now()->format('Y-m-d\TH:i')) }}" required></div>
<div class="field"><label for="weight_kg">Total received weight (kg) *</label>
<input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0.001" required value="{{ old('weight_kg',$record?->weight_kg) }}"></div>
<div class="field"><label for="diverted_kg">Amount diverted (kg) *</label>
<input id="diverted_kg" name="diverted_kg" type="number" step="0.001" min="0" required value="{{ old('diverted_kg',$record?->diverted_kg??0) }}">
<span class="hint">Must be less than or equal to the received weight.</span></div>
<div class="field full"><label for="note">Notes / disposition</label><textarea id="note" name="note" rows="4">{{ old('note',$record?->note) }}</textarea></div>
@endif
<div class="full formfoot"><p class="muted">Edits preserve a revision trail; archive keeps historical records.</p><button class="btn primary">{{ $record?'Save changes':'Save record' }}</button></div>
</div></form></div>
@endsection
