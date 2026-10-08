{{-- Production Scheduling > Incoming Purchases: open PO / PR lines for the materials of active Work Orders against the date the job needs them. --}}
@php
    $rows = array_map(fn ($r) => ['doc' => raw($r['type'].' '.$r['doc']), 'material' => raw($r['material']), 'qty' => \Devlib\Locale::decimal($r['qty'], 2), 'arrives' => raw($r['arrives'] ?? '—'),
        'needed' => raw($r['needed'] ?? '—'), 'effect' => t($r['effect']), 'wo' => raw($r['wo'])], \App\Support\V2\Data\Ppic::incoming());
@endphp
<x-dl::card title="Incoming Purchases" description="Needed by = start of the first operation that uses the material. A late line sets the Work Order gate to Waiting and the planner works around it.">
    <x-dl::table :columns="['doc' => 'Document', 'material' => 'Material', 'qty' => 'Qty', 'arrives' => 'Arrives', 'needed' => 'Needed by', 'wo' => 'Work Order', 'effect' => 'Effect']" :rows="$rows" empty="No open purchases for the active Work Orders." :source="$src" />
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="fa-cart-shopping" label="Open Purchase Requisition" :href="route('v2.screen', 'purchase-requisition')" />
        <x-dl::button variant="secondary" icon="fa-file-invoice" label="Open Purchase Order" :href="route('v2.screen', 'purchase-order')" />
    </x-dl::action-bar>
</x-dl::card>
