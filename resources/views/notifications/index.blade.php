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
            <form method="POST" action="{{ route('notifications.open',$n->id) }}"
                  class="notification-item {{ !$n->is_read?'unread':'' }}">
                @csrf
                <button type="submit" class="notification-open-trigger"
                        aria-label="Open notification: {{ $n->title }}{{ !$n->is_read?' (unread)':'' }}">
                    <span class="notification-item-content">
                        <span class="roundico" aria-hidden="true">◷</span>
                        <span class="listmain">
                            <span class="row-between notification-title">
                                <strong>{{ $n->title }}</strong>
                                @if(!$n->is_read)
                                    <span class="notification-dot notification-indicator" title="Unread" aria-label="Unread"></span>
                                @else
                                    <span class="badge grey">Read</span>
                                @endif
                            </span>
                            <span class="notification-message">{{ $n->message }}</span>
                            <span class="notification-date">{{ $n->created_at?->format('M j, Y · g:i A') }}</span>
                            <span class="notification-view-label">{{ $n->report_id || $n->schedule_id || $n->rule_id || $n->exception_id || $n->zone_id?'View details':'Open notification' }} <span aria-hidden="true">›</span></span>
                        </span>
                    </span>
                </button>
            </form>
        @empty
            <p class="muted">No notifications yet.</p>
        @endforelse
    </div>
    <div class="pager">{{ $notices->links('components.pagination') }}</div>
</div>
@endsection
