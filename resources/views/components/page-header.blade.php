@props(['eyebrow' => null, 'title', 'description' => null])
<header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div class="max-w-3xl">@if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif<h1 class="page-title {{ $eyebrow ? 'mt-2' : '' }}">{{ $title }}</h1>@if($description)<p class="mt-2 text-sm leading-6 text-[var(--text-secondary)] sm:text-base">{{ $description }}</p>@endif</div>
    @isset($actions)<div class="flex shrink-0 flex-wrap gap-2">{{ $actions }}</div>@endisset
</header>
