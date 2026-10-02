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
    static approved = '2026-10-01';
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
// Satu baris = satu perhentian, TIGA baris teks: nama tempat + kota, alamat, lalu barang + No CO.
// Model waktu (koreksi user 2026-10-01): tiap perhentian punya ETA (rencana tiba) dan ETD (rencana berangkat dari
// tempat itu). Baris pertama = pangkalan: hanya ETD (mulai muat -> berangkat). Baris terakhir = kembali: hanya ETA.
// Dari situ dihitung dua durasi: di jalan = ETA perhentian ini - ETD perhentian sebelumnya; di tempat (muat /
// bongkar) = ETD - ETA. IN / OUT = jam tiba dan berangkat sebenarnya, ditulis tangan pengemudi (IN ~ ETA, OUT ~ ETD).
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
    static nb = '<b>NB:</b>&nbsp;ETA = rencana tiba, ETD = rencana berangkat dari tempat itu. IN / OUT = jam tiba dan berangkat sebenarnya, diisi pengemudi.';
    static nameLabel = 'TEMPAT &middot; KOTA / ALAMAT / BARANG';
    static extra = [{key: 'plan', label: 'JENIS / ETA - ETD', width: 40, type: 'html'}, {key: 'in', label: 'IN', width: 22, hand: true}, {key: 'out', label: 'OUT', width: 22, hand: true}];
    static receivers = [['Pengemudi', '']];
    static infoRows = 3;
    static tailHeight = 2;
    static rowHeight = 13;
    static rows = {a5: 5, a4: 15};
    static WORK = {MUAT: 'muat', AMBIL: 'muat', KIRIM: 'bongkar'};     // pekerjaan di tempat, per jenis perhentian

    // Selisih dua jam 'HH:MM' -> '45 mnt' / '2 j' / '1 j 30 mnt'.
    static span(from, to) {
        const min = t => { const [h, m] = t.split(':'); return h * 60 + +m; }, d = min(to) - min(from), h = Math.floor(d / 60), m = d % 60;
        return [h && `${h} j`, m && `${m} mnt`].filter(Boolean).join(' ') || '0 mnt';
    }

    // Kolom rencana: baris 1 jenis; baris 2 tiba (+ lama di jalan); baris 3 berangkat (+ lama muat / bongkar).
    static plan(r, prevEtd, sameAs) {
        const line = (l, d) => `<span><i>${l}</i>${d ? `<em>${d}</em>` : ''}</span>`, work = JadwalKirim.WORK[r.kind];
        if (sameAs) return `<b>${esc(r.kind)}</b>` + line(`<u>tempat sama, baris ${sameAs}</u>`);
        const arrive = r.eta ? line(`ETA ${esc(r.eta)}`, prevEtd && `jalan ${JadwalKirim.span(prevEtd, r.eta)}`) : line(`Mulai ${esc(r.start)}`);
        const leave = r.etd ? line(`ETD ${esc(r.etd)}`, `${work} ${JadwalKirim.span(r.eta ?? r.start, r.etd)}`) : '';
        return `<b>${esc(r.kind)}</b>${arrive}${leave}`;
    }

    constructor(data) {
        let prevEtd = null, stopRow = 0;
        super({...data, rows: data.rows.map((r, i) => {
            const plan = JadwalKirim.plan(r, prevEtd, r.same ? stopRow : 0);
            if (!r.same) { stopRow = i + 1; prevEtd = r.etd ?? prevEtd; }
            return {...r, name: [r.place, r.city].filter(Boolean).join(' · '), sub: r.address, plan,
                sub2: [r.co_no && 'CO ' + r.co_no, r.item, r.note].filter(Boolean).join(' · '), ...(r.same ? {in: 's.d.a.', out: 's.d.a.'} : {})};
        })});
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

// ===== Tahap 2 (user 2026-10-01: semua entry punya cetakan A5 landscape). Belum ada form kertas acuan: isi mengikuti
// field layar v2 masing-masing. Menunggu review user; belum ditandai A. =====

// Layar v2: quotation. Dokumen ke customer; kop PPN bila kena pajak.
PrintDoc.register(class Quotation extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'QUOTATION';
    static subtitle = 'Penawaran Harga';
    static numberLabel = 'No Quotation';
    static refs = [
        {key: 'attn', label: 'Attn'},
        {key: 'sales', label: 'Sales'},
        {key: 'valid_until', label: 'Berlaku s/d', type: 'date'},
        {key: 'payment_term', label: 'Syarat Bayar'},
        {key: 'currency', label: 'Mata Uang'},
    ];
    static partyLabel = 'Kepada Yth.';
    static side = {key: 'cc', label: 'CC'};
    static nb = 'Dengan hormat, bersama ini kami sampaikan penawaran harga sebagai berikut:';
    static nameLabel = 'PRODUK / SPESIFIKASI';
    static extra = [{key: 'inquiry_no', label: 'NO INQUIRY', width: 21}, {key: 'disc', label: 'DISK. %', width: 17}];
    static priced = true;
    static receivers = [['Customer', 'Menyetujui penawaran']];
});

