<?php

/*
 * REMARK
 * Tujuan       : Satu baris pilihan di wizard dalam modal (emoji + judul + penjelasan + tombol aksi), mis. langkah jenis / sumber data.
 * Sumber hulu  : Daftar pilihan dari layar (mis. InquiryTypeCard::TYPES di layar inquiry).
 * Input        : emoji, title, description (English, diterjemahkan), badge + badgeTone (opsional), note (teks jadi, opsional),
 *                kind (kelas modifier warna garis kiri, mis. mp-typecard--new), off (nonaktif); slot = tombol aksi.
 * Output       : <div class="dl-wiz__opt ..."> emoji | judul + badge + penjelasan + catatan | slot </div>; gaya .dl-wiz* di devlib.css.
 * Dipakai oleh : screens/inquiry.blade.php (modal wiz-type, wiz-src-*).
 * Status       : Aktif.
 */

namespace Devlib\Components;

class WizardOption extends DevlibComponent
{
    public function __construct(
        public string $emoji = '',
        public string $title = '',
        public string $description = '',
        public ?string $badge = null,
        public string $badgeTone = 'neutral',
        public ?string $note = null,
        public string $kind = '',
        public bool $off = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::wizard-option';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-wiz__opt', $kind => $kind !== '', 'is-off' => $off]) }} {!! $dl() !!}>
                <span class="dl-wiz__emoji">{{ $emoji }}</span>
                <div class="dl-wiz__txt">
                    <b>{{ $title }}</b>@if ($badge) <x-dl::badge :label="$badge" :tone="$badgeTone" />@endif
                    <p>{{ $description }}</p>
                    @if ($note)<small>{{ $note }}</small>@endif
                </div>
                {{ $slot }}
            </div>
            BLADE;
    }
}
