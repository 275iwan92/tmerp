<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Konsekuensi otomatis dari scope revisi (matriks pemicu, docs/inquiryproduct_delta_revise.md Tier 2):
// imposisi = DIMENSION; proof & due-date = ARTWORK; costing = MATERIAL|DIMENSION|COLOR|PROCESS. Diperbarui JS InquiryRevise.
class ReviseImpact extends DevlibComponent
{
    // kunci => [label, scope pemicu, alasan bila YA, alasan bila TIDAK]
    public const ROWS = [
        'imposisi' => ['📐 Imposisi ulang', ['DIMENSION'], 'ukuran berubah → up/plano dihitung ulang', 'dieline & ukuran tetap → layout reuse'],
        'proof' => ['🎨 Proof warna ulang', ['ARTWORK'], 'artwork berubah → verifikasi visual', 'artwork tetap'],
        'costing' => ['💰 Costing / HPP ulang', ['MATERIAL', 'DIMENSION', 'COLOR', 'PROCESS'], 'material/plate/proses berubah → biaya berubah', 'tak ada perubahan biaya'],
        'due' => ['📅 Due-date reset', ['ARTWORK'], '14 hari kerja dari approval proof baru', '14 hari kerja dari approval proof baru'],
    ];

    public function __construct(public array $scope = []) {}

    public function tag(): string
    {
        return 'x-mp::sales.revise-impact';
    }

    public function source(): string
    {
        return 'dihitung dari scope revisi (tidak disimpan)';
    }

    public function rows(): array
    {
        return array_map(fn ($r) => [...$r, (bool) array_intersect($r[1], $this->scope)], self::ROWS);
    }

    public function rules(): array
    {
        return array_map(fn ($r) => ['on' => $r[1], 'yes' => $r[2], 'no' => $r[3]], self::ROWS);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-impact') }} {!! $dl() !!} data-impact-rules="{{ json_encode($rules()) }}">
                <div class="mp-impact__head">Yang wajib diproses ulang</div>
                @foreach ($rows() as $key => [$label, $on, $yes, $no, $hit])
                    <div class="mp-impact__row" data-impact="{{ $key }}">
                        <span>{{ $label }}</span>
                        <x-dl::badge :label="$hit ? 'YA' : 'TIDAK'" :tone="$hit ? 'warn' : 'neutral'" data-impact-flag />
                        <small data-impact-why>{{ $hit ? $yes : $no }}</small>
                    </div>
                @endforeach
            </div>
            BLADE;
    }
}
