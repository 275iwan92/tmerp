<x-dl::report title="Kartu Stok" subtitle="Stock Card Report" number="RPT-CARD/26/00318" :company="config('devlib.company')" printed-by="Rina — Staff Gudang"
    :criteria="['Kode Item' => 'RM-KRT-0004', 'Nama Item' => 'Art Paper 230gr 79 × 109 cm', 'UOM Stok' => 'LBR (Lembar)', 'Gudang' => 'GD-01 Bahan Baku', 'Lokasi' => 'R1-C3-S1', 'Periode' => '01/07/2026 — 21/08/2026', 'Metode Nilai' => 'AVG (Moving Average)']"
    :opening="450" group-by="month" :groups="['month' => 'Per Bulan']"
    :summary="[['label' => 'Saldo Awal', 'value' => 450], ['label' => 'Total Masuk', 'sum' => 'in', 'signed' => true, 'plus' => true], ['label' => 'Total Keluar', 'sum' => 'out'], ['label' => 'Penyesuaian', 'sum' => 'adj', 'signed' => true], ['label' => 'Saldo Akhir', 'last' => 'end']]"
    :columns="[
        ['key' => 'date', 'label' => 'Tanggal'],
        ['key' => 'doc', 'label' => 'No. Dokumen', 'sub' => 'src'],
        ['key' => 'type', 'label' => 'Tipe', 'type' => 'badge', 'tones' => ['IN' => 'info', 'OUT' => 'danger', 'RTP' => 'info', 'ADJ' => 'warn']],
        ['key' => 'ref', 'label' => 'Referensi / Keterangan'],
        ['key' => 'lot', 'label' => 'Lot'],
        ['key' => 'open', 'label' => 'Saldo Awal', 'type' => 'number'],
        ['key' => 'in', 'label' => 'Masuk', 'type' => 'number', 'sum' => true, 'signed' => true],
        ['key' => 'out', 'label' => 'Keluar', 'type' => 'number', 'sum' => true],
        ['key' => 'adj', 'label' => 'Penyesuaian', 'type' => 'number', 'sum' => true, 'signed' => true],
        ['key' => 'end', 'label' => 'Saldo Akhir', 'type' => 'number', 'running' => 'in - out + adj', 'prev' => 'open', 'bold' => true],
    ]"
    :rows="\App\Support\DemoData::stockCard()" paper="a4l"
    :signatures="[['Dibuat', 'Staff Gudang'], ['Diperiksa', 'Kepala Gudang']]" />
