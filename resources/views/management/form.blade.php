@extends('layouts.app')
@section('title',($item?'Edit ':'Create ').$spec['title'])
@section('content')
<div class="heading"><div><span class="eyebrow">BARANGAY OPERATIONS</span><h1>{{$item?'Edit':'Add'}} — {{$spec['title']}}</h1><p>Required fields are marked. Changes take effect based on publication settings.</p></div><a class="btn" href="{{route('management.index',$resource)}}">← Back to list</a></div>
<div class="card" style="max-width:900px"><form class="grid2" method="POST" action="{{$item?route('management.update',[$resource,$item->id]):route('management.store',$resource)}}">@csrf @if($item)@method('PUT')@endif
@foreach($spec['fields'] as $key=>$field)
@if(!($resource==='users' && $item && $key==='password'))
<div style="{{($field['type']??'')==='textarea'?'grid-column:1/-1':''}}"><label class="form-label" for="fld_{{$key}}">{{$field['label']}} @if(str_starts_with($field['rules'],'required'))<span class="req">*</span>@endif</label>
@php($value=old($key,$item?->{$key}))
@if(($field['type']??'')==='textarea')<textarea class="form-control" id="fld_{{$key}}" name="{{$key}}" rows="5">{{$value}}</textarea>
@elseif(($field['type']??'')==='boolean')<label class="checkline"><input type="checkbox" id="fld_{{$key}}" name="{{$key}}" value="1" @checked(old($key,$item?->{$key}??true))> Enabled</label>
@elseif(($field['type']??'')==='enum')<select class="form-control" name="{{$key}}" id="fld_{{$key}}"><option value="">Choose option</option>@foreach($field['options'] as $v=>$label)<option value="{{$v}}" @selected((string)$value===(string)$v)>{{$label}}</option>@endforeach</select>
@elseif(($field['type']??'')==='relation')<select class="form-control" name="{{$key}}" id="fld_{{$key}}"><option value="">{{str_contains($field['rules'],'nullable')?'Any / none':'Choose'}}</option>@foreach($options[$key] as $opt)
    @if(!($resource==='users'&&$key==='role_id'&&$opt->code==='superadmin'&&auth()->user()->role_code!=='superadmin'))
    <option value="{{$opt->id}}" @selected((string)$value===(string)$opt->id)>{{ $opt->{$field['display']} ?? $opt->id }}</option>
    @endif
@endforeach</select>
@else<input class="form-control" id="fld_{{$key}}" name="{{$key}}" type="{{in_array($field['type']??'text',['date','time','email','password'])?$field['type']:'text'}}" value="{{($field['type']??'')==='password'?'':$value}}" @if(str_starts_with($field['rules'],'required'))required @endif autocomplete="off">
@endif
@error($key)<span class="form-error">{{$message}}</span>@enderror
</div>
@endif
@endforeach
@if($resource==='users' && $item && auth()->user()->role_code==='superadmin' && (string)$item->id!==(string)auth()->id())
<div style="grid-column:1/-1"><a class="btn outline" href="{{route('management.users.password',$item->id)}}">Change account password</a></div>
@endif
<div style="grid-column:1/-1" class="row-between"><p class="muted" style="margin:0">Published content is visible to relevant residents.</p><button type="submit" class="btn primary">{{$item?'Save changes':'Create record'}}</button></div>
</form></div>
@endsection
