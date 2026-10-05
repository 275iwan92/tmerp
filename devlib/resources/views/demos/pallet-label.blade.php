{{-- Form khusus Print Pallet Label. Contoh: gedung dipilih, satu posisi stok dibuka dengan barang yang belum berlabel.
     Di aplikasi: <x-mp::inv.pallet-label :desk="Pallets::labelDesk($home)" /> --}}
@php
    $legend = collect(range(1, 12))->map(fn ($m) => ['color' => \Devlib\Components\Label::MONTH_COLORS[$m], 'label' => strtoupper(date('M', mktime(0, 0, 0, $m, 1)))])->all();
    $kinds = ['' => 'Automatic (remnant when at or below the limit)', 'PALLET' => 'Pallet (full or opened)', 'PACK' => 'Pack (remnant: reams, boxes, cans)'];
@endphp
<div class="dl-demo">
    <x-mp::inv.pallet-label :desk="[
        'empty' => false, 'receipt' => false, 'sizes' => \Devlib\Components\Label::SIZES, 'papers' => \Devlib\Components\LabelSheet::PAPERS, 'legend' => $legend,
        'expiryTones' => ['Expired' => 'danger', 'Expires soon' => 'warn', 'OK' => 'success', 'No expiry date' => 'neutral'], 'notice' => null,
        'filter' => ['action' => '#', 'options' => [1 => 'A10 · Main Warehouse'], 'wh' => 1, 'historyUrl' => '#'],
        'pos' => [
            'title' => 'PCC02002 · Art carton 210gr 65 x 100', 'backUrl' => '#', 'description' => 'Bin A10-PPR-L01-01 · 12.500,00 SHEET on hand · 0,00 SHEET on 0 pallet label(s)',
            'relabelWarn' => null, 'done' => false, 'pallets' => null,
            'create' => ['action' => '#', 'hidden' => ['position' => 'RM|101|11', 'back' => 'label-pallet'],
                'info' => '12.500,00 SHEET in this bin is not on a pallet label yet. One line = one pallet: split the quantity with + Pallet, and copy the pallet code printed on the supplier label.',
                'arrived' => '2026-10-03', 'receiptNo' => null, 'receiptLocked' => false,
                'rows' => [['qty' => 6250, 'supplier_pallet_code' => '', 'lot_no' => '', 'expiry_date' => ''], ['qty' => 6250, 'supplier_pallet_code' => '', 'lot_no' => '', 'expiry_date' => '']],
                'codeHint' => 'as printed on the supplier label', 'kinds' => $kinds],
        ],
        'todo' => ['id' => 'pallet-todo-demo', 'needTones' => ['Arrival label' => 'warn', 'Relabel after use' => 'tangerine'],
            'rows' => [['id' => 'RM|101|11', 'open_url' => '#', 'item' => 'Art carton 210gr 65 x 100', 'code' => 'PCC02002', 'bin' => 'A10-PPR-L01-01', 'onhand' => 12500, 'labelled' => 0, 'diff' => 12500,
                'unit' => 'SHEET', 'pallets' => 0, 'arrived' => '2026-10-03', 'need' => 'Arrival label', 'tone' => null]],
            'stats' => [['label' => 'All'], ['label' => 'Arrival label', 'where' => ['need' => 'Arrival label'], 'tone' => 'warn'], ['label' => 'Relabel after use', 'where' => ['need' => 'Relabel after use'], 'tone' => 'tangerine']]],
        'all' => ['id' => 'pallets-wh-demo', 'rows' => [], 'stats' => []],
    ]" />
</div>
