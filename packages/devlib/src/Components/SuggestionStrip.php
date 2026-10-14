<?php

namespace Devlib\Components;

/*
 * REMARK
 * Tujuan       : Strip usulan machine learning di atas Gantt: lencana ML, usulan urutan (chip berurutan) + tombol Preview / Use / Dismiss, atau keadaan Learning n / 30 + bar kemajuan.
 * Sumber hulu  : dihitung aplikasi (model urutan job); DevLib hanya menampilkan.
 * Input        : mode ready|learning, title, subtitle, items (label per job, berurutan), note, value + needed (learning), previewUrl, useUrl, dismissUrl, previewing.
 * Output       : <section class="dl-seqstrip"> ... </section>; kosong bila tidak ada isi.
 * Dipakai oleh : layar Work Order PPIC (Simulation).
 * Status       : Aktif. Teks hitam di atas latar lavender (token --dl-violet-soft); tombol memakai x-dl::button.
 */
class SuggestionStrip extends DevlibComponent
{
    protected const TEXT_PROPS = ['title', 'note'];

    protected const TEXT_LISTS = [];

    /**
     * @param  list<string>  $items
     */
    public function __construct(
        public string $mode = 'ready',
        public string $title = '',
        public string $subtitle = '',
        public array $items = [],
        public string $note = '',
        public float $value = 0,
        public float $needed = 0,
        public ?string $previewUrl = null,
        public ?string $useUrl = null,
        public ?string $dismissUrl = null,
        public bool $previewing = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::suggestion-strip mode='.$this->mode;
    }

    public function render()
    {
        return <<<'BLADE'
            <section {{ $attributes->class(['dl-seqstrip', 'dl-seqstrip--'.$mode]) }} {!! $dl() !!}>
                <div class="dl-seqstrip__main">
                    <x-dl::badge label="ML" tone="violet" />
                    <b class="dl-seqstrip__title">{{ $title }}</b>
                    @if ($subtitle !== '')<span class="dl-seqstrip__sub">{{ $subtitle }}</span>@endif
                    @if ($mode === 'ready')
                        <span class="dl-seqstrip__chips">
                            @foreach ($items as $i => $item)
                                @if ($i > 0)<i class="fa-solid fa-arrow-right"></i>@endif
                                <span class="dl-seqstrip__chip">{{ $i + 1 }} {{ $item }}</span>
                            @endforeach
                        </span>
                    @endif
                </div>
                @if ($mode === 'learning')
                    <div class="dl-seqstrip__learn">
                        <span class="dl-seqstrip__count">{{ \Devlib\Lang::text('Learning') }} {{ (int) $value }} / {{ (int) $needed }}</span>
                        <x-dl::progress :value="$needed > 0 ? $value / $needed * 100 : 0" />
                    </div>
                @endif
                <p class="dl-seqstrip__note">{{ $note }}</p>
                @if ($mode === 'ready')
                    <div class="dl-seqstrip__actions">
                        @if ($previewUrl)<x-dl::button size="sm" variant="secondary" icon="fa-eye" label="{{ $previewing ? 'Hide preview' : 'Preview' }}" :href="$previewUrl" />@endif
                        @if ($useUrl)<x-dl::action-form action="{{ $useUrl }}"><x-dl::button size="sm" icon="fa-check" label="Use" type="submit" /></x-dl::action-form>@endif
                        @if ($dismissUrl)<x-dl::action-form action="{{ $dismissUrl }}"><x-dl::button size="sm" variant="secondary" icon="fa-xmark" label="Dismiss" type="submit" /></x-dl::action-form>@endif
                    </div>
                @endif
            </section>
            BLADE;
    }
}
