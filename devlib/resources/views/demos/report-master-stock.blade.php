<x-dl::report title="Laporan Master Stok" subtitle="Master Stock Report — Bahan Baku" number="RPT-MST/26/00042" :company="config('devlib.company')" printed-by="Rina — Staff Gudang"
    :criteria="['Tanggal Awal' => '01/08/2026', 'Tanggal Akhir' => '21/08/2026', 'Kelas Stok' => 'Bahan Baku (RM)', 'Gudang' => 'Semua Gudang', 'Item Aktif' => 'Ya']"
    group-by="cat" :groups="['cat' => 'Per Kategori', 'wh' => 'Per Gudang']"
    :summary="[['label' => 'Total Saldo Awal', 'sum' => 'open'], ['label' => 'Total Masuk', 'sum' => 'in', 'signed' => true, 'plus' => true], ['label' => 'Total Keluar', 'sum' => 'out'], ['label' => 'Total Penyesuaian', 'sum' => 'adj', 'signed' => true], ['label' => 'Total Saldo Akhir', 'sum' => 'end']]"
    :columns="[
        ['key' => 'code', 'label' => 'Kode Item'],
        ['key' => 'name', 'label' => 'Nama Item', 'sub' => 'min'],
        ['key' => 'wh', 'label' => 'Gudang'],
        ['key' => 'uom', 'label' => 'UOM'],
        ['key' => 'open', 'label' => 'Saldo Awal', 'type' => 'number', 'sum' => true],
        ['key' => 'in', 'label' => 'Qty Masuk', 'type' => 'number', 'sum' => true, 'signed' => true],
        ['key' => 'out', 'label' => 'Qty Keluar', 'type' => 'number', 'sum' => true],
        ['key' => 'adj', 'label' => 'Penyesuaian', 'type' => 'number', 'sum' => true, 'signed' => true, 'plus' => true],
        ['key' => 'end', 'label' => 'Saldo Akhir', 'type' => 'number', 'sum' => true, 'bold' => true],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['OK' => 'success', '< MIN' => 'danger'], 'rowTones' => ['< MIN' => 'danger']],
    ]"
    :rows="\App\Support\DemoData::masterStock()" />
