<?php

namespace Devlib\Components;

// Hak akses grup per objek (mockup security.html "Hak Akses Grup"): pilih grup -> tiap objek Form/Grid diberi
// tingkat akses (Tidak ada / Baca saja / Baca-tulis / Baca-tulis + Ekspor / Semua). Saring per modul & cari,
// "Set semua" untuk yang tampil, penanda baris berubah + jumlahnya, pindah grup tanpa kehilangan perubahan.
// objects: [['key','name','type' => Form|Grid,'module']] ; groups: ['Administrator', ...]
// perms: [grup => [objKey => indeks level]] ; Simpan -> event dl:permissions-save {group, perms}.
// :save-url = alamat POST JSON {group, perms}: tersimpan di server dulu, baru ditandai tersimpan (gagal = pesan galat, perubahan tetap).
class PermissionMatrix extends DevlibComponent
{
    public const LEVELS = ['No access', 'Read only', 'Read-write', 'Read-write + Export', 'All'];

    public function __construct(
        public array $objects = [],
        public array $groups = [],
        public array $perms = [],
        public array $levels = self::LEVELS,
        public int $default = 1,
        public ?string $source = null,
        public ?string $saveUrl = null,
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
                data-perms="{{ json_encode((object) $perms) }}" data-levels="{{ json_encode(array_map(fn ($l) => \Devlib\Lang::text($l), $levels)) }}" data-default="{{ $default }}" @if ($saveUrl) data-save-url="{{ $saveUrl }}" @endif>
                <div class="dl-perm__bar">
                    <label class="dl-perm__lbl">{{ \Devlib\Lang::t('g_group') }} <select data-pm-group aria-label="{{ \Devlib\Lang::t('g_group') }}">@foreach ($groups as $g)<option>{{ $g }}</option>@endforeach</select></label>
                    <label class="dl-perm__lbl">{{ \Devlib\Lang::t('pmx_module') }} <select data-pm-module aria-label="{{ \Devlib\Lang::t('pmx_module') }}"><option value="">{{ \Devlib\Lang::t('pmx_all_modules') }}</option>@foreach (collect($objects)->pluck('module')->unique() as $m)<option>{{ $m }}</option>@endforeach</select></label>
                    <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search objects…') }}" data-pm-q aria-label="{{ \Devlib\Lang::text('Search objects') }}"></div>
                    <span class="dl-print__spacer"></span>
                    <label class="dl-perm__lbl">{{ \Devlib\Lang::t('pmx_set_shown') }} <select data-pm-bulk aria-label="{{ \Devlib\Lang::text('Set all') }}"><option value="">—</option>@foreach ($levels as $i => $l)<option value="{{ $i }}">{{ \Devlib\Lang::text($l) }}</option>@endforeach</select></label>
                </div>
                <div class="dl-perm__scroll"><table class="dl-perm__table"><thead><tr><th>{{ \Devlib\Lang::t('pmx_object') }}</th><th>{{ \Devlib\Lang::t('pmx_type') }}</th><th>{{ \Devlib\Lang::t('pmx_module') }}</th><th>{{ \Devlib\Lang::t('pmx_access') }}</th></tr></thead><tbody data-pm-body></tbody></table></div>
                <div class="dl-perm__foot"><span data-pm-dirty></span><span class="dl-print__spacer"></span><x-dl::button variant="ghost" size="sm" label="Reset" data-pm-reset /><x-dl::button preset="save" size="sm" data-pm-save /></div>
            </div>
            BLADE;
    }
}
