{{-- 1 nama di Formasi Harian. $p = data orang dari RosterFormationService::matrix,
     $day = tanggal (mode Per hari) atau null (mode Ringkas: tanda ⇄ bila di hari mana pun). --}}
@php
    $chipMulti = $day ? ($p['multi'][$day] ?? null) : (reset($p['multi']) ?: null);
    $chipClass = match (true) {
        ! $p['skill'] && ! $p['borongan'] => 'border-red-300 bg-red-50 text-red-700',
        (bool) $chipMulti => 'border-orange-300 bg-orange-50 text-orange-700',
        $p['borongan'] => 'border-violet-300 bg-violet-50 text-violet-700',
        default => 'border-slate-300 bg-white text-slate-800',
    };
@endphp
<span title="{{ $p['name'] }} · {{ $p['code'] }}{{ $p['borongan'] ? ' · borongan' : '' }}{{ $chipMulti ? ' · juga di '.$chipMulti : '' }}{{ $p['skill'] ? '' : ' · belum bersertifikat unit ini' }}"
    class="inline-flex items-center gap-0.5 px-1.5 py-px m-px rounded-full border text-[11px] font-semibold uppercase whitespace-nowrap {{ $chipClass }}">{{ ! $p['skill'] && ! $p['borongan'] ? '⚠ ' : '' }}{{ $p['label'] }}@if ($p['borongan'])<span class="normal-case font-normal">· borongan</span>@endif @if ($chipMulti)<span class="normal-case font-normal">⇄ {{ $chipMulti }}</span>@endif</span>
