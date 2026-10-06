<x-layouts.platform title="System health">
 <x-page-header eyebrow="Reliability" title="System health" description="Live application dependency and workload checks." />
 <div class="mt-6 grid gap-4 md:grid-cols-2">@foreach($checks as $name=>$check)<article class="panel"><div class="flex items-start justify-between gap-4"><div><h2 class="section-title">{{ str($name)->headline() }}</h2><p class="muted mt-2">{{ $check['message'] }}</p></div><x-badge :variant="$check['healthy'] ? 'success':'danger'">{{ $check['healthy'] ? 'Healthy':'Attention' }}</x-badge></div></article>@endforeach</div>
</x-layouts.platform>
