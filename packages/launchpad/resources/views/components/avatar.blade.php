@props(['user', 'size' => 32])
@php($photo = \Launchpad\Accounts\Avatar::url($user))
@if ($photo)
    <img class="lp-avatar lp-avatar--photo" src="{{ $photo }}" alt="" width="{{ $size }}" height="{{ $size }}" style="width:{{ $size }}px;height:{{ $size }}px">
@else
    <span class="lp-avatar" aria-hidden="true" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ round($size * .42) }}px">{{ mb_strtoupper(mb_substr($user->name ?: $user->email, 0, 1)) }}</span>
@endif
