<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/password-toggle.js') }}" defer></script>
    <script src="{{ asset('assets/incident-evidence.js') }}" defer></script>
</head>
<body>
<div class="app">
    <aside class="side" id="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="mark">♻</span><div class="logo-line">BARANGAY SWM<small>Collection &amp; Monitoring</small></div></a>
        <nav class="site-nav">
            <div class="navHead">GENERAL</div>
            <a class="navitem {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="ico">▦</span> Dashboard</a>
            <a class="navitem {{ request()->routeIs('schedules.*') ? 'active' : '' }}" href="{{ route('schedules.index') }}"><span class="ico">▣</span> Collection Schedules</a>
            <a class="navitem {{ request()->routeIs('rules.*') ? 'active' : '' }}" href="{{ route('rules.index') }}"><span class="ico">☷</span> Rules &amp; Guidelines</a>
            @if(auth()->user()->role_code!=='eco_aide')
            <a class="navitem {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><span class="ico">◎</span> Reports &amp; Tracking</a>
            @endif
            @if(in_array(auth()->user()->role_code,['superadmin','admin','coordinator','eco_aide','crew'],true))
                <div class="navHead">LINIS · ECOLOGICAL MONITORING</div>
                @if(in_array(auth()->user()->role_code,['superadmin','admin','coordinator','eco_aide'],true))
                <a class="navitem {{ request()->routeIs('linis.dashboard')?'active':'' }}" href="{{ route('linis.dashboard') }}"><span class="ico">◉</span> Compliance Dashboard</a>
                @endif
                <a class="navitem {{ request()->is('linis/inspections*')?'active':'' }}" href="{{ route('linis.index','inspections') }}"><span class="ico">✓</span> Pickup Inspections</a>
                @if(auth()->user()->role_code!=='crew')
                <a class="navitem {{ request()->is('linis/weigh-ins*')?'active':'' }}" href="{{ route('linis.index','weigh-ins') }}"><span class="ico">▤</span> MRF Weigh-ins</a>
                @endif
                <a class="navitem {{ request()->is('linis/households*')?'active':'' }}" href="{{ route('linis.index','households') }}"><span class="ico">▦</span> Household Registry</a>
            @endif
            @if(auth()->user()->role_code === 'resident')
                <a class="navitem" href="{{ route('reports.create') }}"><span class="ico">＋</span> Report an Incident</a>
            @endif
            <a class="navitem" href="{{ route('notifications.index') }}"><span class="ico">◷</span> Notifications</a>
            @if(auth()->user()->canManage())
                <div class="navHead">BARANGAY OPERATIONS</div>
                @foreach(['schedules'=>'Manage Schedules','exceptions'=>'Schedule Changes','rules'=>'Publish Local Rules','points'=>'Collection Points'] as $key=>$label)
                    <a class="navitem {{ request()->is('manage/'.$key.'*')?'active':'' }}" href="{{ route('management.index',$key) }}"><span class="ico">▤</span> {{ $label }}</a>
                @endforeach
                @if(auth()->user()->canAdmin())
                <div class="navHead">ADMINISTRATION</div>
                @foreach(['zones'=>'Collection Zones','categories'=>'Waste Categories','users'=>'User Accounts'] as $key=>$label)
                    <a class="navitem {{ request()->is('manage/'.$key.'*')?'active':'' }}" href="{{ route('management.index',$key) }}"><span class="ico">⚙</span> {{ $label }}</a>
                @endforeach
                @endif
            @endif
            <div class="nav-divider"></div>
            <a class="navitem" href="{{ route('profile.edit') }}"><span class="ico">◉</span> My Profile</a>
        </nav>
        <div class="sidefoot"><b>{{ auth()->user()->name }}</b><br>{{ ucwords(str_replace('_',' ',auth()->user()->role_code)) }}<br>
           <form method="POST" action="{{ route('logout') }}">@csrf <button class="btn small outline" style="margin-top:12px">Sign out</button></form>
        </div>
    </aside>
    <main class="main">
        <header class="topbar"><div style="display:flex;align-items:center;gap:11px">
            <button class="btn small mobilemenu" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Toggle navigation">☰</button>
            <div class="crumb">Barangay SWM / <strong>@yield('title','Dashboard')</strong></div>
            </div><div class="topR"><span class="tag">● {{ ucwords(str_replace('_',' ',auth()->user()->role_code)) }}</span><span class="avatar" title="{{ auth()->user()->name }}">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span></div>
        </header>
        <div class="page">
            @if(session('status'))<div class="alert good" role="status">{{ session('status') }}</div>@endif
            @if(session('warning'))<div class="alert bad" role="alert">{{ session('warning') }}</div>@endif
            @if($errors->any())<div class="alert bad" role="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            @yield('content')
            <p class="footnote">Barangay Solid Waste Collection &amp; Monitoring</p>
        </div>
    </main>
</div>
<script>document.addEventListener('click',function(e){const aside=document.getElementById('sidebar');if(window.innerWidth<690&&aside.classList.contains('open')&&!aside.contains(e.target)&&!e.target.closest('.mobilemenu'))aside.classList.remove('open')});</script>
<script>
(function () {
    const menu = document.querySelector('#sidebar .site-nav');
    if (!menu) return;
    let idleTimer;
    menu.addEventListener('scroll', function () {
        menu.classList.add('is-scrolling');
        window.clearTimeout(idleTimer);
        idleTimer = window.setTimeout(function () {
            menu.classList.remove('is-scrolling');
        }, 900);
    }, { passive: true });
}());
</script>
@yield('scripts')
</body></html>
