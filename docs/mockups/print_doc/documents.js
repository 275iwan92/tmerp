// Satu kelas per jenis dokumen. Hanya mendeklarasikan FIELD yang dicetak; posisi diatur PrintDoc.
// Lebar `extra`: 84 mm (tanpa harga) atau 38 mm (berharga; HARGA + SUBTOTAL ditambahkan PrintDoc di posisi tetap).

// Acuan kertas: docs/attachments/surat jalan.jpg
PrintDoc.register(class SuratJalan extends PrintDoc {
    static approved = '2026-10-01';
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
    static approved = '2026-10-01';     // kedua format (dengan harga dan tanpa harga)
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

// Customer Order format kedua: tanpa harga (untuk pihak yang tidak boleh melihat harga). Field sama, kolom harga dan
// kotak total hilang, diganti KETERANGAN (keputusan user 2026-10-01: CO punya 2 format).
PrintDoc.register(class CustomerOrderNoPrice extends PrintDoc.registry.CustomerOrder {
    static subtitle = 'Sales Order &middot; Tanpa Harga';
    static extra = [...PrintDoc.registry.CustomerOrder.extra, {key: 'remark', label: 'KETERANGAN', width: 46}];
    static priced = false;
});

// CPDEL (Customer Plan Delivery): rencana kirim satu CO, TANPA HARGA. Satu baris = satu kiriman. Produk yang dipecah
// (split) dicetak berurutan dan ditandai tiga kali: garis tebal di kiri baris, kolom KIRIMAN "SPLIT 1/3" tebal, dan
// ringkasan "Split" di bawah tabel (keputusan user 2026-10-01: harus jelas jika terjadi split). `item` = id baris CO.
PrintDoc.register(class CustomerPlanDelivery extends PrintDoc {
    static title = 'CUSTOMER PLAN DELIVERY';
    static subtitle = 'Rencana Kirim &middot; Tanpa Harga';
    static numberLabel = 'No CO';
    static refs = [
        {key: 'customer', label: 'Customer'},
        {key: 'customer_po_no', label: 'No PO Customer'},
        {key: 'co_date', label: 'Tanggal CO', type: 'date'},
    ];
    static partyLabel = 'Dikirim ke';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = '<b>NB:</b>&nbsp;SPLIT = satu produk dikirim bertahap (garis tebal di kiri). JUMLAH adalah jumlah per kiriman.';
    static nameLabel = 'NAMA PRODUK';
    static extra = [{key: 'part', label: 'KIRIMAN', width: 17}, {key: 'ship_date', label: 'TGL KIRIM', width: 21, type: 'date'}, {key: 'ship_time', label: 'JAM', width: 11},
        {key: 'due_date', label: 'DUE CO', width: 21, type: 'date'}, {key: 'state', label: 'STATUS', width: 14}];
    // Keputusan user 2026-10-01: Operations Manager, PPIC, dan Sales (pembuat) saling setuju; terakhir Customer.
    // Urutan cetak tetap ketentuan umum: pihak luar paling kiri ... pembuat paling kanan.
    static receivers = [['Customer', 'Menyetujui jadwal']];
    static internalLabels = ['PPIC', 'Operations Manager', 'Sales'];
    static internal = ['Menyetujui', 'Menyetujui', 'Pembuat'];

    constructor(data) {
        const groups = {};
        data.rows.forEach(r => (groups[r.item] ??= []).push(r));
        const total = g => g.reduce((t, r) => t + +r.qty, 0);
        const rows = data.rows.map(r => {
            const g = groups[r.item];
            return g.length > 1 ? {...r, cls: 'split', part: `SPLIT ${g.indexOf(r) + 1}/${g.length}`} : {...r, part: 'Penuh'};
        });
        const notes = Object.values(groups).filter(g => g.length > 1).map(g => ['Split', `${g[0].name} — ${Fmt.num(total(g))} ${g[0].unit} = ${g.map(r => Fmt.num(r.qty)).join(' + ')}`]);
        super({...data, rows, notes: [...notes, ...(data.notes ?? [])]});
    }
});

// Acuan kertas: docs/attachments/purchase order.jpg
PrintDoc.register(class PurchaseOrder extends PrintDoc {
    static approved = '2026-10-01';
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

// Acuan kertas: docs/attachments/expedisi.jpg (Jadwal Kirim; satu lembar per tugas armada).
// Satu baris = satu perhentian, TIGA baris teks: nama tujuan / tempat ambil + kota, alamat, lalu barang + No CO.
// Ruang kosong blok atas (3 baris field saja) dan zona penutup dipindah ke tinggi baris, dan kolom barang dihapus,
// supaya kolom tulis tangan IN / OUT besar (keputusan user 2026-10-01).
PrintDoc.register(class JadwalKirim extends PrintDoc {
    static title = 'JADWAL KIRIM';
    static subtitle = 'Tugas Armada &middot; Internal';
    static numberLabel = 'No Tugas';
    static refs = [
        {key: 'start_time', label: 'Jam Mulai'},
        {key: 'finish_time', label: 'Jam Selesai'},
    ];
    static partyLabel = 'Kendaraan';
    static side = {key: 'driver', label: 'Pengemudi'};
    static nb = '<b>NB:</b>&nbsp;JAM = rencana tiba. IN / OUT = jam tiba dan jam selesai sebenarnya di tujuan, diisi pengemudi.';
    static nameLabel = 'TUJUAN &middot; KOTA / ALAMAT / BARANG';
    static extra = [{key: 'kind', label: 'JENIS', width: 13}, {key: 'time', label: 'JAM', width: 11}, {key: 'in', label: 'IN', width: 30, hand: true}, {key: 'out', label: 'OUT', width: 30, hand: true}];
    static receivers = [['Pengemudi', '']];
    static infoRows = 3;
    static tailHeight = 2;
    static rowHeight = 13;
    static rows = {a5: 5, a4: 15};

    constructor(data) {
        super({...data, rows: data.rows.map(r => ({...r, name: [r.place, r.city].filter(Boolean).join(' · '), sub: r.address,
            sub2: [r.co_no && 'CO ' + r.co_no, r.item, r.note].filter(Boolean).join(' · ')}))});
    }
});

PrintDoc.register(class Inquiry extends PrintDoc {
    static approved = '2026-10-01';
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
    static approved = '2026-10-01';     // Produksi dan Non Produksi
    static title = 'PURCHASE REQUISITION';
    static subtitle = 'Permintaan Pembelian &middot; Internal';
    static numberLabel = 'No PR';
    static refs = [
        {key: 'category', label: 'Jenis'},
        {key: 'source', label: 'Sumber'},
        {key: 'purpose', label: 'Untuk'},
        {key: 'approval', label: 'Approval'},
    ];
    static partyLabel = 'Pemohon';
    static nb = '<b>NB:</b>&nbsp;Harga adalah estimasi. Harga final mengikuti Purchase Quotation dan Purchase Order.';
    // PR produksi: NO CO di kolom, nama product di baris kedua nama barang (`sub`), supaya HARGA dan SUBTOTAL estimasi
    // tetap muat di posisi tetap. PR non produksi (ATK, komputer): NO CO kosong, baris kedua = keterangan bila ada.
    static extra = [{key: 'co_no', label: 'NO CO', width: 17}, {key: 'need_date', label: 'TGL PERLU', width: 21, type: 'date'}];
    static priced = true;
    static receivers = [['Diterima', 'Purchasing']];
});
