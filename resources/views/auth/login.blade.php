@extends('layouts.guest')
@section('title','Sign in')
@section('content')
<h2 class="paneltitle" style="font-size:22px">Welcome back</h2><p class="muted">Sign in to your barangay account.</p>
<form method="POST" action="{{ route('login.attempt') }}" class="stack">@csrf
<div><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" autocomplete="username" required value="{{old('email')}}"></div>
<div><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
<label class="checkline"><input type="checkbox" name="remember" value="1"> Remember this device</label>
<button class="btn primary" type="submit">Sign in</button>
</form><div class="row-between" style="margin-top:20px"><a href="{{route('register')}}" class="greenText">Create resident account</a><a href="{{route('password.request')}}" class="greenText">Forgot password?</a></div>
@endsection
