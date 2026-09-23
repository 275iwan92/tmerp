<x-dl::filter-form id="lap-penjualan" title="Kriteria Laporan Penjualan">
    <x-dl::field.date-range name="dari" name-end="sampai" label="Periode" />
    <x-dl::field.lookup name="supplier_id" lookup="supplier" label="Customer / Supplier" />
    <x-dl::field.select name="status" label="Status Dokumen" :options="['DRAFT' => 'Draft', 'APPROVED' => 'Disetujui', 'CLOSED' => 'Selesai']" />
    <x-dl::field.checkboxes name="kategori" label="Kategori" :options="['Box', 'Sleeve', 'Label']" />
    <x-dl::field.money name="min_nilai" label="Nilai Minimum" currency="IDR" />
</x-dl::filter-form>
