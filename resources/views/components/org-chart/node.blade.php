{{--
    Simpul rekursif Struktur Organisasi (org-chart.blade.php). Anonymous
    component BOLEH include dirinya sendiri by tag name di Blade -- dipakai
    di sini utk render pohon tak terbatas kedalamannya (dibatasi depth di PHP,
    lihat OrgChart::tree()).

    Garis penghubung pakai teknik CSS klasik <ul><li>::before/::after (border
    atas+samping bikin siku), warna per-cabang lewat inline style (BUKAN
    class Tailwind dinamis -- Tailwind build-time scan tidak akan
    mendeteksi/compile class hex yang di-generate runtime, lihat catatan
    griduistandard soal avatar gradient pool utk masalah serupa; di sini
    dipilih inline style karena warnanya truly dynamic per-cabang, bukan dari
    palet tetap kecil).
--}}
@props(['node', 'depth' => 0, 'color' => '#94a3b8', 'highlightIds' => []])
@php
    $position = $node->position;
    // 1 jabatan bisa ditempati >1 orang sekaligus (mis. "Tia/Suro/Fardan" di
    // 1 kotak PPIC Planner pada mockup struktur asli) -- kotak tetap 1, tapi
    // daftar nama di dalamnya bisa lebih dari satu baris (2026-09-13).
    $occupants = $node->occupants;
    $first = $occupants->first();
    $children = $node->children;
    $isHighlighted = in_array($position->position_id, $highlightIds, true);
@endphp

<li class="flex flex-col items-center px-2" style="--line-color: {{ $color }}">
    <div class="w-[220px] shrink-0 bg-white border rounded-xl shadow-sm overflow-hidden {{ $isHighlighted ? 'border-[#f59e0b] ring-2 ring-[#fde68a]' : 'border-[#d7dbe2]' }}">
        <div class="p-3 flex items-center gap-2.5">
            @if ($occupants->count() <= 1)
                @if ($first?->photo_path)
                    <img src="{{ asset('storage/'.$first->photo_path) }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="">
                @else
                    <div class="w-10 h-10 rounded-full bg-[#eef1f5] text-[#4b5568] text-[11px] font-bold flex items-center justify-center shrink-0">
                        {{ $first ? mb_strtoupper(mb_substr($first->full_name, 0, 2)) : '—' }}
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-[12.5px] font-bold truncate">{{ $first->full_name ?? t('Vacant') }}</p>
                    <p class="text-[10.5px] text-muted truncate">{{ $position->position_name }}</p>
                </div>
            @else
                <div class="min-w-0">
                    <p class="text-[12.5px] font-bold leading-snug">{{ $occupants->pluck('full_name')->implode(' / ') }}</p>
                    <p class="text-[10.5px] text-muted truncate">{{ $position->position_name }} <span class="text-[9.5px]">({{ $occupants->count() }} {{ t('people') }})</span></p>
                </div>
            @endif
        </div>
        <div class="px-3 pb-2 flex items-center gap-2 text-[11px]">
            @if ($occupants->count() <= 1)
                @if ($first?->phone)
                    <a href="tel:{{ $first->phone }}" title="{{ t('Phone call') }}" class="text-accent">📞</a>
                @else
                    <span class="text-[#c8ccd6]" title="{{ t('No phone number') }}">📞</span>
                @endif
                @if ($first?->email_personal)
                    <a href="mailto:{{ $first->email_personal }}" title="{{ t('Email') }}" class="text-accent">✉️</a>
                @else
                    <span class="text-[#c8ccd6]" title="{{ t('No email') }}">✉️</span>
                @endif
            @endif
            <span class="ml-auto text-[10px] text-muted font-mono">{{ $position->department->department_name ?? '' }}</span>
        </div>
        <div class="h-[3px]" style="background-color: {{ $color }}"></div>
    </div>

    @if ($children->isNotEmpty())
        <ul class="flex org-chart-children" style="--line-color: {{ $color }}">
            @foreach ($children as $child)
                <x-org-chart.node :node="$child" :depth="$depth + 1" :color="$color" :highlight-ids="$highlightIds" />
            @endforeach
        </ul>
    @endif
</li>
