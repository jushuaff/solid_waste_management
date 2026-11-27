@extends('layouts.app')
@section('title','Report an Incident')
@section('content')
<div class="heading"><div><span class="eyebrow">INCIDENT REPORTING</span><h1>Report an incident</h1><p>Let the barangay know about a missed collection or improper waste disposal in your area.</p></div><a class="btn outline" href="{{route('schedules.index')}}">Check schedule →</a></div>
<div class="split"><div class="card"><h2 class="paneltitle">Collection details</h2><form method="POST" action="{{route('reports.store')}}" enctype="multipart/form-data" class="stack">@csrf
<div><label class="form-label" for="incident_type">Concern type *</label>
<select name="incident_type" id="incident_type" class="form-control" required>
<option value="missed_collection" @selected(old('incident_type','missed_collection')==='missed_collection')>Missed scheduled collection</option>
<option value="illegal_dumping" @selected(old('incident_type')==='illegal_dumping')>Illegal dumping / improper disposal</option>
</select><p class="help">Choose the concern that best describes what happened.</p></div>
<div><label class="form-label">Your assigned collection zone</label><select name="zone_id" class="form-control" required>@foreach($zones as $zone)@if((string)$zone->id===(string)auth()->user()->zone_id)<option value="{{$zone->id}}">{{$zone->name}}</option>@endif @endforeach</select></div>
<div><label class="form-label">Waste category *</label><select name="category_id" class="form-control" required><option value="">Select category</option>@foreach($categories as $cat)<option value="{{$cat->id}}" @selected(old('category_id')===$cat->id)>{{$cat->local_label}}</option>@endforeach</select></div>
<div><label class="form-label">Applicable collection schedule (optional)</label><select name="schedule_id" class="form-control"><option value="">Not sure / other collection issue</option>@foreach($schedules as $s)<option value="{{$s->id}}" @selected(old('schedule_id')===$s->id)>{{config('swm.days')[$s->weekday]}} {{$s->start_time}} · {{$s->category?->local_label}}</option>@endforeach</select></div>
<div><label class="form-label">Incident date *</label><input name="incident_date" class="form-control" type="date" max="{{now()->toDateString()}}" value="{{old('incident_date',now()->toDateString())}}" required></div>
<div><label class="form-label">Street, collection point or landmark *</label><input name="location" class="form-control" value="{{old('location')}}" maxlength="250" required placeholder="e.g., Main collection point near barangay hall"></div>
<div><label class="form-label">Incident map pin (required for illegal dumping)</label>
<div class="grid2"><div><label class="form-label" for="latitude">Latitude</label><input class="form-control" id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude') }}" placeholder="16.4123"></div>
<div><label class="form-label" for="longitude">Longitude</label><input class="form-control" id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude') }}" placeholder="120.5944"></div></div>
<div class="actions" style="margin-top:8px"><button class="btn outline" type="button" id="loadIncidentMap">Choose a pin on map</button><button class="btn outline" type="button" id="useIncidentLocation">Use my current location</button></div>
<div id="incidentMap" style="display:none;height:280px;width:100%;margin:12px 0;border-radius:10px" aria-label="Map pin selector"></div>
<p class="help">An online map uses OpenStreetMap tiles. You can instead enter coordinates manually; location access is optional and only requested when selected.</p>
</div>
<div><label class="form-label">Description *</label><textarea class="form-control" name="description" required placeholder="What happened? What kind of waste and where was it placed?">{{old('description')}}</textarea><p class="help">Avoid unnecessary personal information or other residents' names.</p></div>
@include('reports.partials.evidence-upload')
<label class="checkline"><input type="checkbox" name="privacy" value="1" required> I understand that my report and attachments will be reviewed by authorized barangay personnel for case handling.</label>
<button class="btn primary">Submit incident report</button></form></div>
<div class="stack"><div class="card"><h2 class="paneltitle">Before you submit</h2><div class="listrow"><span class="roundico">1</span><div class="listmain"><b>Check the schedule</b><small>Review applicable day, time and waste type.</small></div></div><div class="listrow"><span class="roundico">2</span><div class="listmain"><b>Confirm your collection method</b><small>Collection point, meet-the-truck or curbside.</small></div></div><div class="listrow"><span class="roundico">3</span><div class="listmain"><b>Save your tracking reference</b><small>After submission, updates appear in your report history.</small></div></div></div><div class="notice">Please keep your tracking reference after submitting. You can follow your report's progress from Reports &amp; Tracking.</div></div>
</div>
@section('scripts')
<script>
(function(){
 const lat=document.getElementById('latitude'),lon=document.getElementById('longitude');
 const incident=document.getElementById('incident_type');
 const mapArea=document.getElementById('incidentMap');
 let map=null,marker=null,loaded=false;
 function syncRequired(){
   const needed=incident.value==='illegal_dumping';
   lat.required=needed;lon.required=needed;
 }
 incident.addEventListener('change',syncRequired);syncRequired();
 function place(latitude,longitude) {
   lat.value=Number(latitude).toFixed(6);lon.value=Number(longitude).toFixed(6);
   if(map){map.setView([latitude,longitude],15);if(marker)marker.setLatLng([latitude,longitude]);else marker=L.marker([latitude,longitude]).addTo(map);}
 }
 function displayMap(){
   mapArea.style.display='block';
   if(map){map.invalidateSize();return;}
   if(!window.L || !loaded) return;
   const latitude=parseFloat(lat.value)||16.4123, longitude=parseFloat(lon.value)||120.5944;
   map=L.map(mapArea).setView([latitude,longitude],13);
   L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
     maxZoom:19, attribution:'&copy; OpenStreetMap contributors'
   }).addTo(map);
   map.on('click',function(e){place(e.latlng.lat,e.latlng.lng);});
   if(lat.value&&lon.value) place(latitude,longitude);
   setTimeout(()=>map.invalidateSize(),100);
 }
 document.getElementById('loadIncidentMap').addEventListener('click',function(){
    if(window.L){loaded=true;displayMap();return;}
    const stylesheet=document.createElement('link');stylesheet.rel='stylesheet';stylesheet.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(stylesheet);
    const script=document.createElement('script');script.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.onload=function(){loaded=true;displayMap();};
    script.onerror=function(){alert('Map service could not load. Please enter coordinates manually.');};
    document.head.appendChild(script);
 });
 document.getElementById('useIncidentLocation').addEventListener('click',function(){
   if(!navigator.geolocation){alert('Location is unavailable. Enter coordinates manually.');return;}
   navigator.geolocation.getCurrentPosition(function(pos){place(pos.coords.latitude,pos.coords.longitude);},function(){
     alert('Location permission was denied or unavailable. You may enter coordinates manually.');
   },{enableHighAccuracy:true,timeout:12000});
 });
})();
</script>
@endsection

@endsection