// Layar v2: delivery-order. Dasar Surat Jalan dan penagihan; baris ditarik dari CPDEL yang sudah Posted.
PrintDoc.register(class DeliveryOrder extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'DELIVERY ORDER';
    static subtitle = 'Perintah Kirim &middot; Internal';
    static numberLabel = 'No DO';
    static refs = [
        {key: 'do_type', label: 'Jenis DO'},
        {key: 'planned_date', label: 'Rencana Kirim', type: 'date'},
        {key: 'actual_date', label: 'Tgl Kirim Aktual', type: 'date', hand: true},
        {key: 'tax', label: 'Pajak'},
        {key: 'region', label: 'Wilayah Tujuan'},
    ];
    static partyLabel = 'Dikirim ke';
    static side = {key: 'contact', label: 'Telp / UP', hand: true};
    static nb = '<b>NB:</b>&nbsp;Dasar pembuatan Surat Jalan dan penagihan. Baris berasal dari Customer Plan Delivery yang sudah Posted.';
    static nameLabel = 'NAMA PRODUK';
    static extra = [{key: 'co_no', label: 'NO CO', width: 17}, {key: 'price_source', label: 'ASAL HARGA', width: 21}];
    static priced = true;
    static receivers = [['Gudang', 'Menyiapkan barang']];
});

// Layar v2: proof-of-delivery. Bukti barang tiba dan diterima (KPI janji kirim).
PrintDoc.register(class ProofOfDelivery extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'BUKTI TERIMA';
    static subtitle = 'Proof of Delivery';
    static numberLabel = 'No Surat Jalan';
    static refs = [
        {key: 'promise', label: 'Janji Kirim'},
        {key: 'arrived_at', label: 'Tiba di Customer', hand: true},
        {key: 'result', label: 'Hasil', hand: true},
        {key: 'received_by', label: 'Diterima oleh', hand: true},
        {key: 'miss_cause', label: 'Sebab Meleset', hand: true},
    ];
    static partyLabel = 'Customer';
    static side = {key: 'contact', label: 'Telp / UP', hand: true};
    static nb = '<b>NB:</b>&nbsp;Hasil: DITERIMA / DITOLAK / CUSTOMER TUTUP. Janji ditepati bila barang tiba dan diterima pada waktu yang dijanjikan.';
    static extra = [{key: 'co_no', label: 'NO CO', width: 17}, {key: 'received_qty', label: 'DITERIMA', width: 21, type: 'money', hand: true}, {key: 'remark', label: 'KONDISI / CATATAN', width: 46, hand: true}];
    static receivers = [['Penerima', 'Customer'], ['Pengemudi', '']];
});

// Layar v2: purchase-quotation. Catatan penawaran supplier untuk dibandingkan.
PrintDoc.register(class PurchaseQuotation extends PrintDoc {
    static title = 'PURCHASE QUOTATION';
    static subtitle = 'Penawaran Supplier &middot; Internal';
    static numberLabel = 'No PQ';
    static refs = [
        {key: 'pr_no', label: 'Ref. PR'},
        {key: 'valid_until', label: 'Berlaku s/d', type: 'date'},
        {key: 'payment_term', label: 'Termin Bayar'},
        {key: 'currency', label: 'Mata Uang / Kurs'},
        {key: 'lead_time', label: 'Lead Time'},
    ];
    static partyLabel = 'Supplier';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = '<b>NB:</b>&nbsp;Catatan penawaran supplier untuk dibandingkan. Bukan pesanan; pesanan terbit sebagai Purchase Order.';
    static extra = [{key: 'pr_line', label: 'BARIS PR', width: 17}, {key: 'lead_days', label: 'LEAD (HARI)', width: 21, type: 'int'}];
    static priced = true;
});

