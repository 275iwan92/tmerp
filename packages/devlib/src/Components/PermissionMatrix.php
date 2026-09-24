<?php

namespace Devlib\Components;

// Hak akses grup per objek (mockup security.html "Hak Akses Grup"): pilih grup -> tiap objek Form/Grid diberi
// tingkat akses (Tidak ada / Baca saja / Baca-tulis / Baca-tulis + Ekspor / Semua). Saring per modul & cari,
// "Set semua" untuk yang tampil, penanda baris berubah + jumlahnya, pindah grup tanpa kehilangan perubahan.
// objects: [['key','name','type' => Form|Grid,'module']] ; groups: ['Administrator', ...]
// perms: [grup => [objKey => indeks level]] ; Simpan -> event dl:permissions-save {group, perms}.
class PermissionMatrix extends DevlibComponent
{
    public const LEVELS = ['Tidak ada', 'Baca saja', 'Baca-tulis', 'Baca-tulis + Ekspor', 'Semua'];

    public function __construct(
        public array $objects = [],
        public array $groups = [],
        public array $perms = [],
        public array $levels = self::LEVELS,
        public int $default = 1,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::permission-matrix';
    }

    public function source(): string
    {
        return $this->source ?? 'sec_group_app (grup x objek sys_app_menu)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-perm') }} {!! $dl() !!} data-dl-perm data-objects="{{ json_encode($objects) }}" data-groups="{{ json_encode($groups) }}"
                data-perms="{{ json_encode((object) $perms) }}" data-levels="{{ json_encode($levels) }}" data-default="{{ $default }}">
                <div class="dl-perm__bar">
                    <label class="dl-perm__lbl">Grup <select data-pm-group aria-label="Grup">@foreach ($groups as $g)<option>{{ $g }}</option>@endforeach</select></label>
                    <label class="dl-perm__lbl">Modul <select data-pm-module aria-label="Modul"><option value="">Semua modul</option>@foreach (collect($objects)->pluck('module')->unique() as $m)<option>{{ $m }}</option>@endforeach</select></label>
                    <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Cari objek…" data-pm-q aria-label="Cari objek"></div>
                    <span class="dl-print__spacer"></span>
                    <label class="dl-perm__lbl">Set semua yang tampil <select data-pm-bulk aria-label="Set semua"><option value="">—</option>@foreach ($levels as $i => $l)<option value="{{ $i }}">{{ $l }}</option>@endforeach</select></label>
                </div>
                <div class="dl-perm__scroll"><table class="dl-perm__table"><thead><tr><th>Objek</th><th>Tipe</th><th>Modul</th><th>Hak akses</th></tr></thead><tbody data-pm-body></tbody></table></div>
                <div class="dl-perm__foot"><span data-pm-dirty></span><span class="dl-print__spacer"></span><x-dl::button variant="ghost" size="sm" label="Reset" data-pm-reset /><x-dl::button preset="save" size="sm" data-pm-save /></div>
            </div>
            BLADE;
    }
}
