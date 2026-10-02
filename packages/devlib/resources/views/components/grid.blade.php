<div {{ $attributes->class(['dl-grid', 'dl-grid--report' => $isReport(), 'dl-grid--entry' => $isEntry()]) }} {!! $dl() !!} data-dl-grid data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
    <div class="dl-grid__panel">
        {{-- Satu baris filter (user 2026-10-02): status (stats) | jenis (tabs) sebagai teks + jumlah; garis bawah bergeser & berganti warna. --}}
        @if ($stats || $tabs)<div class="dl-grid__fbar" data-g-fbar><span class="dl-grid__fgroup" data-g-stats></span><span class="dl-grid__fsep" data-g-fsep hidden></span><span class="dl-grid__fgroup" data-g-tabs></span><i class="dl-grid__find" data-g-find="stats"></i><i class="dl-grid__find" data-g-find="tabs"></i></div>@endif
        <div class="dl-grid__toolbar">
            <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ $search }}" data-g-search aria-label="{{ \Devlib\Lang::text('Search') }}"></div>
            @if ($isReport())
                <x-dl::button variant="secondary" size="sm" icon="filter" label="Filter" data-g-fopen><span class="dl-grid__badge" data-g-filter-count hidden>0</span></x-dl::button>
                <span class="dl-grid__count" data-g-count></span>
            @endif
            <span class="dl-grid__spacer"></span>
            @if ($filters && ! $isReport())
                <div class="dl-pop2" data-g-pop>
                    <x-dl::button variant="secondary" size="sm" icon="filter" label="Filters" data-g-toggle="filters"><span class="dl-grid__badge" data-g-filter-count hidden>0</span></x-dl::button>
                    <div class="dl-pop2__panel" data-g-panel="filters"><div class="dl-pop2__title">{{ \Devlib\Lang::text('Filters') }}</div><div data-g-filter-fields></div><x-dl::button variant="link" label="Reset filter" data-g-filter-reset /></div>
                </div>
            @endif
            <div class="dl-pop2" data-g-pop>
                <x-dl::button variant="secondary" size="sm" icon="columns" :label="\Devlib\Lang::text('Columns')" data-g-toggle="columns" />
                <div class="dl-pop2__panel" data-g-panel="columns"><div class="dl-pop2__title">{{ \Devlib\Lang::t('g_cols_shown') }}</div><div data-g-col-list></div></div>
            </div>
            {{-- Grid Pre-Entry tanpa tombol Grup (user 2026-09-30); group-by awal dari layar tetap berlaku. --}}
            @if ($groups && ! $isEntry())
                <div class="dl-pop2" data-g-pop>
                    <x-dl::button variant="secondary" size="sm" icon="group" :label="\Devlib\Lang::t('g_group')" data-g-toggle="group" />
                    <div class="dl-pop2__panel" data-g-panel="group"><div class="dl-pop2__title">{{ \Devlib\Lang::t('g_group_by') }}</div><div data-g-group-list></div></div>
                </div>
            @endif
            @unless ($isEntry())
            <div class="dl-pop2" data-g-pop>
                <x-dl::button variant="secondary" size="sm" icon="density" :label="\Devlib\Lang::t('g_density')" data-g-toggle="density" />
                <div class="dl-pop2__panel" data-g-panel="density">
                    <div class="dl-pop2__title">{{ \Devlib\Lang::t('g_density') }}</div>
                    <div class="dl-segment" data-g-density><button type="button" class="dl-segment__opt" data-value="compact">{{ \Devlib\Lang::t('g_compact') }}</button><button type="button" class="dl-segment__opt" data-value="normal">{{ \Devlib\Lang::text('Normal') }}</button><button type="button" class="dl-segment__opt" data-value="comfortable">{{ \Devlib\Lang::t('g_comfortable') }}</button></div>
                    <div class="dl-pop2__title">{{ \Devlib\Lang::t('g_lines_per_cell') }}</div>
                    <div class="dl-row"><div class="dl-segment" data-g-lines><button type="button" class="dl-segment__opt" data-value="auto">{{ \Devlib\Lang::t('g_auto') }}</button><button type="button" class="dl-segment__opt" data-value="fixed">{{ \Devlib\Lang::t('g_fixed') }}</button></div><input type="number" min="1" max="5" value="1" class="dl-pager__goto" data-g-lines-n aria-label="{{ \Devlib\Lang::text('Number of rows') }}"></div>
                    <x-dl::button variant="link" :label="\Devlib\Lang::text('Restore defaults')" data-g-density-reset />
                </div>
            </div>
            @endunless
            @if ($cards)<div class="dl-segment" data-g-view><button type="button" class="dl-segment__opt is-active" data-value="table" title="{{ \Devlib\Lang::t('g_view_table') }}"><i class="fa-solid fa-table-list"></i></button><button type="button" class="dl-segment__opt" data-value="cards" title="{{ \Devlib\Lang::t('g_view_cards') }}"><i class="fa-solid fa-grip"></i></button></div>@endif
            <x-dl::button variant="secondary" size="sm" icon="export" label="Export" data-g-export :title="\Devlib\Lang::text('Export CSV = the visible columns (set them first via Columns)')" />
            @if ($newRecord)<x-dl::button preset="new" size="sm" data-g-new :label="$newRecord" />@endif
        </div>
        <div class="dl-grid__chips" data-g-chips hidden></div>
        <div class="dl-grid__bulk" data-g-bulk hidden>
            <b data-g-bulk-count>0</b> {{ \Devlib\Lang::t('g_selected') }} <x-dl::button variant="link" :label="\Devlib\Lang::text('Select all results')" data-g-select-all />
            <span class="dl-grid__spacer"></span>
            @foreach ($bulk as $b)<x-dl::button variant="{{ $b['variant'] ?? 'secondary' }}" size="sm" :icon="$b['icon'] ?? null" :label="$b['label']" data-g-bulk-action="{{ $b['key'] }}" :data-dl-confirm="$b['confirm'] ?? null" />@endforeach
            <x-dl::button variant="ghost" size="sm" :label="\Devlib\Lang::text('Clear selection')" data-g-select-none />
        </div>
        <div class="dl-grid__cards" data-g-cards hidden></div>
        <div class="dl-grid__wrap"><table class="dl-grid__table"><thead data-g-head></thead><tbody data-g-body></tbody><tfoot data-g-foot></tfoot></table></div>
        <div data-g-pager></div>
        @if ($isReport())
            {{-- Grid for Reporting: semua kriteria cari di satu pop-up (user 2026-09-26), bukan bilah filter di atas tabel. --}}
            <div class="dl-mov" data-g-fmodal>
                <div class="dl-modal dl-modal--md" role="dialog" aria-modal="true" aria-label="Filter">
                    <div class="dl-modal__head"><h3><i class="fa-solid fa-filter"></i> Filter</h3><button type="button" class="dl-modal__x" data-g-fclose aria-label="{{ \Devlib\Lang::text('Close') }}">&times;</button></div>
                    <div class="dl-modal__body"><div class="dl-grid__fform" data-g-fform></div></div>
                    <div class="dl-modal__foot">
                        <x-dl::button variant="link" label="Reset" data-g-freset />
                        <span class="dl-grid__spacer"></span>
                        <x-dl::button preset="cancel" data-g-fclose />
                        <x-dl::button preset="apply" data-g-fapply />
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
