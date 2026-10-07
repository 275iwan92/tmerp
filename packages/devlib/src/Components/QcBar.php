<?php

namespace Devlib\Components;

// Bilah QC cepat di tepi bawah halaman: status tahap ini + dua tombol, Approved (satu klik, langsung tersimpan beserta
// waktu) dan Need improvement (pop-up wajib berisi catatan apa yang perlu diperbaiki). Penyimpanan = milik aplikasi
// pemakai (POST ke action: {key, name, stage, status APPROVED|NEEDS_IMPROVEMENT, note}); riwayat tidak menimpa yang lama.
// Tiga lapis persetujuan berurutan (user 2026-10-07): QC (user) -> UAT_OFFICER (officer terkait) -> UAT_SUPERVISOR (atasannya).
// Ketiga lapis Approved = Ready to release (dicatat sebagai siap naik ke live).
// stage = lapis yang diputuskan bilah ini; chain = ringkasan semua lapis [stage => [label, status]] (chip di kiri, hanya info).
// status: null | APPROVED | NEEDS_IMPROVEMENT · by / at / note = keputusan terakhir tahap ini (data, tidak diterjemahkan).
class QcBar extends DevlibComponent
{
    public const STAGES = ['QC' => 'QC', 'UAT_OFFICER' => 'UAT officer', 'UAT_SUPERVISOR' => 'UAT supervisor'];

    public function __construct(
        public string $key = '',
        public string $name = '',
        public string $stage = 'QC',
        public ?string $status = null,
        public ?string $note = null,
        public ?string $by = null,
        public ?string $at = null,
        public ?string $action = null,
        public ?string $csrf = null,
        public bool $canReview = true,
        public array $chain = [],
        public bool $release = false,   // ketiga lapis Approved = siap rilis ke live
    ) {}

    public function tag(): string
    {
        return 'x-dl::qc-bar';
    }

    public function render()
    {
        return <<<'BLADE'
            @php($T = \Devlib\Lang::class)
            <div {{ $attributes->class(['dl-qc', 'is-approved' => $status === 'APPROVED', 'is-improve' => $status === 'NEEDS_IMPROVEMENT']) }} {!! $dl() !!} data-dl-qc data-key="{{ $key }}" data-name="{{ $name }}" data-stage="{{ $stage }}" data-action="{{ $action }}" data-csrf="{{ $csrf }}"
                 data-t-approved="{{ $T::text('Approved') }}" data-t-improve="{{ $T::text('Need improvement') }}" data-t-saved="{{ $T::text('QC decision saved') }}" data-t-failed="{{ $T::text('QC decision was not saved') }}" data-t-empty="{{ $T::text('Write what needs to be improved.') }}">
                <span class="dl-qc__name">{{ $name }}</span>
                @foreach ($chain as $st => [$label, $s])
                    <span @class(['dl-qc__chip', 'is-approved' => $s === 'APPROVED', 'is-improve' => $s === 'NEEDS_IMPROVEMENT', 'is-here' => $st === $stage])>{{ $label }}</span>
                @endforeach
                @if ($release)<span class="dl-qc__rel"><i class="fa-solid fa-rocket"></i> {{ $T::text('Ready to release') }}</span>@endif
                <span class="dl-qc__state" data-qc-state>
                    @if ($status === 'APPROVED')<i class="fa-solid fa-circle-check"></i> {{ $T::text('Approved') }}
                    @elseif ($status === 'NEEDS_IMPROVEMENT')<i class="fa-solid fa-pen-ruler"></i> {{ $T::text('Need improvement') }}
                    @else{{ $T::text('Not reviewed') }}@endif
                </span>
                <small class="dl-qc__meta" data-qc-meta>@if ($by){{ $by }} · {{ $at ? \Devlib\Locale::date($at) : '' }}@if ($status === 'NEEDS_IMPROVEMENT' && $note) · {{ $note }}@endif @endif</small>
                @if ($canReview)
                    <button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-qc-improve><i class="fa-solid fa-pen"></i> {{ $T::text('Need improvement') }}</button>
                    <button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-qc-ok><i class="fa-solid fa-check"></i> {{ $T::text('Approved') }}</button>
                @endif
                <div class="dl-mov" data-qc-dialog role="dialog" aria-modal="true">
                    <div class="dl-modal dl-modal--sm">
                        <div class="dl-modal__head"><h3>{{ $T::text('What needs to be improved?') }}</h3><button type="button" class="dl-modal__x" data-qc-cancel aria-label="{{ $T::text('Close') }}">&times;</button></div>
                        <div class="dl-modal__body">
                            <p class="dl-qc__for">{{ $name }}</p>
                            <textarea class="dl-input" rows="4" maxlength="500" data-qc-note placeholder="{{ $T::text('For example: the Hold button is too small') }}"></textarea>
                            <em class="dl-qc__err" data-qc-err></em>
                        </div>
                        <div class="dl-modal__foot">
                            <button type="button" class="dl-btn dl-btn--secondary dl-btn--md" data-qc-cancel>{{ $T::text('Cancel') }}</button>
                            <button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-qc-save>{{ $T::text('Save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
            BLADE;
    }
}
