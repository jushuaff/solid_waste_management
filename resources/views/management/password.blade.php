@extends('layouts.app')
@section('title','Change Account Password')
@section('content')
<div class="heading">
  <div>
    <span class="eyebrow">SUPERADMIN · ACCOUNT SECURITY</span>
    <h1>Change user password</h1>
    <p>Update the password without modifying this account's name, role or assigned zone.</p>
  </div>
  <a class="btn outline" href="{{route('management.index','users')}}">← Back to Users</a>
</div>
<div class="card" style="max-width:700px">
  <div class="notice"><strong>Account:</strong> {{$user->name}} ({{$user->email}})<br>
  <strong>Role:</strong> {{ucwords(str_replace('_',' ',$user->role_code))}}</div>
  <form method="POST" action="{{route('management.users.password.update',$user->id)}}" class="stack" autocomplete="off">
    @csrf @method('PUT')
    <div>
      <label class="form-label" for="current_password">Your current superadministrator password *</label>
      <input class="form-control" id="current_password" type="password" name="current_password"
             autocomplete="current-password" required>
      @error('current_password')<small class="form-error">{{$message}}</small>@enderror
    </div>
    <div>
      <label class="form-label" for="password">New password for {{$user->name}} *</label>
      <input class="form-control" id="password" type="password" name="password"
             minlength="12" maxlength="120" autocomplete="new-password" required>
      <small class="help">At least 12 characters. Avoid predictable or shared passwords on deployed systems.</small>
      @error('password')<small class="form-error">{{$message}}</small>@enderror
    </div>
    <div>
      <label class="form-label" for="password_confirmation">Confirm new password *</label>
      <input class="form-control" id="password_confirmation" type="password"
             name="password_confirmation" minlength="12" maxlength="120"
             autocomplete="new-password" required>
    </div>
    <div class="actions">
      <button type="submit" class="btn primary"
        onclick="return confirm('Change the password for {{$user->name}}?')">Update password</button>
      <a class="btn outline" href="{{route('management.index','users')}}">Cancel</a>
    </div>
  </form>
</div>
@endsection