// Layar v2: purchase-receive (SPB). Dokumen gudang, tanpa harga.
PrintDoc.register(class GoodsReceipt extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'SURAT PENERIMAAN BARANG';   // nama dokumen = SPB, Surat Penerimaan Barang (user 2026-10-02)
    static subtitle = 'SPB &middot; Goods Receipt';
    static numberLabel = 'No SPB';
    // Tanpa field "Jenis" (user 2026-10-02): SPB hanya untuk penerimaan dari supplier, nomornya urut untuk pemeriksaan
    // tagihan supplier oleh accounting. Bahan dari customer dan barang dari subkon = judul dan seri nomor sendiri (di bawah).
    static refs = [
        {key: 'po_no', label: 'No PO'},
        {key: 'supplier_dn', label: 'No SJ Pengirim'},
        {key: 'vehicle', label: 'No Kendaraan'},
        {key: 'warehouse', label: 'Gudang'},
    ];
    static partyLabel = 'Diterima dari';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = '<b>NB:</b>&nbsp;JUMLAH = jumlah datang. Barang ditolak dikembalikan atau menunggu keputusan Purchasing.';
    static extra = [{key: 'accepted', label: 'DITERIMA', width: 19, type: 'money'}, {key: 'rejected', label: 'DITOLAK', width: 19, type: 'money'}, {key: 'lot_no', label: 'LOT / ROLL', width: 21},
        {key: 'remark', label: 'KETERANGAN', width: 25}];
    static receivers = [['Pengirim', 'Supplier / Ekspedisi']];
});

// Bahan baku milik customer untuk satu Customer Order: judul dan seri nomor sendiri (PBC), bukan SPB.
PrintDoc.register(class CustomerMaterialReceipt extends PrintDoc.registry.GoodsReceipt {
    static approved = null;
    static title = 'PENERIMAAN BAHAN BAKU CUSTOMER';
    static subtitle = 'PBC &middot; Customer Material Receipt';
    static numberLabel = 'No Penerimaan';
    static refs = [
        {key: 'co_no', label: 'No CO'},
        {key: 'supplier_dn', label: 'No SJ Pengirim'},
        {key: 'vehicle', label: 'No Kendaraan'},
        {key: 'warehouse', label: 'Gudang'},
    ];
    static nb = '<b>NB:</b>&nbsp;Bahan milik customer, hanya untuk Customer Order di atas. Bukan stok perusahaan.';
    static receivers = [['Pengirim', 'Customer / Ekspedisi']];
});

// Barang kembali dari subkon: judul dan seri nomor sendiri (PBS), bukan SPB.
PrintDoc.register(class SubconGoodsReceipt extends PrintDoc.registry.GoodsReceipt {
    static approved = null;
    static title = 'PENERIMAAN BARANG DARI SUBKON';
    static subtitle = 'PBS &middot; Goods Receipt from Subcon';
    static numberLabel = 'No Penerimaan';
    static refs = [
        {key: 'po_no', label: 'No PO'},
        {key: 'our_dn', label: 'No SJ Subkon'},
        {key: 'supplier_dn', label: 'No SJ Pengirim'},
        {key: 'vehicle', label: 'No Kendaraan'},
        {key: 'warehouse', label: 'Gudang'},
    ];
    static nb = '<b>NB:</b>&nbsp;JUMLAH = jumlah yang kembali dari subkon. Kekurangan diselesaikan dengan subkon.';
    static receivers = [['Pengirim', 'Subkon / Ekspedisi']];
});

// Layar v2: material-booking. Kebutuhan bahan satu Customer Order.
PrintDoc.register(class MaterialBooking extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'MATERIAL BOOKING';
    static subtitle = 'Pemesanan Bahan &middot; Internal';
    static numberLabel = 'No Booking';
    static refs = [
        {key: 'co_no', label: 'No CO'},
        {key: 'co_date', label: 'Tanggal CO', type: 'date'},
        {key: 'calculated_at', label: 'Dihitung'},
        {key: 'confirmed_by', label: 'Dikonfirmasi'},
    ];
    static partyLabel = 'Customer';
    static nb = '<b>NB:</b>&nbsp;Kebutuhan bahan satu Customer Order. KURANG = belum tertutup stok, PO, atau PR.';
    static nameLabel = 'BAHAN / PRODUK';
    static extra = [{key: 'need_date', label: 'PERLU TGL', width: 21, type: 'date'}, {key: 'covered_by', label: 'DIPENUHI DARI', width: 28}, {key: 'short', label: 'KURANG', width: 21, type: 'money'},
        {key: 'coverage', label: 'STATUS', width: 14}];
    static receivers = [['Gudang', 'Menyiapkan bahan']];
});

