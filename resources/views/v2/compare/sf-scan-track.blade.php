{{-- REMARK
Tujuan       : Layar pembanding sf-scan-track: salinan contoh statis dari katalog DevLib, dirender komponen x-mp milik aplikasi.
Sumber hulu  : Larik literal di berkas ini (tanpa kueri, tanpa kelas data).
Input        : Tidak ada.
Output       : Tampilan saja; aksi bernilai # tidak terkirim ke mana pun.
Dipakai oleh : resources/views/v2/compare.blade.php (route v2.compare).
Status       : Aktif (bahan pembanding).
--}}
{{-- Form khusus Track via QR / Barcode. 'result' memilih hasil yang tampil: none, transfer, pallet, bin atau item.
     Di aplikasi: <x-mp::inv.scan-track :desk="Labels::trackDesk($home)" /> --}}
<x-dl::box class="dl-demo">
    <x-mp::inv.scan-track :desk="['home' => '#', 'result' => [
        'type' => 'item', 'id' => 'RM-101', 'title' => 'PCC02002 · Art carton 210gr 65 x 100', 'description' => 'Raw Material · in 2 bin(s) · 14.500,00 on hand in total',
        'rows' => [
            ['id' => 1, 'bin' => 'A10-PPR-L01-01', 'qty' => 12500, 'unit' => 'SHEET', 'weight' => 1706, 'arrived' => '2026-09-12', 'last' => '2026-09-30', 'idle' => 5, 'state' => 'OK', 'tone' => null],
            ['id' => 2, 'bin' => 'A10-PPR-L03-02', 'qty' => 2000, 'unit' => 'SHEET', 'weight' => 273, 'arrived' => '2026-02-03', 'last' => '2026-02-03', 'idle' => 244, 'state' => 'Dead stock', 'tone' => 'danger'],
        ],
        'pallets' => null, 'expiryTones' => [],
        'moves' => [
            ['id' => 1, 'date' => '2026-09-30', 'doc_no' => 'SO2600120', 'move' => 'OUT', 'warehouse' => 'Main Warehouse', 'bin' => 'A10-PPR-L01-01', 'in' => 0, 'out' => 1500, 'adj' => 0, 'balance' => 12500],
            ['id' => 2, 'date' => '2026-09-12', 'doc_no' => 'SPB2600031', 'move' => 'IN', 'warehouse' => 'Main Warehouse', 'bin' => 'A10-PPR-L01-01', 'in' => 14000, 'out' => 0, 'adj' => 0, 'balance' => 14000],
        ],
    ]]" />
</x-dl::box>
