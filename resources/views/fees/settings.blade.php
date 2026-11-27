@extends('layouts.app')
@section('title','City Garbage Fee Settings')
@section('content')
<div class="heading">
    <div>
        <span class="eyebrow">SUPERADMINISTRATION · CITY POLICY</span>
        <h1>Garbage fee settings</h1>
        <p>Set monthly household rates by city and assign the collection zones covered by each city.</p>
    </div>
    <a class="btn outline" href="{{ route('fees.index') }}">Monthly payments</a>
</div>
<div class="notice">
    Rates are effective from the month you specify and only affect newly generated charges. Previous household charges and recorded payments remain unchanged.
    Confirm each applicable fee and ordinance with the adopting city before collection.
</div>
<div class="card" style="margin-bottom:20px">
    <h2 class="paneltitle">Add a city</h2>
    <form method="POST" action="{{ route('fees.settings.cities.store') }}" class="filterbar">
        @csrf
        <label class="field">City / municipality
            <input class="form-control" name="name" maxlength="120" value="{{old('name')}}" placeholder="e.g., Baguio City" required>
        </label>
        <label class="field">Region
            <input class="form-control" name="region" maxlength="120" value="{{old('region')}}" placeholder="e.g., CAR" required>
        </label>
        <button class="btn primary" type="submit">Add city</button>
    </form>
</div>

@forelse($cities as $city)
<div class="card" style="margin-bottom:20px" id="city-{{ $city->id }}">
    <div class="row-between">
        <div>
            <span class="eyebrow">CITY POLICY</span>
            <h2 class="paneltitle" style="font-size:19px;margin:8px 0 4px">{{ $city->name }}</h2>
            <p class="muted" style="margin:0">{{ $city->region }} · {{ $city->zones->count() }} assigned zone(s)</p>
        </div>
        <span class="badge">Household rates</span>
    </div>
    <div class="grid2" style="margin-top:20px">
        <section>
            <h3 class="paneltitle">Effective monthly fees</h3>
            <div style="overflow-x:auto">
                <table class="content-table">
                    <thead><tr><th>Effective from</th><th>Monthly fee</th><th>Legal basis</th></tr></thead>
                    <tbody>
                    @forelse($city->rates->sortByDesc('effective_month') as $rate)
                        <tr><td>{{ $rate->effective_month }}</td>
                        <td>₱{{ number_format($rate->amount_centavos/100,2) }}</td>
                        <td>{{ $rate->ordinance_reference }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="muted">No household rate configured.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <h3 class="paneltitle" style="margin-top:18px">Set a new effective rate</h3>
            <form method="POST" action="{{ route('fees.settings.rates.store',$city->id) }}" class="stack">
                @csrf
                <div class="grid2">
                    <div class="field">
                        <label>Effective month *</label>
                        <input class="form-control" type="month" name="effective_month" value="{{ now()->format('Y-m') }}" required>
                    </div>
                    <div class="field">
                        <label>Monthly fee (PHP) *</label>
                        <input class="form-control" type="number" name="amount" min="0.01" max="9999999.99" step="0.01" placeholder="0.00" required>
                    </div>
                </div>
                <div class="field">
                    <label>Ordinance / approved fee reference *</label>
                    <input class="form-control" name="ordinance_reference" maxlength="250" placeholder="Approved city fee ordinance or resolution" required>
                </div>
                <div class="field">
                    <label>Notes (optional)</label>
                    <textarea class="form-control" name="notes" rows="2" maxlength="800"></textarea>
                </div>
                <p class="muted">Each rate version is retained. To revise a rate, create a new effective month. Existing charges are not recalculated.</p>
                <button class="btn primary" type="submit">Save monthly rate</button>
            </form>
        </section>
        <section>
            <h3 class="paneltitle">Assigned collection zones</h3>
            <p class="muted">Assign the puroks belonging to this city. Only active households within assigned zones receive monthly charges.</p>
            <form method="POST" action="{{ route('fees.settings.zones.update',$city->id) }}" class="stack">
                @csrf @method('PUT')
                <div class="fee-zone-choices">
                    @forelse($zones as $zone)
                    <label class="checkline">
                        <input type="checkbox" name="zone_ids[]" value="{{ $zone->id }}"
                            @checked((string)$zone->fee_city_id===(string)$city->id)
                            @disabled($zone->fee_city_id && (string)$zone->fee_city_id!==(string)$city->id)>
                        <span>{{ $zone->name }}
                        @if($zone->fee_city_id && (string)$zone->fee_city_id!==(string)$city->id)
                        <small class="muted">(Assigned to another city)</small>
                        @endif</span>
                    </label>
                    @empty
                        <p class="muted">Create collection zones before assigning a city.</p>
                    @endforelse
                </div>
                <p class="muted">To move a zone between cities, unassign it from its current city first. Historical monthly charges stay linked to their original city.</p>
                <button class="btn outline" type="submit">Save zone assignments</button>
            </form>
        </section>
    </div>
</div>
@empty
<div class="card"><p class="muted">No cities configured yet. Add the first city above to begin setting household fees.</p></div>
@endforelse
@endsection
