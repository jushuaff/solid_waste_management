@extends('layouts.app')
@section('title','My Profile')
@section('content')
<div class="heading"><div><span class="eyebrow">ACCOUNT</span><h1>My profile</h1><p>Update account details and your password.</p></div></div>
<div class="grid2">
<div class="card"><h2 class="paneltitle">Personal details</h2><form action="{{route('profile.update')}}" method="POST" class="stack">@csrf @method('PUT')
<div><label class="form-label">Name</label><input class="form-control" name="name" value="{{old('name',$user->name)}}" required></div>
<div><label class="form-label">Email (managed by admin)</label><input class="form-control" disabled value="{{$user->email}}"></div>
<div><label class="form-label">Contact number (optional)</label><input class="form-control" name="contact_number" value="{{old('contact_number',$user->contact_number)}}"></div>
<button class="btn primary">Save profile</button></form></div>
<div class="card"><h2 class="paneltitle">Change password</h2><form action="{{route('profile.password')}}" method="POST" class="stack">@csrf @method('PUT')
<div><label class="form-label">Current password</label><input class="form-control" name="current_password" type="password" required></div>
<div><label class="form-label">New password (12+ characters)</label><input class="form-control" name="password" type="password" required></div>
<div><label class="form-label">Confirm new password</label><input class="form-control" name="password_confirmation" type="password" required></div>
<button class="btn primary">Change password</button></form></div>
</div>
@endsection
