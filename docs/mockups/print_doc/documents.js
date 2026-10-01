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
    static title = 'PENERIMAAN BARANG';
    static subtitle = 'SPB &middot; Goods Receipt';
    static numberLabel = 'No SPB';
    static refs = [
        {key: 'receipt_type', label: 'Jenis'},
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
