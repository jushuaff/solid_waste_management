@extends('layouts.app')
@section('title','Household Linking')
@section('content')
<div class="heading">
    <div>
        <span class="eyebrow">HOUSEHOLD SERVICES</span>
        <h1>{{$staff?'Household link requests':'Link my household'}}</h1>
        <p>{{$staff?'Verify resident identity and household records before granting access.':'Request access to your household billing records. Staff must approve your request before you can pay.'}}</p>
    </div>
    <a class="btn outline" href="{{route('fees.index')}}">Garbage fee payments</a>
</div>
@if(!$staff)
    @if($hasLink)
        <div class="notice">Your account is linked to an active household. You can view issued bills in Garbage Fee Payments.</div>
    @elseif($hasPending)
        <div class="notice">Your household link request is pending verification. You will receive a notification when it is reviewed.</div>
    @else
        <div class="card" style="margin-bottom:18px">
            <h2 class="paneltitle">Request household linking</h2>
            <p class="muted">Provide your household information. If you do not know your household reference, leave it blank. Staff will check your records.</p>
            <form method="POST" action="{{route('fees.household-links.store')}}" class="stack">
                @csrf
                <label class="field">Household reference (if known)
                    <input class="form-control" name="reference" value="{{old('reference')}}" maxlength="100">
                </label>
                <label class="field">House address / location *
                    <input class="form-control" name="location" value="{{old('location')}}" minlength="5" maxlength="250" required>
                </label>
                <label class="field">Additional information (optional)
                    <textarea class="form-control" name="note" maxlength="500" rows="3">{{old('note')}}</textarea>
                </label>
                <button class="btn primary" type="submit">Submit request for verification</button>
            </form>
        </div>
    @endif
@endif
<div class="card">
    <h2 class="paneltitle">{{$staff?'Review requests':'My requests'}}</h2>
    <form method="GET" class="filterbar">
        <label class="field">Status
            <select class="form-control" name="status">
                @foreach(['all'=>'All requests','pending'=>'Pending verification','approved'=>'Approved','rejected'=>'Rejected'] as $value=>$label)
                    <option value="{{$value}}" @selected($status===$value)>{{$label}}</option>
                @endforeach
            </select>
        </label>
        <button class="btn outline" type="submit">Filter</button>
    </form>
    <div style="overflow-x:auto">
        <table class="content-table">
            <thead><tr><th>Submitted</th><th>Resident</th><th>Zone</th><th>Household details</th><th>Status</th><th>Details</th></tr></thead>
            <tbody>
            @forelse($requests as $entry)
                <tr id="link-{{$entry->id}}">
                    <td>{{$entry->created_at?->format('M j, Y')}}</td>
                    <td>{{$entry->resident?->name??'—'}}</td>
                    <td>{{$entry->zone?->name??'—'}}</td>
                    <td>{{$entry->reference??'Reference not provided'}}<small class="fee-review-small">{{$entry->location}}</small></td>
                    <td><span class="badge {{$entry->status==='pending'?'amber':'grey'}}">{{ucfirst($entry->status)}}</span></td>
                    <td>
                        <details @if(request('highlight')===(string)$entry->id) open @endif>
                            <summary>{{$staff && $entry->status==='pending'?'Review':'Details'}}</summary>
                            <p>{{$entry->note??'No additional information.'}}</p>
                            @if($staff && $entry->status==='pending')
                                <p class="muted">Check identity and address against Household Registry. Choose an existing household or register a verified new household directly here.</p>
                                <a class="btn outline small" href="{{route('linis.index','households')}}">Open Household Registry</a>
                                <form method="POST" action="{{route('fees.household-links.review',$entry->id)}}" class="stack">
                                    @csrf
                                    <label class="field">Decision
                                        <select class="form-control" name="decision" required>
                                            <option value="">Choose decision</option>
                                            <option value="approve">Approve link</option>
                                            <option value="reject">Reject request</option>
                                        </select>
                                    </label>
                                    <label class="field">Household linking method (approval only)
                                        <select class="form-control" name="link_mode">
                                            <option value="existing">Link existing household</option>
                                            <option value="new">Register new household and link</option>
                                        </select>
                                    </label>
                                    <label class="field">Existing household in this zone
                                        <select class="form-control" name="household_id">
                                            <option value="">Choose existing household</option>
                                            @foreach($householdsByZone->get((string)$entry->zone_id,collect()) as $householdOption)
                                                @if(!$householdOption->resident_user_id || (string)$householdOption->resident_user_id===(string)$entry->resident_user_id)
                                                    <option value="{{$householdOption->id}}" @selected((string)$householdOption->reference===(string)$entry->reference)>{{$householdOption->reference}} — {{$householdOption->location_description}}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <small class="muted">Choose an existing record only if you selected the existing household method.</small>
                                    </label>
                                    <label class="field">New household reference
                                        <input class="form-control" name="household_reference" maxlength="100" value="{{$entry->reference}}">
                                    </label>
                                    <label class="field">New household verified address
                                        <input class="form-control" name="household_location" maxlength="250" value="{{$entry->location}}">
                                        <small class="muted">For new households only. Confirm the reference and address before registering.</small>
                                    </label>
                                    <label class="checkline">
                                        <input type="checkbox" name="identity_confirmed" value="1">
                                        I verified this resident is authorized to access this household.
                                    </label>
                                    <label class="field">Reason (required if rejecting)
                                        <textarea class="form-control" name="reason" maxlength="500" rows="2"></textarea>
                                    </label>
                                    <button class="btn primary small" type="submit">Save decision</button>
                                </form>
                            @else
                                @if($entry->household_id)
                                    <p>Linked household: {{$entry->household?->reference??'—'}}</p>
                                @endif
                                @if($entry->review_note)
                                    <p>Review note: {{$entry->review_note}}</p>
                                @endif
                            @endif
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{$requests->links('components.pagination')}}</div>
</div>
@endsection
