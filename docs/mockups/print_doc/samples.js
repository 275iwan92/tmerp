// Data contoh: lampiran kertas (docs/attachments) + database dummy ddt*.
// `more` = baris tambahan untuk memperagakan A4 portrait (lebih dari 5 baris); `alt` = data lain untuk kasus A4.
const PRINTED = {printedAt: '01-Okt-2026 16:12'};
const SAMPLES = {
    SuratJalan: {
        data: {...PRINTED, number: '10262874', date: '2026-10-01', status: 'DRAFT', co_no: '2602282', createdBy: 'Yuna',
            party: {name: 'PAK A. YANI', lines: ['PERUMAHAN CITRA KAYANE RESIDENCE', 'MALANG']},
            rows: [{name: 'YAN OB KINGZANA KRIPIK PISANG (DS000)', qty: 508, unit: 'PCS', weight_kg: 12.7, bundles: 6, remark: 'isi 100 / ikat'}]},
        more: [
            {name: 'SJJ IB ROSSI FS GTZ-5S MF (12V5AH) DS001708', qty: 3000, unit: 'PCS', weight_kg: 54, bundles: 30, remark: 'isi 100 / ikat'},
            {name: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', qty: 1000, unit: 'PCS', weight_kg: 18, bundles: 10, remark: 'isi 100 / ikat'},
            {name: 'SJS IB BDM YTUBE 2.50/2.75-14 (DS003437)', qty: 30000, unit: 'PCS', weight_kg: 96, remark: '12 dus'},
            {name: 'SJS IB BDM YTUBE 2.50/2.75-17 (DS003438)', qty: 30000, unit: 'PCS', weight_kg: 99, remark: '12 dus'},
            {name: 'JD BOX DONAT UK 20 X 20 X 6 CM (DS000)', qty: 3000, unit: 'PCS', weight_kg: 81, bundles: 60, remark: 'isi 50 / ikat'},
            {name: 'IB COCO TOKYO FLAT C-25 1KG (DS001477)', qty: 4000, unit: 'PCS', weight_kg: 72.5, bundles: 40, remark: 'isi 100 / ikat'},
            {name: 'OB COCO TOKYO FLAT C-25 1KG', qty: 400, unit: 'PCS', weight_kg: 38.4, remark: '4 pallet'}],
        dev: {
            title: 'Perubahan terhadap format kertas sekarang (docs/attachments/surat jalan.jpg)',
            marks: [[1, '.co', -5, -2], [2, '.kopr', 14, -3], [3, '[data-k=co_no]', -5, 0], [4, '[data-k=vehicle]', -5, 0], [5, '[data-k=date]', 58, 0],
                [6, '[data-k=contact]', -5, 0], [7, 'tbody tr:nth-child(5) td', -5.5, 1.5], [8, 'thead th:nth-child(3)', -4, .3], [9, '.tail', -5.5, 1], [10, '.foot', -5, -1.5], [11, 'thead th:nth-child(5)', 14, .3]],
            legend: [
                '<b>Kop perusahaan</b>: logo, nama, alamat. Dokumen PPN: kop berganti ke CV. Langgeng Jaya Makmur + NPWP.',
                '<b>QR code</b>: scan membuka dokumen online. Aturan: setiap cetakan membawa QR.',
                '<b>Ref. CO</b>: nomor Customer Order asal barang.',
                '<b>Kendaraan / Pengemudi / Diberangkatkan</b> tetap ada. Belum ada kolomnya di database: diisi tangan sampai input-nya dibuat di form Delivery Order.',
                '<b>Tanggal + status</b> selalu di kanan baris pertama, format dd-Mmm-yyyy. Nomor dokumen selalu di kiri baris pertama.',
                '<b>Telp / UP</b> penerima, untuk pengemudi.',
                '<b>A5 landscape = 5 baris</b> (kertas lama 10 baris). Lebih dari 5 baris otomatis A4 portrait, 22 baris.',
                '<b>Jumlah dan Satuan</b>: kolom tetap di posisi yang sama di semua dokumen; angka format Indonesia (508,00).',
                '<b>Tanpa total</b>: Surat Jalan tidak punya nilai uang, jadi tidak ada kotak total. Ruang ini untuk catatan.',
                '<b>Kaki dokumen</b>: "Dicetak pada tanggal jam oleh nama orangnya" (bukan login, tanpa nama aplikasi, nomor dokumen, atau ukuran kertas); kanan: nomor lembar.',
                '<b>Berat (kg) dan Ikat</b>: kolom baru. Ikat hanya terisi bila barang dikemas per ikat. Sumber datanya belum ada di database (angka contoh).',
            ],
        },
    },
    CustomerOrder: {
        data: {...PRINTED, number: '2602410', date: '2026-10-01', status: 'DRAFT', customer: 'PT. SELATAN JADI JAYA', customer_po_no: 'B2L-260930-0002', ppn: 0, createdBy: 'Ehaningtias',
            party: {name: 'PT. SELATAN JADI JAYA', lines: ['JL. RAYA PANJUNAN 8 SUKODONO', 'SIDOARJO']},
            notes: [['Catatan', 'PAKAI SNI, JAPAN TECHNOLOGY BUATAN INDONESIA DI PRODUKSI OLEH PT SJJ SIDOARJO INDONESIA']],
            rows: [{name: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', qty: 20000, unit: 'PCS', cfg: '4/0', type: 'RTP', due_date: '2026-10-09', price: 1800, subtotal: 36000000}]},
        more: [
            {name: 'SJJ IB ROSSI FS GTZ-5S MF (12V5AH) DS001708', qty: 3000, unit: 'PCS', cfg: '4/0', type: 'RTP', due_date: '2026-10-09', price: 1800, subtotal: 5400000},
            {name: 'IB COCO TOKYO FLAT C-25 1KG (DS001477)', qty: 4000, unit: 'PCS', cfg: '4/0', type: 'RTP', due_date: '2026-10-16', price: 1800, subtotal: 7200000},
            {name: 'OB COCO TOKYO FLAT C-25 1KG', qty: 400, unit: 'PCS', cfg: '2/0', type: 'RTP', due_date: '2026-10-16', price: 4950, subtotal: 1980000},
            {name: 'SJS IB BDM YTUBE 2.50/2.75-14 (DS003437)', qty: 30000, unit: 'PCS', cfg: '2/0', type: 'RTP', due_date: '2026-10-20', price: 420, subtotal: 12600000},
            {name: 'SJS IB BDM YTUBE 2.50/2.75-17 (DS003438)', qty: 30000, unit: 'PCS', cfg: '2/0', type: 'RTP', due_date: '2026-10-20', price: 435, subtotal: 13050000},
            {name: 'JD BOX DONAT UK 20 X 20 X 6 CM (DS000)', qty: 3000, unit: 'PCS', cfg: '1/0', type: 'RTP', due_date: '2026-10-22', price: 1150, subtotal: 3450000}],
        dev: {
            title: 'Perubahan terhadap format kertas sekarang (docs/attachments/customer order 1.jpg)',
            marks: [[1, '.co', -5, -2], [2, '[data-k=number]', -5, 0], [3, '[data-k=date]', 58, 0], [4, '[data-k=quotation_no]', -5, 0], [5, '.tail', -5.5, 4.5],
                [6, 'thead th:nth-child(3)', -4, .3], [7, '.tot', -6, 0], [8, '.sign', -5, 0]],
            legend: [
                '<b>Kop + QR</b> sama dengan semua dokumen. Judul di tengah; "Sales Order" jadi anak judul.',
                '<b>Nomor CO</b> pindah dari baris judul ke kiri baris pertama, posisi yang sama dengan nomor di semua dokumen.',
                '<b>Tanggal + status</b> di kanan baris pertama, format dd-Mmm-yyyy (kertas: 10/01/2026). Tulisan "DIKIRIM" di sebelah status belum dimasukkan.',
                '<b>Ref. Quotation dan Syarat Bayar</b> ditambahkan (kosong bila order langsung). "Pembayaran ke Atas Nama" tetap ada.',
                '<b>Catatan</b> pindah ke bawah tabel (lebar, tidak memakan blok atas), bersama <b>Terbilang</b> (baru).',
                '<b>Jumlah dan Satuan</b> dipisah jadi dua kolom; nama produk panjang turun jadi 2 baris; due date dd-Mmm-yyyy; "JUMLAH x HARGA" jadi SUBTOTAL.',
                '<b>Total</b>: persen PPN ditulis; "Grand Total" jadi TOTAL (IDR).',
                '<b>Tanda tangan</b> dari kiri ke kanan: Pemesan (customer, baru), Menyetujui, Mengetahui, Pembuat. Nama Pembuat tercetak otomatis.',
            ],
        },
    },
    PurchaseOrder: {
        data: {...PRINTED, number: 'PO26000001976', date: '2026-10-01', status: 'CONFIRMED', pr_no: 'PR2600000166', ppn: 11, createdBy: 'Eka', knownBy: 'Wiwik', approvedBy: 'Hendra',
            ship_to: 'Gudang Telasih Makmur',
            party: {name: 'PT MAKMUR JAYA USAHA', lines: []},
            notes: [['Catatan', 'Atas kerjasamanya kami ucapkan terima kasih.']],
            rows: [{name: 'DC 310 IKA DA GR 79 x 109 CM', qty: 23, unit: 'RIM', price: 1567737.28, subtotal: 36057957.44}]},
        more: [],
        dev: {
            title: 'Perubahan terhadap format kertas sekarang (docs/attachments/purchase order.jpg, A4 portrait)',
            marks: [[1, '.co', -5, -2], [2, '[data-k=number]', -5, 0], [3, '[data-k=handover]', -5, 0], [4, '.nb', -5.5, 0], [5, 'thead th:nth-child(3)', -4, .3], [6, '.tail', -5.5, 1], [7, '.tot', -6, 0], [8, '.sign', -5, 0]],
            legend: [
                '<b>Kop</b> tetap CV. Langgeng Jaya Makmur + alamat (dokumen PPN), diperkecil ke kiri; judul PURCHASE ORDER di tengah; QR di kanan.',
                '<b>No PO, Ref. PR, tanggal, supplier</b> masuk blok field posisi tetap ("Malang," di depan tanggal dihilangkan).',
                '<b>"Yang akan ... oleh"</b> dan <b>"Estimasi pengiriman/pengambilan pada"</b> jadi field Dikirim / Diambil dan Estimasi Tanggal; diisi tangan bila kosong.',
                '<b>Kalimat pembuka</b> "Dengan hormat, ..." tetap, di baris NB.',
                '<b>Tabel berkepala</b>: Jumlah, Satuan, Harga, Subtotal di kolom tetap; "IDR" tidak diulang tiap angka.',
                '<b>Terbilang</b> dihitung sistem sampai sen (kertas membulatkan ke rupiah).',
                '<b>Total</b>: Diskon hanya muncul bila ada; PPN ditulis persennya.',
                '<b>Tanda tangan</b> dari kiri ke kanan: Supplier, Menyetujui, Mengetahui, Pembuat. Nama ketiganya tercetak otomatis.',
            ],
        },
    },
    // Alamat di contoh ini adalah data contoh (di aplikasi: alamat kirim customer / alamat supplier dari master).
    JadwalKirim: {
        data: {...PRINTED, number: 'JK26100101', date: '2026-10-01', status: 'CONFIRMED', start_time: '09:30', finish_time: '18:00', createdBy: 'Johannes Iwan',
            party: {name: 'N 8035 AB', lines: ['Driver: TRIO & AGUNG']},
            rows: [
                {kind: 'AMBIL', time: '10:15', place: 'GUDANG LBI', city: 'MALANG', address: 'JL. RAYA KARANGLO 88, SINGOSARI', item: 'BAHAN R8', qty: 2, unit: 'PALLET'},
                {kind: 'KIRIM', time: '12:30', place: 'PT SELATAN JADI JAYA', city: 'SIDOARJO', address: 'JL. RAYA PANJUNAN 8 SUKODONO', item: 'SJJ IB ROSSI FS GTZ-5S MF (12V5AH) DS001708', co_no: '2602377', qty: 3000, unit: 'PCS', note: 'max 3100'},
                {kind: 'KIRIM', time: '12:30', place: 'PT SELATAN JADI JAYA', city: 'SIDOARJO', address: 'JL. RAYA PANJUNAN 8 SUKODONO', item: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', co_no: '2602381', qty: 1000, unit: 'PCS', note: 'max 1100'},
                {kind: 'AMBIL', time: '13:00', place: 'PT SELATAN JADI JAYA', city: 'SIDOARJO', address: 'JL. RAYA PANJUNAN 8 SUKODONO', item: 'TAGIHAN & GIRO'},
                {kind: 'KIRIM', time: '14:00', place: 'GOLDEN (SUBKON PAK TASMAN)', city: 'PASURUAN', address: 'JL. RAYA PANDAAN KM 3', item: 'BARANG SUBKON', qty: 7840, unit: 'PCS', note: 'semua'}]},
        alt: {...PRINTED, number: 'JK26100102', date: '2026-10-01', status: 'CONFIRMED', start_time: '07:45', finish_time: '17:00', createdBy: 'Johannes Iwan',
            party: {name: 'N 8381 BK', lines: ['Driver: PUJI & DWI']},
            rows: [
                {kind: 'AMBIL', time: '08:00', place: 'GUDANG LBI (RIT 1)', city: 'MALANG', address: 'JL. RAYA KARANGLO 88, SINGOSARI', item: 'BAHAN R8', qty: 6, unit: 'PALLET'},
                {kind: 'KIRIM', time: '09:15', place: 'TELASIH MAKMUR', city: 'MALANG', address: 'PONDOK BLIMBING INDAH BLOK A5 NO. 5', item: 'BAHAN R8 DARI GUDANG LBI (BONGKAR)', qty: 6, unit: 'PALLET'},
                {kind: 'AMBIL', time: '10:00', place: 'GUDANG LBI (RIT 2)', city: 'MALANG', address: 'JL. RAYA KARANGLO 88, SINGOSARI', item: 'BAHAN R8', qty: 2, unit: 'PALLET'},
                {kind: 'KIRIM', time: '10:45', place: 'TELASIH MAKMUR', city: 'MALANG', address: 'PONDOK BLIMBING INDAH BLOK A5 NO. 5', item: 'BAHAN R8 (BONGKAR) + MUAT KIRIMAN KOTA', qty: 2, unit: 'PALLET'},
                {kind: 'KIRIM', time: '13:00', place: 'PT. SARANA JAYA SERBAGUNA', city: 'MALANG', address: 'JL. TENAGA BARU 12, BLIMBING', item: 'SJS IB BDM YTUBE 2.50/2.75-14 (DS003437)', co_no: '2602262', qty: 30000, unit: 'PCS', note: 'max 31000'},
                {kind: 'KIRIM', time: '13:00', place: 'PT. SARANA JAYA SERBAGUNA', city: 'MALANG', address: 'JL. TENAGA BARU 12, BLIMBING', item: 'SJS IB BDM YTUBE 2.50/2.75-17 (DS003438)', co_no: '2602266', qty: 30000, unit: 'PCS', note: 'max 31000'},
                {kind: 'KIRIM', time: '15:30', place: '93 DONUT', city: 'MALANG', address: 'PERUMAHAN CITRA KAYANE RESIDENCE', item: 'JD BOX DONAT UK 20 X 20 X 6 CM (DS000)', co_no: '2602282', qty: 3000, unit: 'PCS', note: 'semua'}]},
        dev: {
            title: 'Perubahan terhadap format sekarang (docs/attachments/expedisi.jpg, tabel Excel)',
            marks: [[1, '[data-k=number]', -5, 0], [2, '[data-k=party]', -5, 0], [3, 'thead th:nth-child(2)', -1, -2.5], [4, 'thead th:nth-child(5)', 0, -2.5], [5, 'thead th:nth-child(8)', 0, -2.5], [6, '.sign', -5, 0]],
            legend: [
                '<b>Satu lembar per tugas armada</b> (di Excel: banyak tugas dalam satu halaman). Diberi nomor tugas supaya bisa dilacak; format nomor menunggu keputusan.',
                '<b>No Pol dan Driver</b> masuk blok kanan; Jam Mulai / Jam Selesai di kiri.',
                '<b>Tujuan + kota</b> di baris pertama, <b>alamat</b> di baris kedua. Barang dan No CO pindah ke kolom BARANG / CO.',
                '<b>Jenis</b> AMBIL atau KIRIM dan <b>Jam</b> rencana tiba, satu baris per perhentian.',
                '<b>IN / OUT</b> tetap kolom kosong untuk diisi tangan (jam sebenarnya).',
                '<b>Tanda tangan</b> dari kiri ke kanan: Pengemudi, Menyetujui, Mengetahui, Pembuat.',
            ],
        },
    },
    Inquiry: {
        data: {...PRINTED, number: 'IQ26090067', date: '2026-09-04', status: 'CONFIRMED', inquiry_type: 'NEW', source: 'EMAIL', sales: 'Johannes Iwan', createdBy: 'Johannes Iwan',
            deadline_quotation: '2026-09-10', contact: 'PAK RUDI · +62 812-3567-289', product_type: 'Card Box – Reverse Tuck End',
            party: {name: 'PAK RUDI LAWANG', code: '1130000001', lines: ['Jl. Argopuro no. 24', 'Lawang']},
            notes: [['Finishing', 'Laminasi Doff · Pond · Lem samping'], ['Qty alternatif', '20.000 · 50.000 PCS'], ['Pajak / Bahan', 'PPN 11% · bahan sendiri'], ['Catatan Sales', 'Minta sampel dummy putih. Artwork dari customer.']],
            rows: [{name: 'Box Kue Kering 250 gr · Body', qty: 10000, unit: 'PCS', size: '250 × 180 × 80', material: 'Ivory 300 gsm', color: '4/0'}]},
        more: [],
    },
    PurchaseRequisition: {
        // Produksi: tiap baris terikat ke baris CO (pr_line.i_co_line_id) -> NO CO + nama product (baris kedua).
        data: {...PRINTED, number: 'PR2600009', date: '2026-10-01', status: 'CONFIRMED', category: 'Produksi', source: 'MANUAL', purpose: 'Bahan baku order Oktober', createdBy: 'Johannes Iwan',
            approval: 'Level 2 dari 2', ppn: 0,
            party: {name: 'Production', lines: ['Diminta oleh: Johannes Iwan']},
            rows: [{name: 'IVORY 300 79 X 109', sub: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', qty: 40, unit: 'RIM', co_no: '2602410', need_date: '2026-10-15', price: 1185000, subtotal: 47400000},
                {name: 'HVS 58 GR RAINBOW 79 X 109 CM', sub: 'SJS IB BDM YTUBE 2.50/2.75-14 (DS003437)', qty: 200, unit: 'RIM', co_no: '2602262', need_date: '2026-10-15', price: 455000, subtotal: 91000000},
                {name: 'DUPLEX NONE COATED 260 GR 76 X 108 CM', sub: 'JD BOX DONAT UK 20 X 20 X 6 CM (DS000)', qty: 25, unit: 'RIM', co_no: '2602282', need_date: '2026-10-15', price: 905000, subtotal: 22625000}]},
        more: [],
        cases: {
            nonprod: {...PRINTED, number: 'PR2600010', date: '2026-10-01', status: 'CONFIRMED', category: 'Non Produksi', source: 'MANUAL', purpose: 'Kebutuhan kantor Oktober', createdBy: 'Johannes Iwan',
                approval: 'Level 1 dari 2', ppn: 0,
                party: {name: 'General Affairs', lines: ['Diminta oleh: Johannes Iwan']},
                rows: [{name: 'Kertas HVS A4 80 gr', qty: 20, unit: 'RIM', need_date: '2026-10-08', price: 52000, subtotal: 1040000},
                    {name: 'Toner printer HP 107A', qty: 4, unit: 'PCS', need_date: '2026-10-08', price: 950000, subtotal: 3800000},
                    {name: 'Laptop 14 inci Core i5 / 16 GB / SSD 512 GB', sub: 'Untuk staf Accounting, pengganti unit rusak', qty: 1, unit: 'UNIT', need_date: '2026-10-20', price: 11500000, subtotal: 11500000},
                    {name: 'Mouse wireless', qty: 5, unit: 'PCS', need_date: '2026-10-08', price: 85000, subtotal: 425000}]},
        },
    },
};
// CPDEL: contoh CO 2602410, produk pertama dipecah 3 kiriman, produk kedua kirim penuh.
SAMPLES.CustomerPlanDelivery = {
    data: {...PRINTED, number: '2602410', date: '2026-10-01', status: 'CONFIRMED', customer: 'PT. SELATAN JADI JAYA', customer_po_no: 'B2L-260930-0002', co_date: '2026-09-30', createdBy: 'Ehaningtias',
        party: {name: 'PT. SELATAN JADI JAYA', lines: ['JL. RAYA PANJUNAN 8 SUKODONO', 'SIDOARJO']},
        rows: [
            {item: 1, name: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', qty: 8000, unit: 'PCS', ship_date: '2026-10-09', ship_time: '10:00', due_date: '2026-10-09', state: 'Posted'},
            {item: 1, name: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', qty: 8000, unit: 'PCS', ship_date: '2026-10-14', ship_time: '10:00', due_date: '2026-10-09', state: 'Planned'},
            {item: 1, name: 'SJJ IB ND NIPPONDENSO FS GTZ-5S MF (12V5AH) DS001639', qty: 4000, unit: 'PCS', ship_date: '2026-10-19', ship_time: '13:00', due_date: '2026-10-09', state: 'Planned'},
            {item: 2, name: 'SJJ IB ROSSI FS GTZ-5S MF (12V5AH) DS001708', qty: 3000, unit: 'PCS', ship_date: '2026-10-09', ship_time: '10:00', due_date: '2026-10-09', state: 'Posted'}]},
    more: [
        {item: 3, name: 'IB COCO TOKYO FLAT C-25 1KG (DS001477)', qty: 2000, unit: 'PCS', ship_date: '2026-10-16', ship_time: '09:00', due_date: '2026-10-16', state: 'Planned'},
        {item: 3, name: 'IB COCO TOKYO FLAT C-25 1KG (DS001477)', qty: 2000, unit: 'PCS', ship_date: '2026-10-23', ship_time: '09:00', due_date: '2026-10-16', state: 'Planned'},
        {item: 4, name: 'OB COCO TOKYO FLAT C-25 1KG', qty: 400, unit: 'PCS', ship_date: '2026-10-16', ship_time: '09:00', due_date: '2026-10-16', state: 'Planned'},
        {item: 5, name: 'JD BOX DONAT UK 20 X 20 X 6 CM (DS000)', qty: 3000, unit: 'PCS', ship_date: '2026-10-22', ship_time: '14:00', due_date: '2026-10-22', state: 'Planned'}],
};
SAMPLES.CustomerOrderNoPrice = {data: SAMPLES.CustomerOrder.data, more: SAMPLES.CustomerOrder.more};
