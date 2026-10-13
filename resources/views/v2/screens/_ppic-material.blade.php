{{--
 REMARK
 Tujuan       : Production Scheduling > Material Control: kesiapan bahan dan tooling per Work Order menurut hasil engine Material Planning.
 Sumber hulu  : Daftar operasi $ops dari layar production-scheduling (gate material, material_ready_at, gate_note) yang dihitung engine PPIC.
 Input        : $ops (array operasi), $src.
 Output       : x-dl::card berisi x-dl::table (Work Order, Customer, Material, Ready at, Tooling, Note) dan tombol ke Material Planning serta Material Booking.
 Dipakai oleh : Di-include production-scheduling (tab Material).
 Status       : Aktif.
--}}
@php
    $collection = collect($ops);
    $state = ['READY' => 'Ready', 'WAIT' => 'Waiting', 'BLOCK' => 'Blocked'];
    $rows = $collection->filter(fn ($r) => $r['seq'] === $collection->where('wo_id', $r['wo_id'])->min('seq'))
        ->map(fn ($r) => ['wo' => raw($r['wo_no']), 'customer' => raw($r['customer'] ?? '—'), 'gate' => t($state[$r['gates'][0]] ?? $r['gates'][0]),
            'arrives' => $r['material_ready_at'] ? \Devlib\Locale::dt($r['material_ready_at']) : '—', 'note' => raw($r['gate_note'] ?? ''), 'tooling' => t($state[$r['gates'][1]] ?? $r['gates'][1])])->values()->all();
@endphp
<x-dl::card title="Material Control" description="Material readiness of every Work Order. Bookings are confirmed automatically when stock covers the need; shortages and late arrivals stay with inventory control.">
    <x-dl::table :columns="['wo' => 'Work Order', 'customer' => 'Customer', 'gate' => 'Material', 'arrives' => 'Ready at', 'tooling' => 'Tooling', 'note' => 'Note']" :rows="$rows" empty="No Work Orders yet." :source="$src" />
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="fa-boxes-stacked" label="Open Material Planning" :href="route('v2.screen', 'material-planning')" />
        <x-dl::button variant="secondary" icon="fa-lock" label="Open Material Booking" :href="route('v2.screen', 'material-booking')" />
    </x-dl::action-bar>
</x-dl::card>
