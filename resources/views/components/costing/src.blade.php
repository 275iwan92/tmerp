{{--
    REMARK
    Tujuan: penanda kecil sumber isian ProCost yang disarankan engine (✦) atau diubah officer (✎); tooltip = sumber dan jumlah sampel.
    Sumber hulu: rekaman iqp_costing_recommendation (CostingAdvisor::recsFor).
    Input: rec (IqpCostingRecommendation|null). Output: span penanda, kosong bila tidak ada saran.
    Dipakai oleh: x-costing.field, pages::inquiry.costing (kotak Dynamic Parameters).
    Status: BARU 2026-10-16.
--}}
@props(['rec' => null])
@if ($rec && $rec->source !== 'OFFICER')
    @php
        $src = \App\Services\Costing\CostingAdvisor::SOURCES[$rec->source] ?? $rec->source;
        $fmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.');
        $tip = 'Saran engine: '.$fmt($rec->recommended_value).' | '.$src.($rec->source_note ? ': '.$rec->source_note : '').($rec->is_override ? ' | sudah Anda ubah' : '');
    @endphp
    <span title="{{ $tip }}" class="cursor-help normal-case font-bold {{ $rec->is_override ? 'text-[#b45309]' : 'text-[#1d4ed8]' }}">{{ $rec->is_override ? '✎' : ($rec->source === 'ESTIMATE' ? '~' : '✦') }}</span>
@endif