// Layar v2: maintenance-request. Tanpa kolom jumlah: satu baris per gejala.
PrintDoc.register(class MaintenanceRequest extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'MAINTENANCE REQUEST';
    static subtitle = 'Laporan Kerusakan &middot; Internal';
    static numberLabel = 'No Request';
    static refs = [
        {key: 'machine', label: 'Mesin'},
        {key: 'kind', label: 'Jenis'},
        {key: 'priority', label: 'Prioritas'},
        {key: 'stopped', label: 'Mesin Berhenti'},
        {key: 'reported_by', label: 'Dilaporkan oleh'},
    ];
    static partyLabel = 'Keputusan';
    static side = {key: 'wo_no', label: 'Work Order'};
    static nb = '<b>NB:</b>&nbsp;Satu baris per gejala. TINDAKAN AWAL dan CATATAN TEKNISI diisi teknisi saat memeriksa.';
    static nameLabel = 'APA YANG TERJADI';
    static qtyCols = false;
    static extra = [{key: 'component', label: 'KOMPONEN', width: 34}, {key: 'first_action', label: 'TINDAKAN AWAL', width: 43, hand: true}, {key: 'tech_note', label: 'CATATAN TEKNISI', width: 43, hand: true}];
    static receivers = [['Pelapor', 'Produksi']];
});

// Layar v2: maintenance-wo. Tanpa kolom jumlah: satu baris per tugas checklist; hasil diisi teknisi.
PrintDoc.register(class MaintenanceWorkOrder extends PrintDoc {
    static approved = '2026-10-02';
    static title = 'MAINTENANCE WORK ORDER';
    static subtitle = 'Perintah Kerja Perawatan &middot; Internal';
    static numberLabel = 'No WO';
    static refs = [
        {key: 'machine', label: 'Mesin'},
        {key: 'wo_type', label: 'Jenis WO'},
        {key: 'priority', label: 'Prioritas'},
        {key: 'technician', label: 'Teknisi'},
        {key: 'planned', label: 'Rencana'},
    ];
    static partyLabel = 'Sumber';
    static side = {key: 'machine_stop', label: 'Mesin Berhenti'};
    static nb = '<b>NB:</b>&nbsp;HASIL: OK / NG. TERUKUR diisi untuk tugas ukur. Spare part dan jam kerja dicatat di sistem setelah selesai.';
    static nameLabel = 'TUGAS (CHECKLIST)';
    static qtyCols = false;
    static extra = [{key: 'kind', label: 'JENIS', width: 20}, {key: 'range', label: 'MIN - MAX', width: 26}, {key: 'result', label: 'HASIL', width: 20, hand: true}, {key: 'measured', label: 'TERUKUR', width: 22, hand: true},
        {key: 'note', label: 'CATATAN', width: 32, hand: true}];
    static receivers = [['Produksi', 'Menerima mesin kembali']];
});

// Layar v2: petty-cash. Bukti pengeluaran kas kecil (uang muka, penyelesaian / reimburse).
PrintDoc.register(class PettyCash extends PrintDoc {
    static title = 'BUKTI KAS KECIL';
    static subtitle = 'Petty Cash Voucher';
    static numberLabel = 'No Transaksi';
    static refs = [
        {key: 'tx_type', label: 'Jenis'},
        {key: 'fund', label: 'Kas'},
        {key: 'cash_po', label: 'Cash PO'},
        {key: 'advance', label: 'Uang Muka', type: 'money'},
        {key: 'journal', label: 'Jurnal'},
    ];
    static partyLabel = 'Dibayar kepada';
    static nb = '<b>NB:</b>&nbsp;Lampirkan nota asli. Selisih uang muka dikembalikan ke kas atau dibayarkan ke penerima.';
    static extra = [{key: 'code', label: 'KODE', width: 17}, {key: 'receipt_no', label: 'NO NOTA', width: 21}];
    static priced = true;
    static receivers = [['Penerima', 'Menerima uang']];
});

// ===== Tahap 3 (user 2026-10-02: "yang belum didesain saja"). Dokumen transaksional yang belum punya cetakan sama sekali.
// Isi mengikuti field layar / tabel aplikasi; belum ada form kertas acuan. Menunggu review user; belum A. =====

// Barang masuk TIDAK punya cetakan sendiri (user 2026-10-02): yang diterima = yang masuk gudang, cukup satu dokumen, yaitu
// SPB (kelas GoodsReceipt). Yang diperlukan adalah QC Inspection untuk barang yang masuk.
PrintDoc.register(class QcInspection extends PrintDoc {
    static title = 'QC INSPECTION';
    static subtitle = 'Pemeriksaan Barang Masuk &middot; Internal';
    static numberLabel = 'No SPB';
    static refs = [
        {key: 'po_no', label: 'No PO'},
        {key: 'supplier_dn', label: 'No SJ Pengirim'},
        {key: 'warehouse', label: 'Gudang'},
        {key: 'checked_at', label: 'Waktu Periksa'},
        {key: 'decision', label: 'Keputusan'},
    ];
    static partyLabel = 'Supplier';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = '<b>NB:</b>&nbsp;JUMLAH = jumlah datang. Barang DITOLAK tidak masuk stok dan dikembalikan lewat Retur Pembelian.';
    static extra = [{key: 'qty_check', label: 'CEK JML', width: 14}, {key: 'quality', label: 'MUTU', width: 15}, {key: 'accepted', label: 'DITERIMA', width: 17, type: 'money'},
        {key: 'rejected', label: 'DITOLAK', width: 16, type: 'money'}, {key: 'qc_note', label: 'CATATAN QC', width: 22, wrap: true}];
    static internalLabels = ['Gudang', 'Purchasing', 'QC'];
    static internal = ['Mengetahui', 'Menyetujui', 'Pemeriksa'];
});

