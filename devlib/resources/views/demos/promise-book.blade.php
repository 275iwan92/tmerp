{{-- Form khusus Customer Plan Delivery. Layar hanya menyerahkan satu array :book; bagian bernilai null (hak / status tidak
     berlaku) tidak ditampilkan. Di aplikasi: <x-mp::sales.promise-book :book="Cpdel::promise($delivery, auth()->user(), $home)" /> --}}
@php
    $reasons = ['' => '—', 'MACHINE' => 'Machine breakdown', 'MATERIAL' => 'Material late', 'CAPACITY' => 'Capacity full'];
    $channels = ['WHATSAPP' => 'WhatsApp', 'PHONE' => 'Phone', 'EMAIL' => 'E-mail', 'VISIT' => 'Visit'];
@endphp
<div class="dl-demo">
    <x-mp::sales.promise-book :book="[
        'home' => '#', 'orderUrl' => '#', 'printUrl' => '#',
        'coNo' => 'CO2600001', 'product' => 'Folding Box Serum 30 ml',
        'state' => 'NEED_NEGOTIATION', 'stateLabel' => 'Need negotiation', 'stateTone' => 'warn', 'posted' => false,
        'facts' => [
            ['Original promise', '12-Oct-2026', ['tm2sdi.co_order_delivery.original_date']],
            ['Customer slot', '12-Oct-2026 09:00', ['tm2sdi.co_order_delivery.ship_date', 'ship_time']],
            ['Production ready', '14-Oct-2026 07:30', ['tm2sdi.co_order_delivery.ready_at']],
            ['Gap', '+2 days late', []],
            ['Qty', '10.000 pcs / order 25.000 pcs', ['tm2sdi.co_order_delivery.ship_qty']],
            ['Customer receives that day', '08:00 - 16:00', ['tm2mst.cust_receiving_hour']],
            ['Due date on the order', '12-Oct-2026', ['tm2sdi.co_order_item.due_date']],
            ['Production', 'not posted', ['tm2sdi.co_order_delivery.posted_at']],
        ],
        'next' => 'Next: Sales negotiates with the customer (WhatsApp / call), then changes the slot or splits the shipment. If the customer refuses, record it to escalate.',
        'problems' => [],
        'ppic' => ['action' => '#', 'date' => '2026-10-14', 'time' => '07:30', 'woDue' => '13-Oct-2026', 'reasons' => $reasons, 'reason' => 'MACHINE', 'note' => null],
        'slot' => ['action' => '#', 'date' => '2026-10-12', 'time' => '09:00', 'maxQty' => '10.000', 'reasons' => $reasons, 'reason' => null,
            'channels' => $channels, 'channel' => 'WHATSAPP', 'contact' => 'Ibu Rina', 'note' => null],
        'refuse' => ['action' => '#', 'channels' => $channels, 'contact' => 'Ibu Rina'],
        'decide' => null,
        'post' => ['action' => '#', 'description' => 'Creates the Work Order for this line.'],
        'postedInfo' => null,
        'merge' => ['action' => '#', 'id' => 1, 'options' => [2 => '19-Oct-2026 · 15.000 pcs']],
        'log' => [
            ['at' => '05-Oct-2026 10:12', 'who' => 'ppic (PPIC)', 'event' => 'Ready committed', 'change' => 'ready 14-Oct-2026 07:30', 'reason' => 'Machine breakdown', 'via' => '—', 'note' => 'Die-cut machine under repair'],
        ],
        'lines' => [
            ['date' => '12-Oct-2026 09:00', 'qty' => '10.000', 'ready' => '14-Oct-2026 07:30', 'state' => 'Need negotiation · this line', 'from' => '—'],
            ['date' => '19-Oct-2026', 'qty' => '15.000', 'ready' => '—', 'state' => 'Waiting for PPIC', 'from' => '#1'],
        ],
    ]" />
</div>
