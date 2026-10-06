@props(['variant' => 'neutral'])
<span {{ $attributes->class('badge-'.$variant) }}>{{ $slot }}</span>
