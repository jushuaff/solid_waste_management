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

<section class="card fee-account-list" aria-labelledby="fee-accounts-heading" data-fee-old-edit="{{ old('_edit_account','') }}">
    <div class="fee-account-section-header fee-account-table-heading">
        <div>
            <h2 class="paneltitle" id="fee-accounts-heading">Configured receiving accounts</h2>
            <p class="muted">View approved payment destinations and edit an account without leaving the list.</p>
        </div>
        <span class="fee-account-count">{{ $accounts->count() }} {{ $accounts->count()===1?'account':'accounts' }}</span>
    </div>

    <form method="GET" action="{{route('fees.accounts.index')}}" class="fee-account-search">
        <label for="fee-account-search-field" class="fee-sr-only">Search receiving accounts</label>
        <input id="fee-account-search-field" type="search" class="form-control" name="q"
            value="{{is_string(request('q'))?request('q'):''}}" maxlength="100"
            placeholder="Search city, provider, account name or number">
        <button type="submit" class="btn outline">Search</button>
        @if(request()->filled('q'))
            <a class="btn" href="{{route('fees.accounts.index')}}">Clear</a>
        @endif
    </form>

    <div class="fee-receiving-table-scroll" role="region" tabindex="0"
        aria-label="Receiving accounts table; scroll horizontally for more columns on smaller screens">
        <table class="content-table fee-receiving-table">
            <thead>
                <tr>
                    <x-sortable-th key-name="city" label="City / Municipality" />
                    <x-sortable-th key-name="provider" label="Provider" />
                    <x-sortable-th key-name="type" label="Type" />
                    <x-sortable-th key-name="account_name" label="Account holder" />
                    <x-sortable-th key-name="account_number" label="Account number" />
                    <x-sortable-th key-name="qr" label="QR code" />
                    <x-sortable-th key-name="status" label="Status" />
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td><strong>{{$account->city?->name??'City unavailable'}}</strong></td>
                        <td class="fee-table-provider"><strong>{{$account->provider}}</strong></td>
                        <td>
                            <span class="fee-type-label">{{in_array($account->method,['gcash','wallet'],true)?'E-wallet':'Bank transfer'}}</span>
                        </td>
                        <td>{{$account->account_name}}</td>
                        <td><span class="fee-table-number">{{$account->account_number}}</span></td>
                        <td>
                            @if($account->qr_path)
                                <a class="fee-qr-table-link" href="{{route('fees.accounts.qr',$account->id)}}"
                                    target="_blank" rel="noopener noreferrer" aria-label="View QR code for {{$account->provider}}">
                                    View QR
                                </a>
                            @else
                                <span class="muted">Not added</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{$account->is_active?'':'grey'}}">{{$account->is_active?'Active':'Inactive'}}</span>
                        </td>
                        <td>
                            <button type="button" class="btn small outline fee-account-edit-button"
                                data-fee-dialog-open="{{$account->id}}"
                                aria-haspopup="dialog" aria-controls="fee-account-dialog-{{$account->id}}">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="fee-table-empty">
                            {{ request()->filled('q')?'No receiving accounts match your search.':'No receiving accounts configured yet. Add an account using the form above.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@foreach($accounts as $account)
    @php($editOld=(string)old('_edit_account')===(string)$account->id)
    <dialog id="fee-account-dialog-{{$account->id}}" class="fee-account-dialog"
        aria-labelledby="fee-account-dialog-title-{{$account->id}}">
        <div class="fee-dialog-heading">
            <div>
                <span class="eyebrow">RECEIVING ACCOUNT</span>
                <h2 id="fee-account-dialog-title-{{$account->id}}">Edit receiving account</h2>
                <p>{{$account->provider}} · {{$account->city?->name??'City unavailable'}}</p>
            </div>
            <button type="button" class="fee-dialog-close" data-fee-dialog-close
                aria-label="Close edit account window">×</button>
        </div>

        <form method="POST" action="{{route('fees.accounts.update',$account->id)}}"
            enctype="multipart/form-data" class="fee-account-form fee-account-dialog-form">
            @csrf @method('PUT')
            <input type="hidden" name="_edit_account" value="{{$account->id}}">
            <div class="fee-account-fields">
                <div class="field">
                    <label for="fee-city-{{$account->id}}">City / municipality <span class="req">*</span></label>
                    <select id="fee-city-{{$account->id}}" class="form-control" required name="city_id">
                        @foreach($cities as $city)
                            <option value="{{$city->id}}"
                                @selected((string)($editOld?old('city_id'):$account->city_id)===(string)$city->id)>
                                {{$city->name}}
                            </option>
                        @endforeach
                    </select>
                </div>
                @include('fees.partials.provider-select',[
                    'selected'=>$editOld?old('provider',$account->provider):$account->provider,
                    'providerInputId'=>'fee-provider-'.$account->id
                ])
                <div class="field">
                    <label for="fee-holder-{{$account->id}}">Registered account name <span class="req">*</span></label>
                    <input id="fee-holder-{{$account->id}}" class="form-control" name="account_name"
                        required maxlength="160" value="{{$editOld?old('account_name',$account->account_name):$account->account_name}}">
                </div>
                <div class="field">
                    <label for="fee-number-{{$account->id}}">Receiving account number <span class="req">*</span></label>
                    <input id="fee-number-{{$account->id}}" class="form-control" name="account_number"
                        required maxlength="60" autocomplete="off"
                        value="{{$editOld?old('account_number',$account->account_number):$account->account_number}}">
                </div>
            </div>

            <div class="fee-account-extras">
                <div class="field">
                    <label for="fee-instructions-{{$account->id}}">Payment instructions <span class="fee-optional">Optional</span></label>
                    <textarea id="fee-instructions-{{$account->id}}" class="form-control fee-account-instructions"
                        name="instructions" rows="4" maxlength="500">{{$editOld?old('instructions',$account->instructions):$account->instructions}}</textarea>
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

            <div class="fee-account-actions fee-dialog-actions">
                <label class="checkline fee-account-publish">
                    <input type="checkbox" name="is_active" value="1"
                        @checked($editOld?old('is_active','0')==='1':$account->is_active)>
                    <span>
                        <strong>Active · show payment instructions to residents</strong>
                        <small>Deactivate to hide this receiving account from residents.</small>
                    </span>
                </label>
                <button type="button" class="btn outline" data-fee-dialog-close>Cancel</button>
                <button class="btn primary" type="submit">Save changes</button>
            </div>
        </form>
    </dialog>
@endforeach
@endsection
@section('scripts')
<script src="{{asset('assets/fee-account-form.js')}}" defer></script>
@endsection
