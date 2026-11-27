@extends('layouts.app')
@section('title','Notifications')
@section('content')
<div class="heading">
    <div>
        <span class="eyebrow">RECENT UPDATES</span>
        <h1>Notifications</h1>
        <p>New reports, assigned work, collection schedules and barangay announcements.</p>
    </div>
</div>
<div class="card">
    <div class="notifications-list">
        @forelse($notices as $n)
            <div class="notification-item {{ !$n->is_read?'unread':'' }}">
                <div class="notification-item-content">
                    <span class="roundico" aria-hidden="true">◷</span>
                    <div class="listmain">
                        <div class="row-between">
                            <b>{{ $n->title }}</b>
                            @if(!$n->is_read)
                                <span class="notification-dot notification-indicator" aria-label="Unread"></span>
                            @endif
                        </div>
                        <small>{{ $n->message }}</small>
                        <small>{{ $n->created_at?->format('M j, Y · g:i A') }}</small>
                        <div class="notification-actions" style="margin-top:12px">
                            <form method="POST" action="{{route('notifications.open',$n->id)}}">
                                @csrf
                                <button type="submit" class="btn small {{ !$n->is_read?'primary':'outline' }}">
                                    {{ $n->report_id || $n->schedule_id || $n->rule_id || $n->exception_id || $n->zone_id?'View details':'Open notification' }}
                                </button>
                            </form>
                            @if(!$n->is_read)
                                <form method="POST" action="{{route('notifications.read',$n->id)}}">
                                    @csrf
                                    <button type="submit" class="btn small outline">Mark as read</button>
                                </form>
                            @else
                                <span class="badge grey">Read</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p class="muted">No notifications yet.</p>
        @endforelse
    </div>
    <div class="pager">{{ $notices->links('components.pagination') }}</div>
</div>
@endsection
