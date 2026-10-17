<?php

/*
 * REMARK
 * Tujuan       : Banner profil orang / objek: foto di kiri, nama besar + baris keterangan + chip berkelompok (berlabel) + batang kelengkapan di sampingnya, tile KPI selebar penuh di bawahnya, lalu satu baris pemberitahuan.
 * Sumber hulu  : Semua isi dari layar pemanggil (objek ini tidak membaca database).
 * Input        : name, subtitle, groups [[label, badges => [[label, tone, icon]]]], progress (0-100) + progressText; slot photo, kpis, notice.
 * Output       : <div class="dl-pbanner"> dengan grid dua kolom (foto | identitas) dan baris KPI; turun satu kolom di layar sempit.
 * Dipakai oleh : resources/views/v2/screens/hrm-employee-form.blade.php (slot strip x-dl::form); demo devlib.test > Status > Profile Banner.
 * Status       : Aktif.
 */

namespace Devlib\Components;

use Devlib\Lang;

// Satu bentuk banner profil supaya layar lain (customer, supplier, mesin) memakai tata letak yang sama; gaya hanya di devlib.css.
class ProfileBanner extends DevlibComponent
{
    public function __construct(
        public string $name = '',
        public ?string $subtitle = null,
        public array $groups = [],
        public ?float $progress = null,
        public ?string $progressText = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::profile-banner';
    }

    public function source(): string
    {
        return 'Diisi layar pemanggil (mis. tm2mst.emp_employee + hasil hitung kelengkapan dan KPI)';
    }

    public function groupLabel(string $label): string
    {
        return Lang::text($label);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-pbanner') }} {!! $dl() !!}>
                <div class="dl-pbanner__top">
                    @isset($photo)<div class="dl-pbanner__photo">{{ $photo }}</div>@endisset
                    <div class="dl-pbanner__id">
                        <div class="dl-pbanner__name">{{ $name }}</div>
                        @if ($subtitle)<div class="dl-pbanner__sub">{{ $subtitle }}</div>@endif
                        @foreach ($groups as $g)
                            <div class="dl-pbanner__grp">
                                <span class="dl-pbanner__gl">{{ $groupLabel($g['label']) }}</span>
                                <span class="dl-pbanner__chips">
                                    @foreach ($g['badges'] as $b)<x-dl::badge :label="$b['label']" :tone="$b['tone'] ?? 'neutral'" :icon="$b['icon'] ?? null" />@endforeach
                                </span>
                            </div>
                        @endforeach
                        @if ($progress !== null)
                            <div class="dl-pbanner__grp">
                                <span class="dl-pbanner__gl">{{ $groupLabel('Profile') }}</span>
                                <span class="dl-pbanner__prog"><x-dl::progress :value="$progress" tone="auto" /></span>
                                @if ($progressText)<span class="dl-pbanner__progtxt">{{ $progressText }}</span>@endif
                            </div>
                        @endif
                    </div>
                </div>
                @isset($kpis)<div class="dl-pbanner__kpis">{{ $kpis }}</div>@endisset
                @isset($notice)<div class="dl-pbanner__notice">{{ $notice }}</div>@endisset
            </div>
            BLADE;
    }
}