// Gudang (Bahan Baku / Barang Jadi / Setengah Jadi memakai format yang sama; Kategori di blok atas). Tanpa harga.
PrintDoc.register(class StockOut extends PrintDoc {
    static title = 'BUKTI BARANG KELUAR';
    static subtitle = 'Stock Out &middot; Internal';
    static numberLabel = 'No Dokumen';
    static refs = [
        {key: 'category', label: 'Kategori'},
        {key: 'warehouse', label: 'Gudang'},
        {key: 'purpose', label: 'Untuk Proses'},
        {key: 'wo_no', label: 'No WO'},
        {key: 'co_no', label: 'No CO'},
    ];
    static partyLabel = 'Diserahkan ke';
    static nb = '<b>NB:</b>&nbsp;JUMLAH = jumlah yang keluar dari stok. Sisa pemakaian dikembalikan lewat Retur dari Produksi.';
    static extra = [{key: 'code', label: 'KODE', width: 20}, {key: 'lot_no', label: 'LOT / ROLL', width: 22}, {key: 'location', label: 'LOKASI', width: 18}, {key: 'remark', label: 'KETERANGAN', width: 24}];
    static receivers = [['Penerima', 'Produksi']];
});

PrintDoc.register(class StockReturn extends PrintDoc.registry.StockOut {
    static title = 'RETUR DARI PRODUKSI';
    static subtitle = 'Return from Production &middot; Internal';
    static refs = [
        {key: 'category', label: 'Kategori'},
        {key: 'warehouse', label: 'Gudang'},
        {key: 'wo_no', label: 'No WO'},
        {key: 'out_no', label: 'Ref. Barang Keluar'},
        {key: 'reason', label: 'Alasan'},
    ];
    static partyLabel = 'Dikembalikan oleh';
    static nb = '<b>NB:</b>&nbsp;JUMLAH = jumlah yang kembali ke stok. KONDISI menentukan barang masuk stok baik atau stok rusak.';
    static extra = [{key: 'code', label: 'KODE', width: 20}, {key: 'lot_no', label: 'LOT / ROLL', width: 22}, {key: 'condition', label: 'KONDISI', width: 18}, {key: 'remark', label: 'KETERANGAN', width: 24}];
    static receivers = [['Penyerah', 'Produksi']];
});

// Penyesuaian stok dan stock opname: JUMLAH = hasil hitung fisik, dibanding stok sistem.
PrintDoc.register(class StockAdjustment extends PrintDoc {
    static title = 'PENYESUAIAN STOK';
    static subtitle = 'Stock Adjustment &amp; Physical Count';
    static numberLabel = 'No Dokumen';
    static refs = [
        {key: 'category', label: 'Kategori'},
        {key: 'warehouse', label: 'Gudang'},
        {key: 'kind', label: 'Jenis'},
        {key: 'period', label: 'Waktu Hitung'},
        {key: 'approval', label: 'Approval'},
    ];
    static partyLabel = 'Petugas Hitung';
    static side = {key: 'witness', label: 'Saksi'};
    static nb = '<b>NB:</b>&nbsp;JUMLAH = hasil hitung fisik. SELISIH = fisik dikurangi sistem (minus = kurang). Stok berubah setelah disetujui.';
    static extra = [{key: 'code', label: 'KODE', width: 18}, {key: 'system_qty', label: 'STOK SISTEM', width: 22, type: 'money'}, {key: 'diff', label: 'SELISIH', width: 20, type: 'money'}, {key: 'reason', label: 'ALASAN', width: 24}];
    static receivers = [['Saksi', 'Di luar gudang']];
});

