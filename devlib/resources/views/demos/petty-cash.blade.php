{{-- Form khusus Kas Kecil. Layar menyerahkan satu array :desk; 'mode' memilih bagian yang tampil (tx, po, topup, setup, list).
     Di aplikasi: <x-mp::acc.petty-cash :desk="PettyCash::desk($fund, $home)"> grid daftar </x-mp::acc.petty-cash> --}}
@php
    $head = [
        'home' => '#', 'title' => 'Petty Cash Head Office · PC-01',
        'description' => 'Custodian: Sari · account 111400 Petty Cash · cash PO limit IDR 2.000.000 (Constants PO_CASH_MAX)',
        'stats' => [['Imprest Limit', 'IDR 5.000.000', null, null], ['Cash on Hand', 'IDR 3.250.000', 'success', null],
            ['Open Cash Advances', 'IDR 750.000', 'warn', null], ['To Top Up', 'IDR 1.000.000', 'info', null]],
        'showActions' => false, 'tx' => null, 'po' => null, 'topup' => null, 'setup' => null,
    ];
@endphp
<div class="dl-demo">
    {{-- mode po: PO Kontan yang kasbonnya sudah diberikan -> Advance Settlement --}}
    <x-mp::acc.petty-cash :desk="['mode' => 'po', 'po' => [
        'title' => 'Cash PO PO2600012 · Toko Maju Jaya', 'description' => 'Mode: Cash advance · PO status APPROVED · total IDR 750.000',
        'lines' => [['item' => 'Lakban bening 2 inch', 'qty' => 24, 'uom' => 'ROLL', 'est' => 360000], ['item' => 'Cutter besar', 'qty' => 10, 'uom' => 'PCS', 'est' => 390000]],
        'back' => '#', 'advance' => null,
        'settle' => ['action' => '#', 'reimburse' => false,
            'description' => 'Advance PCA2600007 IDR 750.000 to Budi. Enter the receipt amount per line; change or shortfall is calculated automatically.',
            'lines' => [['name' => 'nota[1]', 'label' => 'Receipt: Lakban bening 2 inch (24,00 ROLL)', 'value' => 360000],
                ['name' => 'nota[2]', 'label' => 'Receipt: Cutter besar (10,00 PCS)', 'value' => 390000]],
            'receiver' => null, 'confirm' => 'Post this receipt to petty cash?'],
    ]] + $head" />

    {{-- mode tx: satu transaksi yang sudah Posted, dengan kartu Cancel Transaction --}}
    <x-mp::acc.petty-cash :desk="['mode' => 'tx', 'tx' => [
        'title' => 'Cash Advance · PCA2600007', 'status' => 'Status: Posted',
        'facts' => [['Date', '03-Oct-2026', []], ['Cash PO', 'PO2600012', []], ['Receiver', 'Budi', []], ['Cash Effect', '− IDR 750.000', []],
            ['Advance', 'IDR 750.000', []], ['Receipt Amount', '—', []], ['Journal', 'JV2600311', []], ['Notes', '—', []]],
        'receiptUrl' => null, 'printUrl' => '#', 'cancelled' => null,
        'cancel' => ['action' => '#', 'reason' => null, 'confirm' => 'Cancel this petty cash transaction and reverse its journal?'],
    ]] + $head" />
</div>
