@props(['keyName','label'])
@php
    $isActive = request()->query('sort') === $keyName;
    $currentDirection = strtolower((string)request()->query('direction','asc'));
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    // Reset the page on sort changes; preserve the current search/filter selections.
    $params = array_merge(request()->except(['page','sort','direction']), [
        'sort' => $keyName,
        'direction' => $nextDirection,
    ]);
    $url = url()->current().'?'.http_build_query($params);
@endphp
<th scope="col" aria-sort="{{ $isActive ? ($currentDirection==='desc'?'descending':'ascending') : 'none' }}">
    <a class="sortable-th {{ $isActive ? 'is-active' : '' }}" href="{{ $url }}"
       aria-label="Sort by {{ $label }} {{ $nextDirection === 'asc' ? 'ascending' : 'descending' }}">
        <span>{{ $label }}</span>
        <span class="sort-glyph" aria-hidden="true">{{ $isActive ? ($currentDirection==='desc'?'▼':'▲') : '⇅' }}</span>
    </a>
</th>