PrintDoc.register(class PurchaseReturn extends PrintDoc {
    static title = 'RETUR PEMBELIAN';
    static subtitle = 'Purchase Return';
    static numberLabel = 'No Retur';
    static refs = [
        {key: 'pv_no', label: 'No SPB'},
        {key: 'po_no', label: 'No PO'},
        {key: 'settlement', label: 'Penyelesaian'},
        {key: 'returned_on', label: 'Tgl Dikembalikan', type: 'date', hand: true},
        {key: 'expedition', label: 'Ekspedisi', hand: true},
    ];
    static partyLabel = 'Kepada Yth.';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = 'Dengan hormat, bersama ini kami kembalikan barang berikut karena tidak sesuai pesanan:';
    static extra = [{key: 'code', label: 'KODE', width: 19}, {key: 'lot_no', label: 'LOT / ROLL', width: 21}, {key: 'reason', label: 'ALASAN', width: 44, wrap: true}];
    static receivers = [['Supplier', 'Menerima barang retur']];
});

// Perintah kerja produksi: satu WO = satu part. Baris = proses; hasil diisi operator di lantai produksi.
PrintDoc.register(class WorkOrder extends PrintDoc {
    static title = 'WORK ORDER';
    static subtitle = 'Perintah Kerja Produksi &middot; Internal';
    static numberLabel = 'No WO';
    static refs = [
        {key: 'co_no', label: 'No CO'},
        {key: 'customer', label: 'Customer'},
        {key: 'due_date', label: 'Due Date', type: 'date'},
        {key: 'priority', label: 'Prioritas'},
        {key: 'material_ready', label: 'Bahan Siap', type: 'date'},
    ];
    static partyLabel = 'Produk';
    static side = {key: 'order_qty', label: 'Qty Order'};
    static nb = '<b>NB:</b>&nbsp;JUMLAH = target per proses. MULAI, SELESAI, HASIL BAIK, REJECT, dan OPERATOR diisi di lantai produksi.';
    static nameLabel = 'PROSES / MESIN';
    static extra = [{key: 'start', label: 'MULAI', width: 16, hand: true}, {key: 'finish', label: 'SELESAI', width: 16, hand: true}, {key: 'good', label: 'HASIL BAIK', width: 19, hand: true},
        {key: 'reject', label: 'REJECT', width: 14, hand: true}, {key: 'operator', label: 'OPERATOR', width: 19, hand: true}];
    static receivers = [['Kepala Produksi', 'Menerima perintah']];
});

// Jurnal: tanpa kolom jumlah barang; Debit dan Kredit di kanan, total keduanya harus sama.
PrintDoc.register(class JournalVoucher extends PrintDoc {
    static title = 'JURNAL';
    static subtitle = 'Journal Voucher &middot; Internal';
    static numberLabel = 'No Jurnal';
    static refs = [
        {key: 'kind', label: 'Jenis'},
        {key: 'reference', label: 'Referensi'},
        {key: 'posting_date', label: 'Tgl Posting', type: 'date'},
        {key: 'auto_reverse', label: 'Auto Reverse'},
    ];
    static partyLabel = 'Narasi';
    static nb = '<b>NB:</b>&nbsp;Total Debit harus sama dengan Total Kredit. Jurnal Posted tidak diubah; koreksi lewat jurnal balik.';
    static nameLabel = 'NAMA AKUN';
    static qtyCols = false;
    static extra = [{key: 'code', label: 'KODE AKUN', width: 24}, {key: 'narration', label: 'URAIAN', width: 46, wrap: true}, {key: 'debit', label: 'DEBIT', width: 25, type: 'money'},
        {key: 'credit', label: 'KREDIT', width: 25, type: 'money'}];

    totals() {
        const sum = k => this.d.rows.reduce((t, r) => t + (+r[k] || 0), 0);
        return {lines: [['Total Debit', Fmt.num(sum('debit')), true], ['Total Kredit', Fmt.num(sum('credit')), true]]};
    }
});

// Tanda terima tagihan supplier (tukar faktur). Koreksi user 2026-10-02: yang dicetak bukan "Tagihan Supplier" melainkan
// tanda terimanya. Satu baris = satu invoice supplier yang diserahkan; lembar ini dibawa pulang supplier sebagai bukti.
PrintDoc.register(class SupplierInvoiceReceipt extends PrintDoc {
    static title = 'TANDA TERIMA TAGIHAN SUPPLIER';   // huruf judul dikecilkan di printdoc.css supaya muat di samping kop PPN
    static subtitle = 'Supplier Invoice Receipt';
    static numberLabel = 'No Tanda Terima';
    static refs = [
        {key: 'payment_term', label: 'Syarat Bayar'},
        {key: 'pay_date', label: 'Rencana Bayar', type: 'date'},
        {key: 'pay_method', label: 'Cara Bayar'},
    ];
    static partyLabel = 'Diterima dari';
    static side = {key: 'handed_by', label: 'Diserahkan oleh', hand: true};
    static nb = '<b>NB:</b>&nbsp;Tanda terima ini bukan bukti pembayaran. Dibayar sesuai Rencana Bayar setelah dicocokkan dengan PO dan SPB.';
    static nameLabel = 'NO INVOICE SUPPLIER';
    static qtyCols = false;
    static extra = [{key: 'invoice_date', label: 'TGL INVOICE', width: 22, type: 'date'}, {key: 'po_no', label: 'NO PO', width: 30}, {key: 'tax_invoice', label: 'NO FAKTUR PAJAK', width: 38},
        {key: 'amount', label: 'NILAI TAGIHAN', width: 30, type: 'money'}];
    static receivers = [['Supplier', 'Yang menyerahkan']];
    static internalLabels = ['Mengetahui', '', 'Penerima'];
    static internal = ['', '', 'Finance'];

    totals() {
        const grand = Math.round(this.d.rows.reduce((t, r) => t + (+r.amount || 0), 0) * 100) / 100;
        return {lines: [['TOTAL (IDR)', Fmt.num(grand), true]], grand};
    }
});

