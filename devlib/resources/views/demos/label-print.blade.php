{{-- Form khusus cetak label gudang: satu objek untuk label lokasi (layout row) dan label item (layout stack).
     Di aplikasi: <x-mp::inv.label-print :form="Labels::locationForm($home)" /> atau Labels::itemForm($home). --}}
@php
    $sizes = \Devlib\Components\Label::SIZES;
    $papers = \Devlib\Components\LabelSheet::PAPERS;
    $legend = [['color' => '#2563eb', 'label' => 'Raw Material'], ['color' => '#b45309', 'label' => 'Semi Finished Goods'], ['color' => '#15803d', 'label' => 'Finished Goods']];
@endphp
<div class="dl-demo">
    <x-mp::inv.label-print :form="[
        'title' => 'Print Location Label', 'icon' => 'fa-qrcode', 'layout' => 'row', 'empty' => null, 'notice' => null, 'nothing' => null,
        'description' => 'A label for every bin: its code, storage area, capacity, a QR code for the phone camera and a barcode for the scanner.',
        'filter' => ['action' => '#', 'fields' => [
            ['wh', 'Building', [1 => 'Main Warehouse', 2 => 'Finished Goods Warehouse'], 1, ['tm2mst.wh_warehouse']],
            ['zone', 'Storage area (ticks all its bins)', [1 => 'A · Paper rack · Floor 1', 2 => 'B · Ink room · Floor 1'], 1, ['tm2mst.wh_zone']],
        ]],
        'print' => ['action' => '#', 'hidden' => ['type' => 'location'], 'keysLabel' => 'Bins', 'options' => ['11' => 'A-01-01', '12' => 'A-01-02', '13' => 'A-02-01'],
            'picked' => ['11', '12'], 'sources' => ['tm2mst.wh_loc'], 'copiesHint' => null, 'sizes' => $sizes, 'papers' => $papers, 'historyUrl' => '#'],
        'legend' => $legend,
    ]" />

    <x-mp::inv.label-print :form="[
        'title' => 'Print Item Label', 'icon' => 'fa-tag', 'layout' => 'stack', 'empty' => null, 'filter' => null, 'nothing' => null,
        'description' => 'A label for stock that is in a bin now: item code and name, quantity, bin, arrival date, lot and expiry date, a QR code and a barcode.',
        'notice' => ['message' => 'Item labels for goods receipt SPB2600031, received 03-Oct-2026. The items of this receipt are already ticked.',
            'buttons' => [['back', 'Back to Receipt', '#'], ['fa-pallet', 'Pallet Labels of this Receipt', '#']]],
        'print' => ['action' => '#', 'hidden' => ['type' => 'item', 'back' => 'label-item', 'rid' => 31], 'keysLabel' => 'Stock (item | bin | on hand)',
            'options' => ['RM|101|11' => 'PCC02002 Art carton 210gr | A-01-01 | 12.500 SHEET', 'RM|204|12' => 'IOH31 Ink black | B-01-02 | 40 KG'],
            'picked' => ['RM|101|11', 'RM|204|12'], 'sources' => ['tm2inv.v_rm_onhand'], 'copiesHint' => 'One label per pack or stack: type how many packs carry the same label.',
            'sizes' => $sizes, 'papers' => $papers, 'historyUrl' => '#'],
        'legend' => $legend,
    ]" />
</div>
