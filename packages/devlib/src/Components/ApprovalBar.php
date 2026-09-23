<?php

namespace Devlib\Components;

// Bilah approval di dalam form dokumen: tahapan status + tombol sesuai status + catatan + riwayat.
// DRAFT -> Ajukan -> SUBMITTED -> Setujui (APPROVED) / Tolak (REJECTED, alasan wajib) -> Revisi (kembali DRAFT).
// Selama SUBMITTED / APPROVED isian form induk terkunci. Event dl:approval-status {status, action, note}.
class ApprovalBar extends DevlibComponent
{
    public function __construct(
        public string $status = 'DRAFT',
        public array $history = [],
        public string $me = 'Saya',
        public string $approver = 'Atasan',
    ) {}

    public function tag(): string
    {
        return 'x-dl::approval-bar status='.$this->status;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-apbar') }} {!! $dl() !!} data-dl-apbar data-status="{{ $status }}" data-me="{{ $me }}" data-approver="{{ $approver }}" data-history="{{ json_encode($history) }}">
                <div class="dl-master__pipe dl-apbar__pipe" data-ab-pipe></div>
                <div class="dl-apbar__banner" data-ab-banner></div>
                <div class="dl-apbar__act" data-ab-act>
                    <x-dl::field.text name="approval_note" label="Catatan / Alasan" multiline hint="Wajib diisi bila menolak." />
                    <div class="dl-apbar__buttons">
                        <x-dl::button preset="submit" label="Ajukan" data-ab-do="submit" />
                        <x-dl::button preset="reject" data-ab-do="reject" />
                        <x-dl::button preset="approve" data-ab-do="approve" />
                        <x-dl::button variant="secondary" icon="edit" label="Revisi" data-ab-do="revise" />
                    </div>
                </div>
                <ul class="dl-appr__hist dl-apbar__hist" data-ab-hist></ul>
            </div>
            BLADE;
    }
}
