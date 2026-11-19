<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','Sign in') | Barangay SWM</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}"></head>
<body><div style="min-height:100vh;background:linear-gradient(135deg,#0b2526 0%,#176a57 100%);display:grid;place-items:center;padding:25px"><div style="width:100%;max-width:460px"><div style="text-align:center;color:white;margin-bottom:20px"><div style="font-size:42px">♻</div><h1 style="margin:8px 0">Barangay SWM</h1><p style="opacity:.85">Waste Collection &amp; Monitoring</p></div><div class="card" style="padding:30px">
@if(session('status'))<div class="alert good">{{session('status')}}</div>@endif
@if($errors->any())<div class="alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@yield('content')
</div><p style="text-align:center;color:#c5e8da;font-size:12px;margin-top:20px">Configurable scheduling, local rules and report tracking</p></div></div></body></html>
