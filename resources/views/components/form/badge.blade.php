{{--
    Componentization Form (fase 2) -- pill status (DRAFT/CONFIRMED/dst.),
    pola yang sama persis berulang di header CO/Quotation/PR/PO/PV. Palet
    tone sengaja sama dengan grid stat-box (griduistandard.md §2) supaya
    bahasa warna konsisten lintas grid & form.
--}}
@props(['tone' => 'default'])
@php
    $class = match ($tone) {
        'active', 'success' => 'bg-[#e9f9ef] text-[#16a34a]',
        'warn' => 'bg-[#fef6e7] text-[#b45309]',
        'danger' => 'bg-[#fdecec] text-[#c0392b]',
        'info' => 'bg-[#eaf2ff] text-[#1d4ed8]',
        default => 'bg-[#eef1f5] text-[#4b5568]',
    };
@endphp
<span class="text-[10.5px] font-bold px-2.5 py-0.5 rounded-full {{ $class }}">{{ $slot }}</span>
