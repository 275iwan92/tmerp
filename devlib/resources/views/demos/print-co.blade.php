<x-dl::print-out title="Nota Customer Order" subtitle="Customer Order" number="CO2600011" date="2026-08-15" status="DIKONFIRMASI"
    :company="config('devlib.company')" :company-ppn="config('devlib.company_ppn')" :ppn="true" printed-by="admin"
    :parties="['Customer' => ['PT Contoh Snackindo', 'Gudang Pusat, Malang'], 'Ref. Quotation' => 'QT2600007', 'No. PO Customer' => 'PO/TLM/VIII/2026-041', 'Syarat Bayar' => 'Net 30']"
    :columns="[
        ['key' => 'name', 'label' => 'Nama Produk', 'sub' => 'code'],
        ['key' => 'spec', 'label' => 'Spesifikasi (bahan · finishing · warna)'],
        ['key' => 'ship', 'label' => 'Tgl Kirim', 'width' => '62px'],
        ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
        ['key' => 'price', 'label' => 'Harga/pc', 'type' => 'money', 'price' => true],
        ['key' => 'disc', 'label' => 'Disk.', 'price' => true],
        ['key' => 'subtotal', 'label' => 'Subtotal', 'type' => 'money', 'price' => true],
    ]"
    :rows="\App\Support\DemoData::coLines(24)"
    :totals="['of' => 'subtotal', 'discount' => 1404137, 'ppn' => 11, 'extra' => [['PPh 23 (jasa)', -343612]], 'net' => 'Net Diterima']"
    :signatures="[['Dibuat', 'Sales'], ['Diketahui', 'Ops Manager'], ['Disetujui', 'Customer']]">
    <x-slot:notes><b>Catatan:</b> Harga sudah termasuk ongkos kirim dalam kota Surabaya. Pengiriman bertahap sesuai Plan Delivery.</x-slot:notes>
</x-dl::print-out>
