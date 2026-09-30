<?php

namespace Devlib\Components;

// Approval Center (mockup pr/po_approval_center): daftar dokumen menunggu (kiri) + detail (kanan):
// ringkasan, baris item, alur approval berjenjang (PLT ditandai), riwayat, Tolak / Tahan / Setujui.
// Hanya-baca (user 2026-09-30): tidak ada isian di layar; alasan Tolak/Tahan wajib, diminta di dialog konfirmasi.
// docs: [['id','no','title','amount','party','date','submitted_by','summary' => [label => nilai],
//         'lines' => [[...]], 'steps' => [['role','by','at','status' => done|current|pending','plt','submitter']],
//         submitter = pembuat (bukan level approval); sisanya = Level 1..n, yang 'current' = level berjalan.
//         'history' => [['action','by','at','note','plt']]]]
// lineColumns: [kolom => label]; kolom berakhiran :money diformat Rupiah (mis. 'price:money').
// Keputusan memancarkan event dl:approval (detail: {id, action, note}) untuk disimpan aplikasi.
class Approval extends DevlibComponent
{
    public function __construct(
        public array $docs = [],
        public array $lineColumns = [],
        public string $title = 'Menunggu Approval Saya',
        public string $approveLabel = 'Setujui',
        public ?string $holdLabel = 'Tahan',
        public string $rejectLabel = 'Tolak',
        public string $me = 'Saya',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::approval';
    }

    public function source(): string
    {
        return $this->source ?? 'dokumen menunggu approval';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-appr') }} {!! $dl() !!} data-dl-approval data-docs="{{ json_encode($docs) }}" data-cols="{{ json_encode($lineColumns) }}" data-me="{{ $me }}">
                <div class="dl-appr__head"><h2>{{ $title }}</h2><span class="dl-badge dl-badge--pill dl-badge--warn" data-a-count></span></div>
                <div class="dl-appr__grid">
                    <div class="dl-appr__list" data-a-list></div>
                    <div class="dl-appr__detail" data-a-detail>
                        <div data-a-body></div>
                        {{ $slot }}
                        <div class="dl-appr__action" data-a-action>
                            <div class="dl-appr__buttons">
                                <x-dl::button preset="reject" :label="$rejectLabel" data-a-do="reject" />
                                @if ($holdLabel)<x-dl::button preset="hold" :label="$holdLabel" data-a-do="hold" />@endif
                                <x-dl::button preset="approve" :label="$approveLabel" data-a-do="approve" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            BLADE;
    }
}
