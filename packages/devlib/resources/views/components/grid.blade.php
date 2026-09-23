<div {{ $attributes->class('dl-grid') }} {!! $dl() !!} data-dl-grid data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
    @if ($stats)<div class="dl-grid__stats" data-g-stats></div>@endif
    <div class="dl-grid__panel">
        @if ($tabs)<div class="dl-tabs__bar dl-grid__tabs" data-g-tabs></div>@endif
        <div class="dl-grid__toolbar">
            <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ $search }}" data-g-search aria-label="Cari"></div>
            <span class="dl-grid__spacer"></span>
            @if ($filters)
                <div class="dl-pop2" data-g-pop>
                    <x-dl::button variant="secondary" size="sm" icon="filter" label="Filters" data-g-toggle="filters"><span class="dl-grid__badge" data-g-filter-count hidden>0</span></x-dl::button>
                    <div class="dl-pop2__panel" data-g-panel="filters"><div class="dl-pop2__title">Filters</div><div data-g-filter-fields></div><x-dl::button variant="link" label="Reset filter" data-g-filter-reset /></div>
                </div>
            @endif
            <div class="dl-pop2" data-g-pop>
                <x-dl::button variant="secondary" size="sm" icon="columns" label="Kolom" data-g-toggle="columns" />
                <div class="dl-pop2__panel" data-g-panel="columns"><div class="dl-pop2__title">Kolom tampil</div><div data-g-col-list></div></div>
            </div>
            @if ($groups)
                <div class="dl-pop2" data-g-pop>
                    <x-dl::button variant="secondary" size="sm" icon="group" label="Grup" data-g-toggle="group" />
                    <div class="dl-pop2__panel" data-g-panel="group"><div class="dl-pop2__title">Kelompokkan menurut</div><div data-g-group-list></div></div>
                </div>
            @endif
            <div class="dl-pop2" data-g-pop>
                <x-dl::button variant="secondary" size="sm" icon="density" label="Kerapatan" data-g-toggle="density" />
                <div class="dl-pop2__panel" data-g-panel="density">
                    <div class="dl-pop2__title">Kerapatan</div>
                    <div class="dl-segment" data-g-density><button type="button" class="dl-segment__opt" data-value="compact">Rapat</button><button type="button" class="dl-segment__opt" data-value="normal">Normal</button><button type="button" class="dl-segment__opt" data-value="comfortable">Longgar</button></div>
                    <div class="dl-pop2__title">Baris per sel</div>
                    <div class="dl-row"><div class="dl-segment" data-g-lines><button type="button" class="dl-segment__opt" data-value="auto">Otomatis</button><button type="button" class="dl-segment__opt" data-value="fixed">Tetap</button></div><input type="number" min="1" max="5" value="1" class="dl-pager__goto" data-g-lines-n aria-label="Jumlah baris"></div>
                    <x-dl::button variant="link" label="Kembalikan ke bawaan" data-g-density-reset />
                </div>
            </div>
            <x-dl::button variant="secondary" size="sm" icon="export" label="Export" data-g-export title="Export CSV = kolom yang sedang tampil (atur dulu lewat Kolom)" />
            @if ($newRecord)<x-dl::button preset="new" size="sm" data-g-new :label="$newRecord" />@endif
        </div>
        <div class="dl-grid__chips" data-g-chips hidden></div>
        <div class="dl-grid__bulk" data-g-bulk hidden>
            <b data-g-bulk-count>0</b> dipilih <x-dl::button variant="link" label="Pilih semua hasil" data-g-select-all />
            <span class="dl-grid__spacer"></span>
            @foreach ($bulk as $b)<x-dl::button variant="{{ $b['variant'] ?? 'secondary' }}" size="sm" :icon="$b['icon'] ?? null" :label="$b['label']" data-g-bulk-action="{{ $b['key'] }}" :data-dl-confirm="$b['confirm'] ?? null" />@endforeach
            <x-dl::button variant="ghost" size="sm" label="Batal pilih" data-g-select-none />
        </div>
        <div class="dl-grid__wrap"><table class="dl-grid__table"><thead data-g-head></thead><tbody data-g-body></tbody><tfoot data-g-foot></tfoot></table></div>
        <div data-g-pager></div>
    </div>
</div>