// Tagihan uang muka ke customer.
PrintDoc.register(class DownPaymentInvoice extends PrintDoc {
    static title = 'TAGIHAN UANG MUKA';
    static subtitle = 'Down Payment Invoice';
    static numberLabel = 'No Tagihan';
    static refs = [
        {key: 'co_no', label: 'No CO'},
        {key: 'customer_po_no', label: 'No PO Customer'},
        {key: 'due_date', label: 'Jatuh Tempo', type: 'date'},
        {key: 'payment_term', label: 'Syarat Bayar'},
        {key: 'pay_to', label: 'Pembayaran a.n.'},
    ];
    static partyLabel = 'Kepada Yth.';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = 'Dengan hormat, bersama ini kami tagihkan uang muka atas pesanan berikut:';
    static nameLabel = 'URAIAN';
    static extra = [{key: 'co_ref', label: 'NO CO', width: 21}, {key: 'percent', label: 'PERSEN', width: 17}];
    static priced = true;
    static receivers = [['Customer', 'Menerima tagihan']];
});

// Tanda terima uang muka (user 2026-10-02): bukti untuk customer bahwa uang muka sudah kami terima.
PrintDoc.register(class DownPaymentReceipt extends PrintDoc {
    static title = 'TANDA TERIMA UANG MUKA';
    static subtitle = 'Down Payment Receipt';
    static numberLabel = 'No Tanda Terima';
    static refs = [
        {key: 'invoice_no', label: 'No Tagihan'},
        {key: 'co_no', label: 'No CO'},
        {key: 'customer_po_no', label: 'No PO Customer'},
        {key: 'pay_method', label: 'Cara Bayar'},
        {key: 'received_at', label: 'Diterima di'},
    ];
    static partyLabel = 'Diterima dari';
    static side = {key: 'contact', label: 'Telp / UP'};
    static nb = 'Telah kami terima uang muka atas pesanan berikut:';
    static nameLabel = 'URAIAN';
    static qtyCols = false;
    static extra = [{key: 'co_ref', label: 'NO CO', width: 28}, {key: 'order_value', label: 'NILAI PESANAN', width: 34, type: 'money'}, {key: 'percent', label: 'PERSEN', width: 20},
        {key: 'amount', label: 'DITERIMA', width: 38, type: 'money'}];
    static receivers = [['Customer', 'Yang menyerahkan']];
    static internalLabels = ['Mengetahui', '', 'Penerima'];
    static internal = ['', '', 'Finance'];

    totals() {
        const grand = Math.round(this.d.rows.reduce((t, r) => t + (+r.amount || 0), 0) * 100) / 100;
        return {lines: [['TOTAL (IDR)', Fmt.num(grand), true]], grand};
    }
});

