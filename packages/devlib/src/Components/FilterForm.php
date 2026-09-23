<?php

namespace Devlib\Components;

// Form filter / kriteria laporan: isi = field library apa pun; Terapkan -> chip kriteria aktif (x = lepas satu),
// Reset, lipat/buka, simpan kombinasi sebagai filter favorit (per pengguna, di browser). Event dl:filter {values}.
class FilterForm extends DevlibComponent
{
    public function __construct(
        public string $id = 'filter',
        public string $title = 'Filter',
        public bool $open = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::filter-form';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class(['dl-filter', 'is-closed' => ! $open]) }} {!! $dl() !!} data-dl-filter data-id="{{ $id }}" novalidate>
                <div class="dl-filter__head">
                    <button type="button" class="dl-filter__toggle" data-fl-toggle><i class="fa-solid fa-filter"></i> <b>{{ $title }}</b> <i class="fa-solid fa-chevron-down dl-filter__chev"></i></button>
                    <div class="dl-filter__chips" data-fl-chips></div>
                    <select class="dl-filter__presets" data-fl-presets aria-label="Filter tersimpan"><option value="">Filter tersimpan…</option></select>
                </div>
                <div class="dl-filter__body">{{ $slot }}</div>
                <div class="dl-filter__foot">
                    <span class="dl-filter__save" data-fl-savebox hidden><input type="text" placeholder="Nama filter" data-fl-name aria-label="Nama filter"><x-dl::button size="sm" label="OK" data-fl-saveok /></span>
                    <x-dl::button variant="link" size="sm" icon="fa-star" label="Simpan filter" data-fl-save />
                    <span class="dl-print__spacer"></span>
                    <x-dl::button variant="ghost" size="sm" label="Reset" data-fl-reset />
                    <x-dl::button size="sm" icon="filter" label="Terapkan" type="submit" />
                </div>
            </form>
            BLADE;
    }
}
