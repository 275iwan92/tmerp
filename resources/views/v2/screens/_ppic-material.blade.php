{{-- Production Scheduling > Material Control: material readiness per Work Order as the engines see it (gate = Material Planning engine result). --}}
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
