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
    $occupant = $node->occupant;
    $children = $node->children;
    $isHighlighted = in_array($position->position_id, $highlightIds, true);
@endphp

<li class="flex flex-col items-center px-2" style="--line-color: {{ $color }}">
    <div class="w-[220px] shrink-0 bg-white border rounded-xl shadow-sm overflow-hidden {{ $isHighlighted ? 'border-[#f59e0b] ring-2 ring-[#fde68a]' : 'border-[#d7dbe2]' }}">
        <div class="p-3 flex items-center gap-2.5">
            @if ($occupant?->photo_path)
                <img src="{{ asset('storage/'.$occupant->photo_path) }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="">
            @else
                <div class="w-10 h-10 rounded-full bg-[#eef1f5] text-[#4b5568] text-[11px] font-bold flex items-center justify-center shrink-0">
                    {{ $occupant ? mb_strtoupper(mb_substr($occupant->full_name, 0, 2)) : '—' }}
                </div>
            @endif
            <div class="min-w-0">
                <p class="text-[12.5px] font-bold truncate">{{ $occupant->full_name ?? 'Vacant' }}</p>
                <p class="text-[10.5px] text-muted truncate">{{ $position->position_name }}</p>
            </div>
        </div>
        <div class="px-3 pb-2 flex items-center gap-2 text-[11px]">
            @if ($occupant?->phone)
                <a href="tel:{{ $occupant->phone }}" title="Telepon" class="text-accent">📞</a>
            @else
                <span class="text-[#c8ccd6]" title="Tidak ada No. HP">📞</span>
            @endif
            @if ($occupant?->email_personal)
                <a href="mailto:{{ $occupant->email_personal }}" title="Email" class="text-accent">✉️</a>
            @else
                <span class="text-[#c8ccd6]" title="Tidak ada email">✉️</span>
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
