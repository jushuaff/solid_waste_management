@extends('layouts.guest')
@section('title','Forgot password')
@section('content')
<h2 class="paneltitle" style="font-size:22px">Reset your password</h2><p class="muted">Enter the email associated with your account. Reset links expire after one hour.</p>
<form method="POST" action="{{route('password.email')}}" class="stack">@csrf
<div><label class="form-label">Email address</label><input class="form-control" name="email" type="email" required value="{{old('email')}}"></div>
<button class="btn primary">Send reset link</button></form><p><a href="{{route('login')}}" class="greenText">Return to sign in</a></p>
@endsection
