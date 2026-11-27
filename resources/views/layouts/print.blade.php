<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>@yield('title') | {{ config('app.name') }}</title>
<style>
@page { margin: 18mm 14mm; }
* { box-sizing: border-box; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.38; color:#193b37; }
header { border-bottom: 2px solid #16745e; padding: 0 0 12px; margin-bottom: 19px; }
header .system { font-size: 8pt; text-transform: uppercase; color: #16745e; font-weight: bold; margin: 0 0 5px; }
h1 { font-size: 17pt; line-height:1.2; margin: 0 0 6px; }
h2 { font-size: 11pt; border-bottom: 1px solid #cbded6; margin: 18px 0 7px; padding-bottom: 6px; }
p { margin: 6px 0 10px; overflow-wrap: break-word; }
.muted, .foot { color:#5b716b; }
table { width:100%; border-collapse:collapse; font-size:8pt; margin:10px 0 18px; table-layout:auto; }
th, td { text-align:left; border:1px solid #d3ded9; padding:6px; vertical-align:top; overflow-wrap:break-word; }
th { background:#e8f4ef; font-weight:bold; }
thead { display:table-header-group; }
tr { page-break-inside:avoid; }
.status { font-weight:bold; color:#16745e; }
.foot { font-size:7.5pt; border-top:1px solid #d3ded9; padding-top:9px; margin-top:22px; }
.break { page-break-before:always; }
</style>
</head>
<body>
<header>
<p class="system">Barangay Solid Waste Collection &amp; Monitoring System</p>
<h1>@yield('title')</h1>
<p class="muted">Generated {{now()->format('F j, Y - g:i A')}} · {{config('app.name')}}</p>
</header>
@yield('content')
<p class="foot">Computer-generated PDF. Confirm all figures and records against approved barangay data before official use.</p>
</body>
</html>
