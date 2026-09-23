<x-dl::import title="Import Item Bahan Baku" template="template-item"
    :columns="[
        ['key' => 'code', 'label' => 'Kode Item', 'required' => true, 'aliases' => ['kode', 'item code'], 'example' => 'RM-KRT-0100'],
        ['key' => 'name', 'label' => 'Nama Item', 'required' => true, 'aliases' => ['nama', 'item name'], 'example' => 'Art Paper 150gr 65x100'],
        ['key' => 'uom', 'label' => 'Satuan', 'type' => 'lookup', 'lookup' => 'uom', 'required' => true, 'aliases' => ['uom', 'unit'], 'example' => 'LBR'],
        ['key' => 'price', 'label' => 'Harga', 'type' => 'number', 'aliases' => ['harga beli', 'price'], 'example' => '1.250,50'],
        ['key' => 'min', 'label' => 'Stok Minimum', 'type' => 'number', 'aliases' => ['min', 'minimum'], 'example' => '500'],
        ['key' => 'since', 'label' => 'Aktif Sejak', 'type' => 'date', 'aliases' => ['tanggal'], 'example' => '01/09/2026'],
    ]" />
<p class="dl-note" style="margin-top:10px">Coba unduh template, isi di Excel, simpan sebagai CSV, lalu tarik kembali ke kotak di atas. Export CSV tersedia di setiap grid (tombol Export = kolom yang tampil).</p>