// Customer Invoice (user 2026-10-02, docs/md_for_systems/customerinvoice_accounting.md): baris = barang yang diterima
// customer per Surat Jalan. Nilai invoice selalu penuh; uang muka tampil sebagai potongan dan yang ditagih = sisanya.
// Order PPN: kop CV. Langgeng Jaya Makmur, PPN 12% x DPP nilai lain (11/12 harga jual). Rekening transfer ikut identitas.
PrintDoc.register(class CustomerInvoice extends PrintDoc {
    static title = 'CUSTOMER INVOICE';
    static subtitle = 'Faktur Tagihan';
    static numberLabel = 'No Invoice';
    static refs = [
        {key: 'co_no', label: 'No CO'},
        {key: 'customer_po_no', label: 'No PO Customer'},
        {key: 'payment_term', label: 'Syarat Bayar'},
        {key: 'due_date', label: 'Jatuh Tempo', type: 'date'},
        {key: 'note', label: 'Catatan'},
    ];
    static partyLabel = 'Ditagih ke';
    static side = {key: 'tax_id', label: 'NPWP / NIK'};
    static nb = '<b>Pelunasan harap ditransfer ke {bank}</b>';
    static nameLabel = 'NAMA PRODUK';
    static extra = [{key: 'cfg', label: 'CFG', width: 9}, {key: 'type', label: 'TIPE', width: 9}, {key: 'dn', label: 'NO SRT JALAN', width: 20, wrap: true}];
    static priced = true;
    static receivers = [['Customer', 'Menerima invoice']];
    static rowHeight = 7.1;
    static tailHeight = 21;

    totals() {
        const d = this.d, r2 = v => Math.round(v * 100) / 100, sub = r2(d.rows.reduce((t, r) => t + +r.subtotal, 0));
        const ppn = d.ppn ? r2(r2(sub * 11 / 12) * d.ppn / 100) : 0, total = r2(sub + ppn), dp = d.dp ?? [], paid = r2(dp.reduce((t, x) => t + +x.amount, 0)), grand = r2(total - paid);
        const lines = [['Subtotal', Fmt.num(sub)]];
        if (ppn) lines.push([`PPN ${d.ppn}% × DPP Nilai Lain`, Fmt.num(ppn)]);
        if (!dp.length) return {lines: [...lines, ['TOTAL (IDR)', Fmt.num(total), true]], grand: total};
        if (ppn) lines.push(['Total', Fmt.num(total)]);
        dp.forEach(x => lines.push([`Uang Muka ${Fmt.date(x.date)}`, `(${Fmt.num(x.amount)})`]));
        return {lines: [...lines, ['SISA TAGIHAN', Fmt.num(grand), true]], grand};
    }

    render() { return super.render().replace('{bank}', esc(this.d.bank)); }
});

// HR: permohonan cuti / sakit / izin. Tanpa kolom jumlah barang.
PrintDoc.register(class LeaveRequest extends PrintDoc {
    static title = 'PERMOHONAN CUTI / IZIN';
    static subtitle = 'Leave Request &middot; Internal';
    static numberLabel = 'No Permohonan';
    static refs = [
        {key: 'employee_no', label: 'No Pegawai'},
        {key: 'department', label: 'Departemen'},
        {key: 'position', label: 'Jabatan'},
        {key: 'balance', label: 'Sisa Cuti'},
        {key: 'substitute', label: 'Pengganti', hand: true},
    ];
    static partyLabel = 'Pemohon';
    static side = {key: 'phone', label: 'No HP'};
    static nb = '<b>NB:</b>&nbsp;Diajukan sebelum tanggal cuti. Sakit lebih dari 1 hari disertai surat dokter.';
    static nameLabel = 'ALASAN';
    static qtyCols = false;
    static extra = [{key: 'type', label: 'JENIS', width: 30}, {key: 'from', label: 'DARI', width: 24, type: 'date'}, {key: 'until', label: 'SAMPAI', width: 24, type: 'date'},
        {key: 'days', label: 'HARI', width: 14, type: 'int'}, {key: 'boss_note', label: 'CATATAN ATASAN', width: 28, hand: true}];
    static internalLabels = ['Atasan Langsung', 'HRD', 'Pemohon'];
    static internal = ['Mengetahui', 'Menyetujui', ''];
});

// HR: permohonan tenaga kerja. Tanpa kolom jumlah barang; jumlah orang bilangan bulat.
PrintDoc.register(class ManpowerRequisition extends PrintDoc {
    static title = 'PERMOHONAN TENAGA KERJA';
    static subtitle = 'Manpower Requisition &middot; Internal';
    static numberLabel = 'No Permohonan';
    static refs = [
        {key: 'track', label: 'Jalur'},
        {key: 'requested_by', label: 'Diminta oleh'},
        {key: 'needed_date', label: 'Dibutuhkan Tgl', type: 'date'},
        {key: 'reason', label: 'Alasan'},
    ];
    static partyLabel = 'Departemen';
    static nb = '<b>NB:</b>&nbsp;Setelah disetujui, HRD membuka lowongan sesuai jumlah dan kualifikasi di bawah.';
    static nameLabel = 'POSISI';
    static qtyCols = false;
    static extra = [{key: 'headcount', label: 'JML ORANG', width: 22, type: 'int'}, {key: 'employment', label: 'STATUS KERJA', width: 30}, {key: 'qualification', label: 'KUALIFIKASI', width: 68, wrap: true}];
    static internalLabels = ['Kepala Departemen', 'Direksi', 'Pembuat'];
    static internal = ['Mengetahui', 'Menyetujui', ''];
    static receivers = [['HRD', 'Menerima permohonan']];
});
