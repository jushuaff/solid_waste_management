@extends('layouts.app')
@section('title',$spec['title'])
@section('content')
<div class="heading"><div><span class="eyebrow">BARANGAY CONFIGURATION</span><h1>{{$spec['title']}}</h1><p>Create, update, filter and manage records.</p></div><a href="{{route('management.create',$resource)}}" class="btn primary">＋ Add new</a></div>
@if($resource==='schedules')<div class="notice">Support different pickup methods, recurring weekday schedules, time windows and resident instructions. Only published schedules appear to residents.</div>@endif
<div class="card"><form method="GET" class="filterbar management-filters"><input class="form-control" name="q" value="{{request('q')}}" placeholder="Search name or publication status" aria-label="Search records"><button class="btn outline" type="submit">Filter</button><a href="{{route('management.index',$resource)}}" class="btn">Reset</a></form>
<div style="overflow:auto"><table class="content-table"><thead><tr>
@foreach(array_slice($spec['fields'],0,4,true) as $key=>$field)
<x-sortable-th :key-name="$key" :label="$field['label']" />
@endforeach<th scope="col">Actions</th></tr></thead><tbody>
@forelse($rows as $row)
<tr>@foreach(array_slice($spec['fields'],0,4,true) as $key=>$field)
<td>
@php($value=$row->$key)
@if(($field['type']??'')==='relation'&&$value)
    @php($relationModel=$field['model'])
    {{ $relationModel::find($value)?->{$field['display']} ?? 'Record not found' }}
@elseif(($field['type']??'')==='boolean'){{ $value?'Active':'Inactive' }}
@elseif(($field['type']??'')==='password')<span class="muted">Hidden</span>
@elseif(($field['type']??'')==='enum'){{ $field['options'][$value] ?? $value }}
@else{{ \Illuminate\Support\Str::limit((string)$value,80) }}@endif
</td>@endforeach
<td><div class="actions"><a class="btn small outline" href="{{route('management.edit',[$resource,$row->id])}}">Edit</a>
@if($resource==='users' && auth()->user()->role_code==='superadmin' && (string)$row->id!==(string)auth()->id())
<a class="btn small outline" href="{{route('management.users.password',$row->id)}}">Change password</a>
@endif
<form class="inlineform" method="POST" action="{{route('management.destroy',[$resource,$row->id])}}" onsubmit="return confirm('Delete this unused record? Published and referenced records may be protected.')">@csrf @method('DELETE')<button class="btn small red">Delete</button></form></div></td></tr>
@empty<tr><td colspan="6" class="muted">No records. Use “Add new” to create one.</td></tr>@endforelse
</tbody></table></div>
<div class="pager">{{ $rows->links('components.pagination') }}</div>
</div>
@endsection
