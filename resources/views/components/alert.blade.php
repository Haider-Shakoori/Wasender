@props(['variant' => 'info', 'title' => null])
@php($tone = match($variant){'success'=>'border-emerald-500/25 bg-emerald-500/8 text-[var(--success)]','warning'=>'border-amber-500/25 bg-amber-500/8 text-[var(--warning)]','danger'=>'border-rose-500/25 bg-rose-500/8 text-[var(--danger)]',default=>'border-blue-500/25 bg-blue-500/8 text-[var(--info)]'})
<div role="{{ $variant==='danger' ? 'alert' : 'status' }}" {{ $attributes->class("rounded-xl border p-4 text-sm $tone") }}>@if($title)<p class="font-semibold">{{ $title }}</p>@endif<div @class(['mt-1'=>$title])>{{ $slot }}</div></div>
