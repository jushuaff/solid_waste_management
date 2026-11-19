@extends('layouts.guest')
@section('title','New password')
@section('content')
<h2 class="paneltitle" style="font-size:22px">Set a new password</h2>
<form method="POST" action="{{route('password.update')}}" class="stack">@csrf
<input type="hidden" name="token" value="{{$token}}">
<div><label class="form-label">Email</label><input class="form-control" type="email" name="email" required value="{{old('email',$email)}}"></div>
<div><label class="form-label">New password (12+ characters)</label><input class="form-control" name="password" type="password" required></div>
<div><label class="form-label">Confirm new password</label><input class="form-control" name="password_confirmation" type="password" required></div>
<button class="btn primary">Reset password</button></form>
@endsection
