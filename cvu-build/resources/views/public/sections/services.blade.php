@php
    $unitLabels = [
        'leadership' => 'Student Leadership Unit',
        'scholarship' => 'Scholarship & Financial Unit',
        'values' => 'Character & Values Unit',
    ];
    $servicesByUnit = $services->groupBy('unit');
@endphp

<div id="units" class="section-intro">
    <p class="section-label">Explore our services</p>
    <h2 id="choose-unit">Choose a Unit</h2>
    <p>Select a CSLD unit or click any transaction below to view its requirements and processing time.</p>
</div>

@if ($serviceSearch !== '' || $serviceUnit)
    <div class="service-filters">
        <p>
            {{ $services->count() }} {{ Str::plural('service', $services->count()) }} found
            @if ($serviceSearch !== '')
                for “{{ $serviceSearch }}”
            @endif
        </p>
        <div class="filter-links" aria-label="Filter by unit">
            @foreach (['' => 'All units'] + $unitLabels as $key => $label)
                <a href="/?{{ http_build_query(['service_q' => $serviceSearch, 'service_unit' => $key]) }}#services"
                   @if (($serviceUnit ?? '') === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            <a href="/#services">Clear search</a>
        </div>
    </div>
@endif

<div class="unit-cards">
    @forelse ($servicesByUnit as $key => $unitServices)
        <article id="unit-{{ $key }}" class="unit-card unit-card-{{ $key }}"
                 style="--unit-photo: url('{{ $asset($key) }}')">
            <h3>{{ $unitLabels[$key] }}</h3>
            <div @class(['unit-services', 'unit-services-grid' => $unitServices->count() > 4])>
                @foreach ($unitServices as $service)
                    <a class="unit-service" href="{{ $destination($service['kind']) }}">
                        <span class="document-icon" aria-hidden="true"></span>
                        <span>{{ $service['title'] }}</span>
                        <span class="chevron-icon" aria-hidden="true"></span>
                    </a>
                @endforeach
            </div>
        </article>
    @empty
        <p class="no-services">No services match your search. <a href="/#services">View all services</a></p>
    @endforelse
</div>
