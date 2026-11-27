@extends('layouts.app')
@section('title','Payment Receiving Accounts')
@section('content')
<div class="heading">
    <div>
        <span class="eyebrow">GARBAGE FEE COLLECTION</span>
        <h1>Receiving accounts</h1>
        <p>Manage the approved bank and e-wallet accounts residents can use for garbage-fee payments.</p>
    </div>
    <a class="btn outline" href="{{route('fees.index')}}">← Monthly fees</a>
</div>

<div class="notice fee-account-notice">
    Residents can view only active receiving accounts assigned to their city. Always verify account ownership before publishing payment instructions. Cash and card payments are recorded separately after verification.
</div>

<section class="card fee-account-create" aria-labelledby="fee-new-account-heading">
    <div class="fee-account-section-header">
        <div>
            <span class="eyebrow">NEW PAYMENT DESTINATION</span>
            <h2 class="paneltitle" id="fee-new-account-heading">Add receiving account</h2>
            <p class="muted">Enter the receiving institution and account details. A QR code is optional.</p>
        </div>
    </div>

    <form method="POST" action="{{route('fees.accounts.store')}}" enctype="multipart/form-data" class="fee-account-form">
        @csrf

        <div class="fee-account-fields">
            <div class="field">
                <label for="new-fee-city">City / municipality <span class="req">*</span></label>
                <select id="new-fee-city" name="city_id" required class="form-control">
                    <option value="">Select city or municipality</option>
                    @foreach($cities as $city)
                        <option value="{{$city->id}}" @selected(old('city_id')===(string)$city->id)>{{$city->name}} · {{$city->region}}</option>
                    @endforeach
                </select>
                <small class="hint">Residents in this city will see this account when published.</small>
            </div>
            @include('fees.partials.provider-select',['selected'=>old('provider',''),'providerInputId'=>'new-fee-provider'])
            <div class="field">
                <label for="new-fee-holder">Registered account name <span class="req">*</span></label>
                <input id="new-fee-holder" required maxlength="160" class="form-control" name="account_name"
                    autocomplete="organization" value="{{old('account_name')}}" placeholder="Name registered with the provider">
            </div>
            <div class="field">
                <label for="new-fee-number">Receiving account number <span class="req">*</span></label>
                <input id="new-fee-number" required maxlength="60" class="form-control" name="account_number"
                    autocomplete="off" inputmode="numeric" value="{{old('account_number')}}" placeholder="Account or e-wallet number">
            </div>
        </div>

        <div class="fee-account-extras">
            <div class="field">
                <label for="new-fee-instructions">Payment instructions <span class="fee-optional">Optional</span></label>
                <textarea id="new-fee-instructions" class="form-control fee-account-instructions"
                    name="instructions" maxlength="500" rows="4"
                    placeholder="Payment reference or other collection instructions for residents">{{old('instructions')}}</textarea>
                <small class="hint">These instructions will appear alongside the receiving account.</small>
            </div>
            <div class="field">
                <label for="new-fee-qr">Payment QR code <span class="fee-optional">Optional</span></label>
                <div class="fee-qr-upload" data-fee-qr-upload>
                    <div class="fee-qr-upload-label">Upload a QR image</div>
                    <p>JPG, PNG or WebP · Maximum 2 MB</p>
                    <input id="new-fee-qr" type="file" name="qr_code" class="form-control"
                        accept="image/jpeg,image/png,image/webp">
                    <img class="fee-qr-selected-preview" data-fee-qr-preview alt="Selected QR code preview" hidden>
                    <small class="fee-qr-file-label" data-fee-qr-filename aria-live="polite"></small>
                </div>
            </div>
        </div>

        <div class="fee-account-actions">
            <label class="checkline fee-account-publish">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active','1')==='1')>
                <span><strong>Publish to residents</strong><small>Show this account as an available payment destination.</small></span>
            </label>
            <button class="btn primary" type="submit">Save receiving account</button>
        </div>
    </form>
</section>

@if(auth()->user()->role_code==='superadmin')
<section class="card fee-provider-management">
    <details class="fee-provider-admin" id="fee-provider-create">
        <summary>+ Add bank or e-wallet provider</summary>
        <p class="muted">Add an institution not available in the dropdown. This does not create a receiving account.</p>
        <form method="POST" action="{{route('fees.providers.store')}}" class="filterbar">
            @csrf
            <label class="field">Institution name
                <input class="form-control" name="name" maxlength="100" required placeholder="Bank or e-wallet name">
            </label>
            <label class="field">Provider category
                <select class="form-control" name="type" required>
                    <option value="bank">Bank / digital bank</option>
                    <option value="wallet">E-wallet</option>
                </select>
            </label>
            <button type="submit" class="btn primary">Add provider</button>
        </form>
        @if($customProviders->isNotEmpty())
            <h3 class="paneltitle fee-extra-heading">Additional providers</h3>
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
</section>
@endif

