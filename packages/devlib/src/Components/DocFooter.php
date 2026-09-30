<?php

namespace Devlib\Components;

// Footer dokumen Master-Detail untuk layar yang totalnya dihitung SERVER (mis. CoOrder::totals()) -- tampilan sama dengan
// footer x-dl::lines (Form Master-Detail yang disetujui user 2026-09-30): kiri = tab penyesuaian (slot per kunci :tabs,
// mis. Discount / Tax / Other Costs) + kotak Terbilang di ruang kosong; kanan = Summary yang SELALU terlihat.
// summary: [[label, nilai teks, 'grand'|'dpp'?], ...] -- 'grand' = Grand Total (disorot), 'dpp' = garis pemisah DPP.
class DocFooter extends DevlibComponent
{
    public function __construct(
        public array $tabs = [],
        public array $summary = [],
        public ?string $words = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::doc-footer';
    }

    // Terbilang untuk nilai nol ("Nol rupiah" / "Zero ...") tidak ditampilkan.
    public function showWords(): bool
    {
        $w = trim((string) $this->words);

        return $w !== '' && ! preg_match('/^(nol|zero)\b/i', $w);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-lines__footer', 'dl-docfoot']) }} {!! $dl() !!}>
                <div class="dl-lines__left">
                    @if ($tabs)
                        <div class="dl-lines__adj dl-tabs" data-dl-tabs>
                            <div class="dl-tabs__bar" role="tablist">@foreach ($tabs as $k => $t)<button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $k }}">{{ $t }}</button>@endforeach</div>
                            @foreach ($tabs as $k => $t)<div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $k }}">{{ ${$k} ?? '' }}</div>@endforeach
                        </div>
                    @endif
                    @if ($showWords())<div class="dl-lines__words"><span>Terbilang</span><i>{{ $words }}</i></div>@endif
                </div>
                @if ($summary)
                    <div class="dl-lines__totals">
                        <div class="dl-lines__sumh">Summary</div>
                        @foreach ($summary as $row)
                            <div class="{{ ($row[2] ?? null) === 'grand' ? 'dl-lines__grand' : (($row[2] ?? null) === 'dpp' ? 'dl-lines__dpp' : '') }}"><span>{{ $row[0] }}</span><b>{{ $row[1] }}</b></div>
                        @endforeach
                    </div>
                @endif
            </div>
            BLADE;
    }
}
