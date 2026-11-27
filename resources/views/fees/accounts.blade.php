@extends('layouts.app')
@section('title','Payment Receiving Accounts')
@section('content')
<div class="heading">
    <div>
        <span class="eyebrow">GARBAGE FEE COLLECTION</span>
        <h1>Receiving accounts</h1>
        <p>Manage the payment destinations residents can use for their monthly garbage fees.</p>
    </div>
    <a class="btn outline" href="{{route('fees.index')}}">← Monthly fees</a>
</div>
<div class="notice">Select the receiving bank or e-wallet and enter its account name and number. QR codes are optional. Card and cash payments are handled through authorized collection channels and recorded after verification.</div>
<div class="card" style="margin-bottom:20px">
<h2 class="paneltitle">Add receiving account</h2>
<form method="POST" action="{{route('fees.accounts.store')}}" enctype="multipart/form-data" class="stack">
@csrf
<div class="grid2">
<div class="field"><label>City / municipality *</label>
<select name="city_id" required class="form-control"><option value="">Choose city</option>
@foreach($cities as $city)<option value="{{$city->id}}" @selected(old('city_id')===(string)$city->id)>{{$city->name}} · {{$city->region}}</option>@endforeach
</select></div>
@include('fees.partials.provider-select',['selected'=>old('provider',''),'providerInputId'=>'new-fee-provider'])
<div class="field"><label>Registered account name *</label><input required maxlength="160" class="form-control" name="account_name" value="{{old('account_name')}}"></div>
<div class="field"><label>Receiving account number *</label><input required maxlength="60" class="form-control" name="account_number" value="{{old('account_number')}}" autocomplete="off"></div>
<div class="field"><label>Optional QR code (JPG, PNG, WebP · 2 MB max)</label><input type="file" name="qr_code" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
<div class="field full"><label>Instructions (optional)</label><textarea class="form-control" rows="2" name="instructions" maxlength="500" placeholder="Payment purpose, applicable office or payment notes">{{old('instructions')}}</textarea></div>
<div class="field full"><label class="checkline"><input type="checkbox" name="is_active" value="1" checked> Publish to residents in this city</label></div>
</div>
<div class="actions"><button class="btn primary" type="submit">Save receiving account</button></div>
</form>
</div>
@if(auth()->user()->role_code==='superadmin')
<div class="card" style="margin-bottom:20px">
<details class="fee-provider-admin" id="fee-provider-create">
<summary class="btn outline">+ Add bank or e-wallet provider</summary>
<p class="muted">Add institutions not yet listed. This changes the selectable provider list only; it does not create a receiving account.</p>
<form method="POST" action="{{route('fees.providers.store')}}" class="filterbar">
@csrf
<label class="field">Institution name
<input class="form-control" name="name" maxlength="100" required placeholder="Bank or e-wallet name"></label>
<label class="field">Provider category
<select class="form-control" name="type" required>
<option value="bank">Bank / digital bank</option>
<option value="wallet">E-wallet</option>
</select></label>
<button type="submit" class="btn primary">Add provider</button>
</form>
@if($customProviders->isNotEmpty())
<h3 class="paneltitle" style="margin-top:16px">Additional providers</h3>
<div class="fee-provider-tags">
@foreach($customProviders as $provider)
<form method="POST" action="{{route('fees.providers.toggle',$provider->id)}}" class="fee-provider-tag">
@csrf @method('PATCH')
<span>{{$provider->name}} · {{ucfirst($provider->type)}} · {{$provider->is_active?'Active':'Inactive'}}</span>
<button type="submit" class="btn small outline">{{$provider->is_active?'Disable':'Enable'}}</button>
</form>
@endforeach
</div>
@endif
</details>
</div>
@endif

<div class="card">
<h2 class="paneltitle">Configured receiving accounts</h2>
<div class="fee-receiving-grid">
@forelse($accounts as $account)
<section class="fee-receiving-card">
<div class="row-between">
<div><strong>{{$account->city?->name??'City unavailable'}}</strong><p class="muted" style="margin:4px 0">{{$account->provider}} · {{in_array($account->method,['gcash','wallet'],true)?'E-wallet':'Bank transfer'}}</p></div>
<span class="badge {{$account->is_active?'':'grey'}}">{{$account->is_active?'Active':'Inactive'}}</span>
</div>
<form method="POST" action="{{route('fees.accounts.update',$account->id)}}" enctype="multipart/form-data" class="stack" style="margin-top:12px">
@csrf @method('PUT')
<div class="grid2">
<div class="field"><label>City *</label><select class="form-control" required name="city_id">
@foreach($cities as $city)<option value="{{$city->id}}" @selected((string)$city->id===(string)$account->city_id)>{{$city->name}}</option>@endforeach
</select></div>
@include('fees.partials.provider-select',['selected'=>$account->provider,'providerInputId'=>'fee-provider-'.$account->id])
<div class="field"><label>Account holder *</label><input class="form-control" name="account_name" required maxlength="160" value="{{$account->account_name}}"></div>
<div class="field full"><label>Account number *</label><input class="form-control" name="account_number" required maxlength="60" value="{{$account->account_number}}"></div>
<div class="field full"><label>Instructions</label><textarea class="form-control" name="instructions" rows="2" maxlength="500">{{$account->instructions}}</textarea></div>
<div class="field full">
<label>QR code (optional)</label>
@if($account->qr_path)
<img class="fee-qr-preview" src="{{route('fees.accounts.qr',$account->id)}}" alt="Current QR code for {{$account->provider}}">
<label class="checkline"><input type="checkbox" name="remove_qr" value="1"> Remove current QR (without uploading a replacement)</label>
@endif
<input class="form-control" type="file" name="qr_code" accept="image/jpeg,image/png,image/webp">
<small class="muted">Uploading a new image replaces the current displayed QR.</small>
</div>
<div class="field full"><label class="checkline"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)> Active · show payment instructions to residents</label></div>
</div>
<button class="btn outline" type="submit">Save account changes</button>
</form>
</section>
@empty
<p class="muted">No receiving accounts configured. Add a city and its receiving account above to begin.</p>
@endforelse
</div>
</div>
@endsection