<section class="card fee-account-list" aria-labelledby="fee-accounts-heading">
    <div class="fee-account-section-header">
        <div>
            <h2 class="paneltitle" id="fee-accounts-heading">Configured receiving accounts</h2>
            <p class="muted">Select Edit account to update details, replace a QR code or change publication status.</p>
        </div>
        <span class="fee-account-count">{{ $accounts->count() }} {{ $accounts->count()===1?'account':'accounts' }}</span>
    </div>

    <div class="fee-receiving-grid">
        @forelse($accounts as $account)
            <article class="fee-receiving-card">
                <div class="fee-receiving-card-header">
                    <div>
                        <span class="eyebrow">{{in_array($account->method,['gcash','wallet'],true)?'E-WALLET':'BANK TRANSFER'}}</span>
                        <h3>{{$account->provider}}</h3>
                        <p>{{$account->city?->name??'City unavailable'}}</p>
                    </div>
                    <span class="badge {{$account->is_active?'':'grey'}}">{{$account->is_active?'Active':'Inactive'}}</span>
                </div>
                <div class="fee-receiving-summary">
                    <div><span>Account name</span><strong>{{$account->account_name}}</strong></div>
                    <div><span>Account number</span><strong>{{$account->account_number}}</strong></div>
                </div>

                <details class="fee-account-edit-panel">
                    <summary>Edit account <span aria-hidden="true">⌄</span></summary>
                    <form method="POST" action="{{route('fees.accounts.update',$account->id)}}"
                        enctype="multipart/form-data" class="fee-account-form">
                        @csrf @method('PUT')
                        <div class="fee-account-fields">
                            <div class="field">
                                <label for="fee-city-{{$account->id}}">City / municipality <span class="req">*</span></label>
                                <select id="fee-city-{{$account->id}}" class="form-control" required name="city_id">
                                    @foreach($cities as $city)
                                        <option value="{{$city->id}}" @selected((string)$city->id===(string)$account->city_id)>{{$city->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            @include('fees.partials.provider-select',['selected'=>$account->provider,'providerInputId'=>'fee-provider-'.$account->id])
                            <div class="field">
                                <label for="fee-holder-{{$account->id}}">Registered account name <span class="req">*</span></label>
                                <input id="fee-holder-{{$account->id}}" class="form-control" name="account_name"
                                    required maxlength="160" value="{{$account->account_name}}">
                            </div>
                            <div class="field">
                                <label for="fee-number-{{$account->id}}">Receiving account number <span class="req">*</span></label>
                                <input id="fee-number-{{$account->id}}" class="form-control" name="account_number"
                                    required maxlength="60" value="{{$account->account_number}}">
                            </div>
                        </div>
                        <div class="fee-account-extras">
                            <div class="field">
                                <label for="fee-instructions-{{$account->id}}">Payment instructions <span class="fee-optional">Optional</span></label>
                                <textarea id="fee-instructions-{{$account->id}}" class="form-control fee-account-instructions"
                                    name="instructions" rows="4" maxlength="500">{{$account->instructions}}</textarea>
                            </div>
                            <div class="field">
                                <label for="fee-qr-{{$account->id}}">Payment QR code <span class="fee-optional">Optional</span></label>
                                <div class="fee-qr-upload" data-fee-qr-upload>
                                    @if($account->qr_path)
                                        <img class="fee-qr-existing" src="{{route('fees.accounts.qr',$account->id)}}"
                                            alt="Current QR code for {{$account->provider}}">
                                        <label class="checkline">
                                            <input type="checkbox" name="remove_qr" value="1"> Remove current QR
                                        </label>
                                    @else
                                        <div class="fee-qr-upload-label">No QR code uploaded</div>
                                    @endif
                                    <p>Upload a replacement · JPG, PNG or WebP · 2 MB max</p>
                                    <input id="fee-qr-{{$account->id}}" class="form-control" type="file" name="qr_code"
                                        accept="image/jpeg,image/png,image/webp">
                                    <img class="fee-qr-selected-preview" data-fee-qr-preview alt="New QR code preview" hidden>
                                    <small class="fee-qr-file-label" data-fee-qr-filename aria-live="polite"></small>
                                </div>
                            </div>
                        </div>
                        <div class="fee-account-actions">
                            <label class="checkline fee-account-publish">
                                <input type="checkbox" name="is_active" value="1" @checked($account->is_active)>
                                <span><strong>Active · show to residents</strong><small>Turn off to hide these payment instructions.</small></span>
                            </label>
                            <button class="btn primary" type="submit">Save changes</button>
                        </div>
                    </form>
                </details>
            </article>
        @empty
            <p class="muted">No receiving accounts configured. Add a receiving account above to begin.</p>
        @endforelse
    </div>
</section>
@endsection
@section('scripts')
<script src="{{asset('assets/fee-account-form.js')}}" defer></script>
@endsection
