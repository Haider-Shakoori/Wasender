@props(['variant' => 'primary', 'type' => 'button', 'loadingLabel' => 'Working…'])
@php($classes = match($variant) {'danger'=>'btn-danger','secondary'=>'btn-secondary','ghost'=>'btn-ghost',default=>'btn-primary'})
<button type="{{ $type }}" {{ $attributes->class($classes) }} x-data="{ busy:false }" @if($type==='submit') @submit.window="if ($event.target.contains($el)) busy=true" :disabled="busy" @endif>
    <span @if($type==='submit') x-show="!busy" @endif>{{ $slot }}</span>
    @if($type==='submit')<span x-cloak x-show="busy" class="items-center gap-2"><span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>{{ $loadingLabel }}</span>@endif
</button>
