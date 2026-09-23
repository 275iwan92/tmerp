@php
    $tree = [
        ['caption' => 'Transaksi'],
        ['label' => 'Sales', 'icon' => 'fa-cart-shopping', 'items' => [
            ['key' => 'quo', 'label' => 'Quotation'], ['key' => 'co', 'label' => 'Customer Order', 'badge' => '4'],
            ['label' => 'Delivery', 'items' => [['key' => 'do', 'label' => 'Delivery Order'], ['key' => 'dn', 'label' => 'Delivery Notes'], ['label' => 'Retur', 'items' => [['key' => 'ret-cust', 'label' => 'Retur Customer'], ['key' => 'ret-rep', 'label' => 'Ganti Barang']]]]],
        ]],
        ['label' => 'Procurement', 'icon' => 'fa-truck', 'items' => [['key' => 'pr', 'label' => 'Purchase Requisition', 'badge' => '2', 'tone' => 'warn'], ['key' => 'po', 'label' => 'Purchase Order'], ['key' => 'pv', 'label' => 'Penerimaan Barang']]],
        ['label' => 'Inventory', 'icon' => 'fa-warehouse', 'items' => [['key' => 'sin', 'label' => 'Stock In'], ['key' => 'sout', 'label' => 'Stock Out'], ['key' => 'adj', 'label' => 'Adjustment']]],
        ['caption' => 'Laporan'],
        ['label' => 'Laporan', 'icon' => 'fa-chart-pie', 'items' => [['key' => 'r-sales', 'label' => 'Penjualan'], ['key' => 'r-stock', 'label' => 'Kartu Stok'], ['key' => 'r-out', 'label' => 'Outstanding PO', 'badge' => '7', 'tone' => 'danger']]],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings'],
    ];
    $right = [
        ['caption' => 'Pintasan'],
        ['key' => 'r-approve', 'label' => 'Menunggu Approval', 'icon' => 'approve', 'badge' => '5', 'tone' => 'danger'],
        ['key' => 'r-notif', 'label' => 'Notifikasi', 'icon' => 'notification', 'badge' => '3'],
        ['key' => 'r-chat', 'label' => 'Chat', 'icon' => 'chat'],
        ['label' => 'Bantuan', 'icon' => 'help', 'items' => [['key' => 'h-guide', 'label' => 'Panduan'], ['key' => 'h-ticket', 'label' => 'Buat Tiket'], ['key' => 'h-short', 'label' => 'Pintasan Keyboard']]],
    ];
@endphp
<div class="dl-sm-demo">
    <x-dl::side-menu id="demo-left" title="TELASIH MAKMUR" :items="$tree" active="co" />
    <div class="dl-sm-demo__page"><div><b>Area layar</b><br>Panel kiri: akordeon bertingkat, cari, favorit ☆ & terakhir dibuka, ciutkan ke rail ikon (flyout).<br>Panel kanan: panel pintasan di sisi kanan (bisa diciutkan juga).</div></div>
    <x-dl::side-menu id="demo-right" side="right" title="Panel Kanan" :items="$right" :search="false" />
</div>
