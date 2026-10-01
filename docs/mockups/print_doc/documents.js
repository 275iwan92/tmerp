// Satu kelas per jenis dokumen. Hanya mendeklarasikan FIELD yang dicetak; posisi diatur PrintDoc.
// Lebar `extra`: 84 mm (tanpa harga) atau 38 mm (berharga; HARGA + SUBTOTAL ditambahkan PrintDoc di posisi tetap).

// Acuan kertas: docs/attachments/surat jalan.jpg
PrintDoc.register(class SuratJalan extends PrintDoc {
    static title = 'SURAT JALAN';
    static numberLabel = 'No Surat Jalan';
    static qrHint = 'dokumen online<br>&amp; bukti terima';
    static refs = [
        {key: 'customer_po_no', label: 'No PO Customer', hand: true},
        {key: 'co_no', label: 'Ref. CO'},
        {key: 'vehicle', label: 'Kendaraan', hand: true},
        {key: 'driver', label: 'Pengemudi', hand: true},
        {key: 'departed_at', label: 'Diberangkatkan', hand: true},
    ];
    static partyLabel = 'Dikirim ke';
    static side = {key: 'contact', label: 'Telp / UP', hand: true};
    static nb = '<b>NB:</b>&nbsp;Pengaduan maksimum 7 hari setelah barang diterima';
    // IKAT hanya terisi bila barang dikemas per ikat; kosong untuk kemasan lain (keputusan user 2026-10-01).
    static extra = [{key: 'weight_kg', label: 'BERAT (KG)', width: 21, type: 'money'}, {key: 'bundles', label: 'IKAT', width: 13, type: 'int'}, {key: 'remark', label: 'KETERANGAN', width: 50}];
    static receivers = [['Penerima', 'Customer'], ['Pengemudi', '']];
});

// Acuan kertas: docs/attachments/customer order 1.jpg
PrintDoc.register(class CustomerOrder extends PrintDoc {
    static title = 'CUSTOMER ORDER';
    static subtitle = 'Sales Order';
    static numberLabel = 'No CO';
    static refs = [
        {key: 'customer', label: 'Customer'},
        {key: 'customer_po_no', label: 'No PO Customer'},
        {key: 'quotation_no', label: 'Ref. Quotation'},
        {key: 'payment_term', label: 'Syarat Bayar'},
        {key: 'pay_to', label: 'Pembayaran a.n.'},
    ];
    static partyLabel = 'Dikirim ke';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = '<b>NB:</b>&nbsp;Pesanan mengikat setelah dikonfirmasi. Perubahan jumlah / spesifikasi melalui revisi tertulis.';
    static nameLabel = 'NAMA PRODUK';
    static extra = [{key: 'cfg', label: 'CFG', width: 8}, {key: 'type', label: 'TYPE', width: 9}, {key: 'due_date', label: 'TGL KIRIM', width: 21, type: 'date'}];
    static priced = true;
    static receivers = [['Pemesan', 'Customer']];
});

// Acuan kertas: docs/attachments/purchase order.jpg
PrintDoc.register(class PurchaseOrder extends PrintDoc {
    static title = 'PURCHASE ORDER';
    static subtitle = 'Surat Pesanan';
    static numberLabel = 'No PO';
    static refs = [
        {key: 'pr_no', label: 'Ref. PR'},
        {key: 'payment_term', label: 'Syarat Bayar'},
        {key: 'handover', label: 'Dikirim / Diambil', hand: true},
        {key: 'delivery_date', label: 'Estimasi Tanggal', type: 'date', hand: true},
        {key: 'ship_to', label: 'Tujuan'},
    ];
    static partyLabel = 'Kepada Yth.';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = 'Dengan hormat, dengan ini kami memesan barang sebagai berikut:';
    static nameLabel = 'NAMA BARANG / JASA';
    static extra = [{key: 'disc', label: 'DISK.', width: 17}, {key: 'due_date', label: 'TGL KIRIM', width: 21, type: 'date'}];
    static priced = true;
    static receivers = [['Supplier', 'Menerima pesanan']];
});

// Acuan kertas: docs/attachments/expedisi.jpg (Jadwal Kirim; satu lembar per tugas armada)
PrintDoc.register(class JadwalKirim extends PrintDoc {
    static title = 'JADWAL KIRIM';
    static subtitle = 'Tugas Armada &middot; Internal';
    static numberLabel = 'No Tugas';
    static refs = [
        {key: 'start_time', label: 'Jam Mulai'},
        {key: 'finish_time', label: 'Jam Selesai'},
    ];
    static partyLabel = 'Kendaraan';
    static nb = '<b>NB:</b>&nbsp;IN / OUT = jam tiba dan jam selesai di tujuan, diisi pengemudi.';
    static nameLabel = 'TUJUAN / ITEM';
    static extra = [{key: 'kind', label: 'JENIS', width: 13}, {key: 'co_no', label: 'CO', width: 13}, {key: 'start', label: 'START', width: 11}, {key: 'stop', label: 'STOP', width: 10},
        {key: 'in', label: 'IN', width: 9, hand: true}, {key: 'out', label: 'OUT', width: 9, hand: true}, {key: 'note', label: 'NOTE', width: 19, wrap: true}];
    static receivers = [['Pengemudi', '']];
});

PrintDoc.register(class Inquiry extends PrintDoc {
    static title = 'INQUIRY';
    static subtitle = 'Permintaan Produk &middot; Internal';
    static numberLabel = 'No Inquiry';
    static refs = [
        {key: 'inquiry_type', label: 'Jenis'},
        {key: 'source', label: 'Sumber'},
        {key: 'sales', label: 'Sales'},
        {key: 'deadline_quotation', label: 'Batas Quotation', type: 'date'},
        {key: 'product_type', label: 'Tipe Produk'},
    ];
    static partyLabel = 'Customer';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = '<b>NB:</b>&nbsp;Dokumen internal, tanpa harga. Harga hanya terbit di Quotation setelah Pro-Dev dan Pro-Cost selesai.';
    static nameLabel = 'NAMA PRODUK / PART';
    static extra = [{key: 'size', label: 'UKURAN JADI (mm)', width: 31}, {key: 'material', label: 'BAHAN', width: 35}, {key: 'color', label: 'WARNA', width: 18}];
    static receivers = [['Diterima', 'Pro-Dev'], ['Diterima', 'Pro-Cost']];
});

PrintDoc.register(class PurchaseRequisition extends PrintDoc {
    static title = 'PURCHASE REQUISITION';
    static subtitle = 'Permintaan Pembelian &middot; Internal';
    static numberLabel = 'No PR';
    static refs = [
        {key: 'source', label: 'Sumber'},
        {key: 'purpose', label: 'Untuk'},
        {key: 'approval', label: 'Approval'},
    ];
    static partyLabel = 'Pemohon';
    static nb = '<b>NB:</b>&nbsp;Harga adalah estimasi. Harga final mengikuti Purchase Quotation dan Purchase Order.';
    static extra = [{key: 'code', label: 'KODE', width: 17}, {key: 'need_date', label: 'TGL PERLU', width: 21, type: 'date'}];
    static priced = true;
    static receivers = [['Diterima', 'Purchasing']];
});
