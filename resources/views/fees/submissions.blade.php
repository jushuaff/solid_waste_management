@extends('layouts.app')
@section('title','Verify Online Payments')
@section('content')
<div class="heading">
    <div>
        <span class="eyebrow">GARBAGE FEE COLLECTION · RECEIPT REVIEW</span>
        <h1>Online payment verification</h1>
        <p>Review residents’ GCash and bank transfer evidence before crediting household balances.</p>
    </div>
    <a class="btn outline" href="{{route('fees.index')}}">← Monthly payments</a>
</div>
<div class="notice">
    <strong>Manual verification required.</strong> An uploaded screenshot or receipt does not prove funds were received. Check the payment against the approved receiving account or actual bank/wallet transaction records before selecting Verify. Never rely on a screenshot alone.
</div>
<div class="card" style="margin-bottom:18px">
    <div class="row-between" style="gap:16px;flex-wrap:wrap">
        <div><h2 class="paneltitle" style="margin:0">Transfer receipts</h2>
        <p class="muted" style="margin:5px 0 0">{{number_format($pendingCount)}} submission(s) awaiting verification</p></div>
        <form method="GET" class="filterbar">
            <label class="field">Review status
                <select class="form-control" name="status">
                    @foreach(['pending'=>'Pending verification','verified'=>'Verified','rejected'=>'Rejected','all'=>'All submissions'] as $value=>$label)
                        <option value="{{$value}}" @selected($status===$value)>{{$label}}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn outline" type="submit">Filter</button>
        </form>
    </div>
</div>
<div class="card">
    <div class="fee-review-scroll">
        <table class="content-table fee-review-table">
            <thead>
                <tr>
                    <th>Submission</th><th>Household</th><th>Billing month</th>
                    <th>Amount</th><th>Provider</th><th>Submitted</th><th>Status</th><th>Review</th>
                </tr>
            </thead>
            <tbody>
            @forelse($submissions as $submission)
                <tr>
                    <td><strong>{{$submission->submission_code}}</strong></td>
                    <td>{{$submission->household?->reference??'—'}}
                        <small class="fee-review-small">{{$submission->resident?->name??''}}</small></td>
                    <td>{{$submission->charge?->billing_month??'—'}}</td>
                    <td><strong>{{\App\Services\FeeLedger::money((int)$submission->amount_centavos)}}</strong></td>
                    <td>{{$submission->provider}}<small class="fee-review-small">{{strtoupper($submission->method)}}</small></td>
                    <td>{{$submission->created_at?->format('M j, Y')}}</td>
                    <td><span class="badge {{$submission->status==='pending'?'amber':($submission->status==='rejected'?'grey':'')}}">{{ucwords(str_replace('_',' ',$submission->status))}}</span></td>
                    <td>
                        <details class="fee-review-details">
                            <summary>{{$submission->status==='pending'?'Review':'Details'}}</summary>
                            <div class="fee-review-panel">
                                <p><strong>Transaction reference:</strong> {{$submission->transaction_reference}}</p>
                                <p><strong>External payment date:</strong> {{$submission->paid_on?->format('M j, Y')}}</p>
                                <p><strong>Receiving institution:</strong> {{$submission->provider}}</p>
                                <p><strong>Receiving account name:</strong> {{$submission->account_name}}</p>
                                @if($submission->note)<p><strong>Resident's note:</strong> {{$submission->note}}</p>@endif
                                <a class="btn outline small" target="_blank" rel="noopener noreferrer"
                                    href="{{route('fees.submissions.receipt',$submission->id)}}">
                                    View / download receipt
                                </a>
                                @if($submission->status==='pending')
                                    <p class="muted" style="margin-top:12px">Confirm that funds actually reached the approved bank or wallet account. Verify the reference, amount and date.</p>
                                    <form method="POST" action="{{route('fees.submissions.review',$submission->id)}}" class="fee-review-actions">
                                        @csrf
                                        <label class="field">Review decision
                                            <select class="form-control" name="decision" required>
                                                <option value="">Choose a decision</option>
                                                <option value="verify">Verify — credit household balance</option>
                                                <option value="reject">Reject — request corrected proof</option>
                                            </select>
                                        </label>
                                        <label class="field">Review note <small>(required if rejecting)</small>
                                            <textarea class="form-control" name="review_note" rows="2" maxlength="500"
                                                placeholder="Explain the rejection, or optionally record verification details"></textarea>
                                        </label>
                                        <button class="btn primary" type="submit"
                                            onclick="return confirm('Have you independently verified the external transfer or the reason for rejection?')">
                                            Save decision
                                        </button>
                                    </form>
                                @else
                                    <p><strong>Reviewed:</strong> {{$submission->reviewed_at?->format('M j, Y · g:i A')??'—'}}</p>
                                    @if($submission->review_note)<p><strong>Review notes:</strong> {{$submission->review_note}}</p>@endif
                                @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No online payment submissions match this status.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pager">{{$submissions->links('components.pagination')}}</div>
</div>
@endsection
