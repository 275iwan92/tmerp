{{-- Perbedaan REVISI sebelum -> sesudah (tm2sdi.iqp_revise_change) untuk layar ProCost v1 (docs/flow_product_master.md §4):
     supaya Costing tahu apa yang berubah dari product induk. Kosong / bukan REVISE = tidak tampil. --}}
@php
    $revChanges = \App\Models\Sdi\IqpReviseChange::where('inquiry_id', $inquiryId)->with('part:id_part,part_name')->get();
@endphp
@if ($revChanges->isNotEmpty())
    <div class="{{ $card ?? '' }} border-amber-300 bg-amber-50">
        <div class="font-bold text-[#92400e] mb-2">✏️ Revisi: yang berubah dari product induk (wajib dihitung ulang)</div>
        <table class="w-full text-[12px]">
            <thead><tr class="text-left text-[#64748b]"><th class="py-1 pr-3">Part</th><th class="py-1 pr-3">Yang berubah</th><th class="py-1 pr-3">Sebelum</th><th class="py-1 pr-3">Sesudah</th><th class="py-1">Diulang di</th></tr></thead>
            <tbody>
                @foreach ($revChanges as $c)
                    <tr class="border-t border-amber-200"><td class="py-1 pr-3">{{ $c->part?->part_name ?? 'Dokumen' }}</td><td class="py-1 pr-3 font-semibold">{{ $c->field_label }}</td><td class="py-1 pr-3">{{ $c->old_value ?? '—' }}</td><td class="py-1 pr-3 font-semibold">{{ $c->new_value ?? '—' }}</td><td class="py-1">{{ $c->impact }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
