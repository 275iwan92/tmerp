@props(['title', 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'lg-panel']) }}>
    <div class="lg-ph">
        <div>
            <h2>{{ $title }}</h2>
            @if ($subtitle)<p>{{ $subtitle }}</p>@endif
        </div>
        @isset($action)<div class="lg-actions">{{ $action }}</div>@endisset
    </div>
    {{ $slot }}
</div>
