<div class="dl-demo dl-demo--row">
    @foreach (\Devlib\Components\Badge::STATUS as $status => $tone)
        <x-dl::badge :label="$status" :tone="$tone" />
    @endforeach
    <x-dl::badge label="Baru" tone="accent" icon="new" />
</div>
