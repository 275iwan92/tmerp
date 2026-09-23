<x-dl::print-out title="Surat Penerimaan Barang" subtitle="Goods Receipt Note" number="SPB/26/00058" date="2026-08-21" status="TERPOSTING"
    :company="config('devlib.company')" printed-by="gudang.ari"
    :parties="['Tanggal terima' => '21 Agustus 2026', 'Gudang penerima' => 'GD-BB · Gudang Bahan Baku', 'Pesanan Pembelian' => ['2 PO', 'PO/26/00031 · PO/26/00035'], 'No. surat jalan' => 'SJ-CKN/8/1142', 'Supplier' => ['PT Contoh Kertas Nusantara', 'Surabaya'], 'No. kendaraan' => 'L 9182 UZ']"
    :columns="[
        ['key' => 'po', 'label' => 'Asal PO'],
        ['key' => 'name', 'label' => 'Kode & Nama Barang', 'sub' => 'code'],
        ['key' => 'qty_buy', 'label' => 'Qty', 'type' => 'number', 'group' => 'Satuan Beli'],
        ['key' => 'uom_buy', 'label' => 'UOM', 'group' => 'Satuan Beli'],
        ['key' => 'qty_stock', 'label' => 'Qty', 'type' => 'number', 'group' => 'Satuan Stok'],
        ['key' => 'uom_stock', 'label' => 'UOM', 'group' => 'Satuan Stok'],
        ['key' => 'ok', 'label' => 'Diterima', 'type' => 'number', 'group' => 'Hasil QC'],
        ['key' => 'reject', 'label' => 'Ditolak', 'type' => 'number', 'group' => 'Hasil QC'],
        ['key' => 'lot', 'label' => 'Lot'],
    ]"
    :rows="[
        ['po' => 'PO/26/00031', 'name' => 'Kertas Ivory 300gr 79x109', 'code' => 'PYJ31', 'qty_buy' => 5000, 'uom_buy' => 'LBR', 'qty_stock' => 5000, 'uom_stock' => 'LBR', 'ok' => 5000, 'reject' => '', 'lot' => 'IVR-2608-A'],
        ['po' => 'PO/26/00031', 'name' => 'Art Paper 150gr 65x100', 'code' => 'PYA21', 'qty_buy' => 5360, 'uom_buy' => 'LBR', 'qty_stock' => 5360, 'uom_stock' => 'LBR', 'ok' => 5240, 'reject' => 120, 'lot' => 'AP150-2608-B'],
        ['po' => 'PO/26/00031', 'name' => 'Fountain Solution (drum 200 L)', 'code' => 'CFS41', 'qty_buy' => 4, 'uom_buy' => 'DRUM', 'qty_stock' => 792, 'uom_stock' => 'LTR', 'ok' => 792, 'reject' => '', 'lot' => 'FS-2608-D4'],
        ['po' => 'PO/26/00035', 'name' => 'Art Paper 120gr 65x100', 'code' => 'PYA12', 'qty_buy' => 4000, 'uom_buy' => 'LBR', 'qty_stock' => 4000, 'uom_stock' => 'LBR', 'ok' => 4000, 'reject' => '', 'lot' => 'AP120-2608-C'],
    ]"
    :signatures="[['Diserahkan oleh', 'Pengirim / Sopir Supplier'], ['Diterima oleh', 'Petugas Gudang Bahan Baku'], ['Diperiksa oleh', 'Quality Control']]">
    <x-slot:notes><b>Catatan penerimaan:</b> baris 2 — 120 LBR ditolak QC (tepi sobek & kotor), tidak masuk stok, menjadi kandidat Retur Pembelian. Dokumen gudang ini tidak memuat harga.</x-slot:notes>
</x-dl::print-out>
