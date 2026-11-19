@extends('layouts.guest')
@section('title','Create account')
@section('content')
<h2 class="paneltitle" style="font-size:22px">Resident registration</h2><p class="muted">Staff accounts are created by an administrator.</p>
<form method="POST" action="{{route('register.store')}}" class="stack">@csrf
<div><label class="form-label">Full name</label><input class="form-control" name="name" required value="{{old('name')}}"></div>
<div><label class="form-label">Email</label><input class="form-control" name="email" type="email" required value="{{old('email')}}"></div>
<div><label class="form-label">Collection zone</label><select class="form-control" name="zone_id" required><option value="">Choose zone</option>@foreach($zones as $zone)<option value="{{$zone->id}}" @selected(old('zone_id')===$zone->id)>{{$zone->name}}</option>@endforeach</select></div>
<div><label class="form-label">Password (12+ characters)</label><input class="form-control" name="password" type="password" autocomplete="new-password" required></div>
<div><label class="form-label">Confirm password</label><input class="form-control" name="password_confirmation" type="password" autocomplete="new-password" required></div>
<label class="checkline"><input type="checkbox" name="privacy" value="1" required> I understand that my name, email, assigned zone and submitted concerns will be processed by authorized barangay personnel for account and case management. See the privacy information provided by the adopting barangay.</label>
<button class="btn primary">Create account</button></form><p><a href="{{route('login')}}" class="greenText">Back to login</a></p>
@endsection
