@props(['name', 'title'])
<div x-cloak x-show="$store.modal?.name === '{{ $name }}'" @keydown.escape.window="$store.modal?.close()" class="fixed inset-0 z-[80] grid place-items-end p-0 sm:place-items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="{{ $name }}-title">
    <button class="absolute inset-0 bg-[var(--overlay)]" @click="$store.modal.close()" aria-label="Close dialog"></button>
    <div x-show="$store.modal?.name === '{{ $name }}'" x-transition class="relative w-full rounded-t-2xl bg-[var(--surface-raised)] p-6 shadow-2xl sm:max-w-lg sm:rounded-2xl"><h2 id="{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2><div class="mt-3 text-sm leading-6 text-[var(--text-secondary)]">{{ $slot }}</div>@isset($actions)<div class="mt-6 flex justify-end gap-3">{{ $actions }}</div>@endisset</div>
</div>
