# CUSTOMER INVOICE + ACCOUNTS RECEIVABLE — RENCANA MODUL

> Status: **arah dan lima keputusan A–E DISETUJUI user 2026-10-02** (bagian 8). Modul belum dibangun; langkah
> berikut = perjalanan user + mockup (bagian 10) untuk disetujui.
> Ketentuan keuangan dan akuntansi (jurnal, nomor, pajak, pengendalian): **`customerinvoice_accounting.md`**.
> Analisa (penomoran, detail baris, software pasar, aturan Indonesia): bagian 9.
> Acuan lapangan: `docs/attachments/` — `print out tagihan customer invoice.jpeg`,
> `screen untuk input tagihan customer invoice.jpeg`, `customer invoice uang muka.jpeg`,
> `screen untuk input uang muka.jpeg`.
> Acuan desain yang sudah LOCKED dan tetap dipakai: `downpayment_tier1-4.md` (jurnal uang muka).

---

## 1. Keputusan inti

**Customer Invoice dan AR dijadikan SATU modul, satu tabel dokumen.** Invoice itu sendiri adalah dokumen
piutang; AR = invoice + penerimaan + alokasi + umur piutang. Sistem lama memisahkannya (invoice di Sales,
piutang tidak ada) dan akibatnya terlihat di datanya sendiri (bagian 2).

Tidak dibuat tabel baru yang menyaingi yang ada. `tm2acc.fin_invoice` + `ArApService` + `fin_payment_alloc` +
`fin_dp_allocation` + `v1_fin_ar_aging` sudah ada dan sudah memposting jurnal. Yang kurang hanya tiga hal:

1. **Baris barang** — `fin_invoice` sekarang hanya punya satu angka subtotal yang diketik tangan, tanpa
   hubungan ke Surat Jalan.
2. **Layar v2** — grid v2 sudah ada, tetapi Open / New masih melempar ke layar v1.
3. **Potongan uang muka yang benar** — dipotong sebagai uang muka, bukan diketik di kolom Diskon.

---

## 2. Apa kata data lama (`tm2old.sdi_cinv*`, dibaca 2026-10-02)

| Fakta | Angka | Artinya untuk desain |
|---|---|---|
| Invoice 2026 (s.d. September) | 2.531 invoice, Rp 26,25 miliar, 2.067 CO | ± 14 invoice per hari kerja: input harus cepat, hampir tanpa ketik |
| PPN vs Non PPN | 818 PPN (32%) · 1.713 Non PPN (68%) | dua kop, dua rekening bank, satu layar |
| Potongan DP | 283 invoice memotong DP lewat kolom **Diskon** dengan teks bebas "DP TGL 15/09/2026" | DP tidak terhubung ke dokumen DP mana pun |
| Diskon persen sungguhan | 0 invoice | kolom Diskon praktis hanya dipakai untuk DP |
| Tagihan DP (layar "Customer Invoicing") | 274 dokumen sejak Sep 2023, hanya ± 40 di 2026 | sebagian besar DP diterima **tanpa** dokumen DP |
| CO ber-DP sejak 2025 (109 CO) | Non PPN (82 CO): 69 dipotong, 4 tidak dipotong, 9 belum ada invoice akhir. PPN (27 CO): **tidak satu pun** invoice akhirnya menampilkan potongan DP | order PPN ditagih penuh di invoice akhir, DP tidak terlihat di dokumen (lihat bagian 9.3); 4 CO Non PPN perlu dicek Finance |
| Pembayaran customer | `sdi_cinv_rcp` 0 baris, `remain_of_bill` kosong | sistem lama **tidak tahu siapa masih berhutang berapa** |
| Invoice per CO | 88% satu invoice, sisanya 2 s.d. 10+ (kirim bertahap) | 1 invoice = 1 CO, banyak Surat Jalan |
| Qty tagih ≠ qty diterima | 14% baris | sebagian besar akibat cara kerja layar lama (menjumlah semua penerimaan CO, lalu dikurangi tangan pada invoice kedua) — lihat bagian 9.2 |
| Satu baris menggabung beberapa Surat Jalan | 9% baris | baris cetak = per product, daftar No. SJ |
| Syarat bayar | 30 hari 65% · Dimuka 12% · Kontan 9% · 45 hari 7% | jatuh tempo dihitung dari master syarat bayar |

Catatan: potongan DP dicocokkan lewat teks "DP" di keterangan diskon.

---

## 3. Alur dokumen

```
Customer Order (Confirmed)
   |
   |-- [opsional] DOWN PAYMENT INVOICE ----> Customer Receipt ----> uang muka CO (akun 213200)
   |        (% atau nominal dari nilai CO)                                   |
   v                                                                         |
Delivery Order -> Delivery Notes (Surat Jalan) -> Proof of Delivery          |
   |                                                                         |
   v                                                                         v
CUSTOMER INVOICE (baris = baris Surat Jalan yang belum ditagih)  <-- potong uang muka otomatis
   |  Confirm = nomor terbit + jurnal + qty_invoiced Surat Jalan
   v
Customer Receipt (satu penerimaan boleh melunasi banyak invoice, boleh sebagian)
   |
   v
Closed (lunas)          laporan: AR Aging, Statement of Account, Unbilled Delivery, DP Outstanding
```

Satu form, dua jenis dokumen (field **Invoice Type**):

| Jenis | Sumber | Isi | Lampiran acuan |
|---|---|---|---|
| **Down Payment** (`CUSTOMER_DP`) | Customer Order | nilai CO, sudah dibayar, **Bill Amount** (% / nominal), sisa | layar "Update Customer Invoicing", cetak "Total Tagihan 7.500.000" |
| **Invoice** (`CUSTOMER_INVOICE`) | Surat Jalan | baris product, qty kirim, qty tagih, harga, No. SJ, potongan DP | layar "Customer Invoice", cetak "Grand Total 1.657.600" |

Jenis ketiga, **Credit Note** (retur / selisih harga sesudah invoice diposting), masuk tahap 3.

---

## 4. Aturan bisnis

> Diperbarui oleh analisa 9.7: dasar tagih = qty diterima (butir 2), nomor terbit saat Confirm dari seri
> (butir 6), dan tagihan DP tanpa jurnal sampai dibayar (tabel jurnal; menunggu keputusan C).

1. **Satu invoice = 1 customer, 1 CO, 1 status PPN, 1 mata uang.** Boleh memuat banyak Surat Jalan.
2. **Baris invoice hanya dari baris Surat Jalan** yang sudah dikirim dan masih punya sisa
   (`do_line.qty_do - qty_invoiced > 0`). Qty tagih boleh lebih kecil dari sisa; sisanya tetap terbuka untuk
   invoice berikutnya. Tagih dobel jadi mustahil secara struktur (menggantikan kontrol duplikat
   "customer + nilai + tanggal" yang sekarang).
3. **Harga tidak diketik.** Diambil dari baris Surat Jalan (snapshot harga CO). Perubahan harga lewat
   persetujuan yang sudah ada di Delivery Order (`price_change_approval_status`).
4. **Uang muka dipotong otomatis.** Saat draft dibuat, sistem mengusulkan DP CO yang sama yang sudah dibayar
   dan belum terpakai (paling lama dulu, paling banyak sebesar nilai invoice). Petugas boleh mengubah
   nilainya. Di layar dan cetakan tampil sebagai baris sendiri "Down Payment 15-Sep-2026 (no. DP)".
5. **DP tanpa dokumen DP tetap tercatat.** Kasir yang menerima uang muka langsung membuat dokumen DP sekaligus
   penerimaannya dalam satu langkah (cetakannya = Tanda Terima Uang Muka). Jadi hanya ada satu cara DP masuk ke
   sistem, dan semua DP muncul sebagai usulan potongan.
6. **Confirm** = nomor invoice terbit + jurnal diposting + `do_line.qty_invoiced` naik + alokasi DP diposting,
   dalam **satu transaksi** (SQL lintas-DB lewat `DbName::table()` pada koneksi `tm2acc`; satu server MySQL,
   jadi atomik).
7. **Sesudah Confirm tidak bisa diubah.** Cancel wajib alasan, hanya bila periode masih terbuka dan belum ada
   pembayaran: jurnal dibalik, qty Surat Jalan dan DP dilepas. Di luar itu koreksi lewat Credit Note.
8. **Jatuh tempo** = tanggal invoice + hari dari `fin_payment_term`; Dimuka / Kontan / Di Tempat = hari itu juga.
9. **PPN**: status dari `co_order.is_ppn`; tarif disimpan di invoice saat Confirm (data lama punya 10% dan 11%).
   Kop Non PPN = Telasih Makmur, kop PPN = CV. Langgeng Jaya Makmur + NPWP (aturan kop yang sudah ada).
10. **Status dokumen** (istilah baku form v2): Draft → Confirmed → Closed (lunas) / Cancelled. Status tagih
    turunan: Open, Partially paid, Due this week, Overdue, Paid.

### Jurnal

| Peristiwa | Debit | Kredit |
|---|---|---|
| Tagihan DP | 1121 Piutang | 213200 Uang Muka Penjualan (+ 212100 PPN Keluaran bila PPN) |
| Invoice | 1121 Piutang (nilai penuh) | 411 Penjualan + 212100 PPN Keluaran |
| Potong DP | 213200 Uang Muka (+ 212100 reklas PPN DP) | 1121 Piutang |
| Penerimaan | 111x Kas / Bank (+ PPh 23 dipotong customer, + selisih / biaya bank) | 1121 Piutang |
| Kelebihan bayar | 111x Kas / Bank | 213200 Uang Muka (bisa dipakai invoice berikut) |

Contoh uji dari lampiran: 508 pcs × 7.200 = 3.657.600 − DP 2.000.000 = **1.657.600**; DP 50% dari
15.000.000 = **7.500.000**. Dua kasus ini jadi test regresi.

Umur piutang memisahkan **tagihan DP belum dibayar** dari **piutang dagang**, supaya piutang tidak tampak
lebih besar dari penyerahan barang yang sebenarnya.

---

## 5. Perubahan database (`tm2acc`; dikerjakan di `ddtacc` dulu)

**`fin_invoice` — tambah kolom** (tabel dan isinya tetap):
`is_ppn`, `tax_rate`, `payment_term_code`, `customer_po_no`, `bill_to_name`, `bill_to_address`,
`currency_code`, `exchange_rate`, `company_bank_id`, `discount_amount`, `discount_note`,
`charge_amount`, `charge_note` (FOB / ongkos kirim dari `co_order_external_cost`), `dp_applied_amount`,
`order_amount` + `dp_percent` (khusus DP), `notes`, `confirmed_by/at`, `cancel_reason`, `cancelled_by/at`.
`invoice_type` + `CUSTOMER_CREDIT_NOTE` (tahap 3).

**`fin_invoice_line` — tabel baru:**

| Kolom | Isi |
|---|---|
| `id_invoice_line` BIGINT PK, `invoice_id` FK, `line_no` | — |
| `line_kind` | PRODUCT / SERVICE / CHARGE |
| `do_line_id`, `delivery_id`, `delivery_no` | asal Surat Jalan (FK logis ke `tm2sdi`, nomor disalin) |
| `co_order_item_id`, `product_id`, `description` | product, nama disalin |
| `color_config`, `order_type` | kolom Cfg dan Tipe di cetakan |
| `qty_delivered`, `qty_invoiced`, `qty_unit` | qty kirim (salinan) dan qty tagih |
| `unit_price`, `discount_pct`, `line_total` | — |
| `revenue_account_id` | akun pendapatan per baris |

**`fin_company_bank` — master baru** (rekening perusahaan yang dicetak di "Pelunasan harap ditransfer ke"):
bank, nomor rekening, atas nama, akun GL, dipakai untuk PPN / Non PPN / keduanya, default. Punya layar input
sendiri. Data lama: 5 rekening di `tm2old.mst_bank_account` — **data nyata, diisi hanya atas izin user**.

**`fin_payment_alloc` — tambah** `pph_amount`, `writeoff_amount` (PPh 23 dipotong customer, selisih kecil).

Sudah ada dan dipakai apa adanya: `fin_dp_allocation`, `v1_fin_dp_outstanding`, `v1_fin_ar_aging`,
`do_line.qty_invoiced` (kolomnya ada, belum pernah diisi oleh kode mana pun).

Dua sumber tarif PPN perlu disatukan: `ArApService` membaca `fin_tax_scheme` (PPN_OUT), sedangkan Customer
Order membaca konstanta `PPN`. Invoice akan memakai sumber yang sama dengan CO supaya nilai CO = nilai invoice.

---

## 6. Layar (v2, objek DevLib, grid dulu baru form)

| Layar | Isi |
|---|---|
| **Customer Invoice** (grid) | Grid Transactional: kotak dokumen (nomor + customer), tabel anak = baris product. Saringan: All · Draft · Open · Overdue · Paid · Down Payment. Menggantikan grid Order to Cash dan Down Payment. |
| **Customer Invoice** (form) | Tombol di atas (New, Save, Undo, Delete, Confirm, Cancel, Back, Print ▾, Email ▾). Kepala 3 kelompok seperti layar lama: Order · Billing · Notes. Tab: Items · Amount · Payments (baca saja) · History. |
| **New** (pre-entry) | Pilih jenis → customer → CO → centang Surat Jalan yang belum ditagih → draft terisi sendiri. |
| **To Invoice** (worklist) | Surat Jalan yang sudah dikirim dan belum ditagih; tombol Create Invoice per CO. Titik mulai kerja harian Finance. |
| **Customer Receipt** (grid + form) | Customer, rekening, tanggal, nilai diterima; centang invoice terbuka; alokasi; PPh / selisih. Confirm = bukti bank masuk / kas masuk. |
| **Laporan** | AR Aging · Statement of Account per customer · Unbilled Delivery · DP Outstanding · Invoice Register (dasar PPN Keluaran). |
| **Cetak** | Customer Invoice dan Down Payment Invoice (A5 landscape 1-5 baris / A4 portrait, objek `PrintDoc`): baris product + Cfg + Tipe + No. SJ, potongan DP, rekening transfer, terbilang. Tanda Terima Uang Muka. |
| **Customer Order** | Tab Billing (baca saja): DP, invoice, dibayar, sisa. |

Hak akses: modul FINACC. Sales melihat status tagihan CO-nya tanpa bisa mengubah.

---

## 7. Urutan kerja

**Tahap 0 — persetujuan.** User journey per layar (buka → lihat → klik → dapat) + mockup JPEG form dan cetakan.

**Tahap 1 — menagih.** Kolom dan tabel baru di `ddtacc`; service `InvoiceBuilder` (DP dari CO, invoice dari
Surat Jalan, usulan potongan DP); grid + form v2; Confirm / Cancel dengan jurnal; cetakan; master rekening
perusahaan; test regresi dua kasus lampiran + tie-out DP.

**Tahap 2 — piutang.** Customer Receipt v2 + alokasi; To Invoice; AR Aging, Statement of Account, Unbilled
Delivery; tab Billing di Customer Order.

**Tahap 3 — pengendalian.** Credit Note; batas kredit customer saat Confirm CO; pengingat jatuh tempo
(e-mail); ekspor e-Faktur; saldo awal piutang dan pindah ke `tm2acc`.

Layar v1 (`finance.receivables`, `finance.dp-invoice`) dibiarkan sampai v2 lolos QC, lalu diarsipkan.

---

## 8. Keputusan user

Enam pertanyaan awal, sesudah data lama, kode ScriptCase, software di pasar, dan aturan Indonesia diperiksa
(bagian 9):

| # | Pertanyaan awal | Status |
|---|---|---|
| 1 | Format nomor invoice | **Terjawab sebagian** — format, reset, dan cara terbit jelas (9.1). Sisa: satu seri atau dua seri (keputusan A). |
| 2 | Arti Qty Minim | **Terjawab** — harga borongan: nilai baris = harga, bukan qty × harga (9.2). |
| 3 | PPN pada uang muka | **Praktik sekarang diketahui**, berbeda dari aturan pajak (9.3, 9.6). Keputusan B. |
| 4 | Siapa membuat invoice | **Terjawab** — Finance: satu orang untuk invoice, satu orang untuk tagihan DP (9.4). |
| 5 | Rekening di cetakan | **Terjawab** — polanya jelas (9.3). Sisa: izin menyalin data nyata (keputusan D). |
| 6 | Saldo awal piutang | **Terjawab** — memang tidak ada datanya; diusulkan lembar kerja di aplikasi (9.7 butir 12). |

**Keputusan user 2026-10-02:**

| | Keputusan | Pelaksanaan |
|---|---|---|
| **A** | **Dua seri nomor**, satu per identitas: Non PPN `…/TM/…`, PPN `…/LJM/…` (kode bisa diubah di master seri). Format dan urut ulang bulanan tetap; nomor terbit otomatis saat Confirm. | tahap 1 |
| **B** | **PPN uang muka mengikuti aturan**: Faktur Pajak saat uang muka diterima, invoice akhir memuat PPN sisanya. Setelan cadangan `DP_VAT_ON_RECEIPT` untuk kembali ke cara lama. | tahap 1; konfirmasi konsultan pajak sebelum dipakai di tmerp.test |
| **C** | **Tagihan DP tanpa jurnal sampai uangnya diterima.** Menggantikan butir LOCKED di `downpayment_tier1-4.md` (sudah diberi catatan perubahan). User meminta urusan keuangan dan akuntansi didokumentasikan jelas → `customerinvoice_accounting.md`. | dokumen selesai; kode tahap 1 |
| **D** | **Lima rekening bank lama disalin** ke master rekening perusahaan; bawaan PPN = Maspion CV. Langgeng Jaya Makmur, Non PPN = BCA Puspita Dewi Widodo. | tahap 1 (saat master dibuat) |
| **E** | **Qty Minim di Customer Order v2 = harga borongan** (untuk saat ini). | selesai 2026-10-02: `CoOrderItem::recalcSubtotal`, `DoLine`, test `QtyMinimLumpSumTest` |

---

## 9. Analisa lanjutan (2026-10-02)

Sumber: `tm2old` (data s.d. 10-Sep-2026), kode `docs/legacy_scriptcase/tm/form_sdi_cinv*` dan
`tm3/form_sdi_cinv_prebill`, log `tm2old.sc_log`, dokumentasi vendor, dan peraturan (tautan di 9.5 dan 9.6).

### 9.1 Penomoran di sistem lama

| Hal | Temuan |
|---|---|
| Format | `urut/TM/bulan romawi/tahun`, urut kembali ke 1 tiap bulan. |
| Satu urutan untuk PPN dan Non PPN | Ya. September 2026: nomor 1 Non PPN, nomor 2 PPN, dan seterusnya bercampur. |
| Cara terbit | **Diketik tangan.** Layar mengusulkan `YY00001` (fungsi `my_seq`), lalu ditimpa. Sisa nomor usulan yang lupa ditimpa: `2200001`–`2200007`, `1900001`. |
| Salah ketik | ± 15 nomor sejak 2016: `46TM/I/2025`, `168/TM/VII//2025`, `33/T/IX/2026`, `74/M/II/2026`, `11/TDPV/2025`, spasi di depan. |
| Lompatan | Hampir tidak ada: 0–2 nomor hilang per bulan (Okt 2025 – Sep 2026). Agustus 2026 ada 7 nomor 345–351 yang terketik bulan VIII. |
| Bulan di nomor | Mengikuti bulan tanggal invoice; hanya 5 yang tidak cocok dalam 21 bulan (contoh lampiran: `275/TM/IX/2026` bertanggal 01-10-2026). |
| Urutan waktu | Tidak selalu urut: nomor dipakai melompat-lompat dalam satu hari. |
| Seri kedua `…/TDP/…` | Untuk order kanal penjualan "TDP" (Surat Jalan seri sendiri, 7 digit `26xxxxx`): 132 (2023), 256 (2024), 706 (2025), 178 (2026). **Berhenti Mei 2026**; sesudah itu order TDP memakai nomor TM. |
| Nomor dipakai ulang | 131 invoice dihapus di 2026 (5% dari 2.662 yang dibuat), termasuk yang sudah berstatus INVOICED; nomornya dipakai lagi. Jejaknya hanya di `sc_log`. |
| Tagihan DP | Otomatis `YY` + 6 digit, urut per tahun (`26000068`). "No Faktur / Reff" = nomor CO; DP kedua ditulis `2601259-2`. |

Aplikasi sekarang: tiap dokumen punya fungsi nomor sendiri di kode (`PO26xxxxx`, `SPB26xxxxx`, `INV-AR-00001`),
formatnya tidak bisa diubah dari layar, dan belum ada tabel seri nomor.

### 9.2 Detail baris invoice di sistem lama

- **Dasar tagih = qty DITERIMA customer**, bukan qty kirim. Layar menjumlah `qty_receive` semua Surat Jalan
  untuk baris CO itu; bila belum diisi muncul "Penerimaan Surat Jalan Belum Di input". Diterima ≠ dikirim pada
  95 dari 3.959 baris Surat Jalan 2026 (2,4%).
- Karena yang dijumlah **semua** penerimaan CO (bukan yang belum ditagih), invoice kedua harus dikurangi
  tangan. Akibatnya, untuk CO Jan 2025 – Jun 2026 (6.602 baris): 79 baris tertagih lebih dari yang diterima
  (± Rp 422 juta), 56 baris kurang (± Rp 194 juta), 6 baris belum ditagih (Rp 12 juta). Sebagian bisa sah
  (penerimaan tidak diperbarui); layak diperiksa Finance.
- Di aplikasi baru qty diterima **belum disimpan**: kolom "Received" di cetakan Proof of Delivery diisi
  tangan, `do_line` hanya punya `qty_do`.
- **Qty Minim = harga borongan.** Bila dicentang, nilai baris = harga (bukan qty × harga): 199 dari 199 baris
  CO sejak 2025, rata-rata Rp 798 ribu untuk ± 573 pcs.
- **Tipe** di cetakan = jenis order baris CO: NEW, RTP, RDP. **Cfg** = konfigurasi warna (4/0).
- **Tanggal invoice = tanggal Surat Jalan** pada 92% invoice 2026. Diketiknya rata-rata 3 hari kemudian; 31%
  lebih dari 3 hari kemudian.
- Tarif PPN diketik: 786 invoice 11%, satu terketik 10%. Kolom NPWP teks bebas: 436 invoice PPN berisi
  "NO NPWP".
- Jarang sekali dipakai: mata uang selain rupiah (1 invoice sejak 2023), FOB (1 di 2026), diskon persen (0),
  retur (8 dokumen, semua Jan–Apr 2025).
- 200 customer ditagih di 2026; 10 terbesar = 67% nilai, 20 terbesar = 83%. Nilai tagihan kotor 2026 termasuk
  PPN, sebelum potongan DP: Rp 27,99 miliar.

### 9.3 Uang muka di sistem lama

- DP standar **50%** dari total termasuk PPN; ada juga 25%, 75%, 100%.
- Rekening: order PPN → Maspion 124.200.1997 a.n. CV. Langgeng Jaya Makmur; Non PPN → BCA 331.030.0313 a.n.
  Puspita Dewi Widodo (umum), kadang BCA 011.808.0869 a.n. Carrij Soehendra.
- Pembayaran tidak pernah dicatat (`paid_amt` selalu 0; `sdi_cpym`, `sdi_cinv_rcp` kosong). 47 tagihan DP
  masih DRAFT.
- **Non PPN:** DP dipotong lewat kolom Diskon (743 invoice sejak 2025, Rp 3,48 miliar), dan `grand_total`
  tersimpan sesudah dipotong — laporan yang menjumlah `grand_total` jadi lebih kecil dari penjualan sebenarnya.
- **PPN:** invoice akhir terbit **penuh dengan PPN penuh**, DP tidak ditampilkan (26 dari 27 CO). Tidak ada
  Faktur Pajak saat DP diterima.

### 9.4 Siapa mengerjakan

Dari `sc_log` 2026: invoice dibuat satu orang (Wiwik, 2.662 dibuat, 2.995 diubah, 131 dihapus); tagihan DP oleh
satu orang lain (Mia, grup Accounting, 72 dokumen). Sales tidak membuat invoice. Cetak: 1.496 kali format
Langgeng Jaya Makmur, 1.682 kali format Telasih Makmur.

### 9.5 Software di pasar

| Produk | Nomor | Nomor terbit saat | Uang muka | Potong di invoice akhir | Invoice terposting diubah? |
|---|---|---|---|---|---|
| SAP S/4HANA | rentang nomor per jenis dokumen, hanya dari sistem | simpan | permintaan uang muka (tanpa jurnal, pajak dihitung) | diusulkan otomatis | tidak; batal / credit memo |
| SAP Business One | seri per jenis dokumen | simpan | DP Request (tanpa jurnal) atau DP Invoice | jendela "Down Payments to Draw", boleh sebagian | tidak |
| Odoo 17/18 | urutan per jurnal, format menentukan reset bulanan | **Confirm** (draft tanpa nomor) | invoice DP (% / nominal) dari order | dipotong otomatis | kembali ke draft bila belum dikunci |
| NetSuite | otomatis, awalan per jenis | simpan | Customer Deposit (kewajiban) | Deposit Application | ya, selama periode terbuka |
| D365 Business Central | No. Series, lompatan dilarang, seri sendiri untuk prepayment | **posting** | prepayment % di order, bisa menahan pengiriman | dipotong otomatis | tidak; Correct / Cancel |
| D365 Finance | number sequence (kode perusahaan, periode) | posting | prepayment invoice | invoice penuh, DP sebagai pelunasan | tidak |
| Accurate Online | token termasuk **bulan romawi**, reset harian / bulanan / tahunan | simpan | Uang Muka Penjualan bernomor sendiri | tab Uang Muka, boleh sebagian | ya, selama periode terbuka |
| Zahir | otomatis, bisa diketik | simpan | Uang Muka Penjualan | otomatis | ya |

Pola yang sama di produk kelas ERP: nomor dari seri yang diatur (bukan diketik), terbit saat posting, tidak
dipakai ulang, seri terpisah per jenis dokumen dan per badan usaha; uang muka dokumen sendiri yang masuk akun
kewajiban; invoice akhir mengusulkan DP terbuka dan boleh memotong sebagian; penagihan dari daftar "sudah
dikirim, belum ditagih" dengan qty tertagih disimpan per baris kirim; invoice terposting tidak bisa diubah.
Dua gaya tampilan: potongan DP tampil di invoice (SAP, Odoo, Business Central, Accurate) atau invoice penuh
dengan DP sebagai pelunasan (D365 Finance, NetSuite). Format Faktur Pajak Indonesia cocok dengan gaya pertama.

Tautan utama: [Odoo sequence](https://www.odoo.com/documentation/18.0/applications/finance/accounting/customer_invoices/sequence.html) ·
[Odoo down payment](https://www.odoo.com/documentation/18.0/applications/sales/sales/invoicing/down_payment.html) ·
[BC number series](https://learn.microsoft.com/en-us/dynamics365/business-central/ui-create-number-series) ·
[BC prepayments](https://learn.microsoft.com/en-us/dynamics365/business-central/walkthrough-setting-up-and-invoicing-sales-prepayments) ·
[D365 prepayment invoice](https://learn.microsoft.com/en-us/dynamics365/finance/accounts-receivable/customer-prepayment-invoice) ·
[NetSuite customer deposit](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_N1296349.html) ·
[Accurate penomoran](https://help.accurate.id/product/accurate-online/fitur-aol/pengaturan/penomoran/cara-membuat-penomoran-otomatis/) ·
[Zahir DP + PPN](https://zahiraccounting.com/id/blog/uang-muka-penjualan-telah-dikenakan-ppn/).
Keterangan SAP dan sebagian Accurate / Zahir berasal dari ringkasan pencarian, bukan halaman dokumentasi penuh.

### 9.6 Aturan akuntansi dan pajak Indonesia

| Topik | Aturan | Akibat untuk modul |
|---|---|---|
| Standar | SAK EP berlaku 1 Jan 2025 (pengganti SAK ETAP); SAK EMKM masih boleh untuk omzet sampai Rp 50 miliar. Yang dipakai perusahaan = keputusan akuntan / KAP. | saat pengakuan pendapatan dan cara cadangan piutang dibuat bisa diatur |
| Pendapatan | diakui saat barang diserahkan, bukan saat invoice dicetak (SAK EP 23.10); PPN bukan pendapatan | tanggal invoice mengikuti tanggal Surat Jalan; laporan "sudah dikirim, belum ditagih" di akhir periode |
| Uang muka | kewajiban sampai barang diserahkan (SAK EP contoh 23A.9); tagihan DP yang belum dibayar sebaiknya tidak menggelembungkan piutang | DP per CO di akun 213200; dasar keputusan C |
| Piutang | SAK EP: penurunan nilai berdasarkan bukti (telat bayar, gagal bayar), per customer besar dan per kelompok umur | umur piutang per jatuh tempo; penghapusan = transaksi tersendiri yang disetujui |
| Retur / diskon | mengurangi pendapatan; untuk PPN pembeli yang membuat nota retur | Credit Note terhubung ke invoice asal, mencatat nomor nota retur |
| Pembukuan | UU KUP Ps. 28: rupiah, bahasa Indonesia, disimpan 10 tahun, data elektronik sah. **Tidak ada undang-undang yang mewajibkan nomor invoice urut**; itu praktik audit. | invoice terposting tidak dihapus; nomor batal tetap terlihat; laporan lompatan nomor |
| Nomor Faktur Pajak | sejak Coretax (PER-11/PJ/2025) nomor seri diberikan DJP saat unggah; batas unggah tanggal 20 bulan berikutnya | nomor invoice perusahaan bebas; nomor Faktur Pajak disimpan per invoice sesudah unggah |
| PPN uang muka | terutang saat pembayaran diterima sebelum penyerahan; Faktur Pajak "Uang Muka", lalu "Pelunasan" yang merujuk nomor faktur DP dan hanya memuat sisa | dokumen DP menyimpan nomor Faktur Pajak-nya; invoice akhir PPN atas sisa |
| Tarif | 12% × DPP nilai lain (11/12 harga jual) = efektif 11% (PMK 131/2024), kode transaksi 04 | simpan harga jual, DPP nilai lain, PPN; tarif dari sistem, tidak diketik |
| PPh 23 | cetak dengan bahan dari percetakan = penjualan barang (tanpa PPh 23); maklon 2%. Sebagian customer tetap memotong. | penerimaan = uang + PPh 23 (bukti potong dicatat) |
| Bea Meterai | Rp 10.000 untuk dokumen penerimaan uang di atas Rp 5 juta; invoice yang hanya menagih tidak kena | tempat meterai di Tanda Terima Uang Muka / kwitansi; tanpa tulisan "LUNAS" di invoice |
| Mata uang | transaksi dalam negeri wajib rupiah (UU 7/2011) | invoice rupiah saja |
| Pengendalian | pemisahan tugas penagihan / kasir / piutang; cocok tiga dokumen CO – Surat Jalan – invoice; konfirmasi piutang; uji pisah batas | peran terpisah, laporan pisah batas, Statement of Account |

Tautan utama: [SAK EP (IAI)](https://web.iaiglobal.or.id/SAK-IAI/Tentang%20SAK%20Entitas%20Privat%20(EP)) ·
[Faktur Pajak uang muka dan pelunasan (DJP Coretaxpedia)](https://pajak.go.id/coretaxpedia/faktur-pajak-uang-muka-dan-pelunasan) ·
[PER-11/PJ/2025 uang muka (Ortax)](https://ortax.org/ini-ketentuan-pembuatan-faktur-pajak-uang-muka-sesuai-per-11-2025) ·
[kode 04 dan DPP 11/12 (DDTC)](https://news.ddtc.co.id/berita/nasional/1807950/ppn-dihitung-dengan-dpp-1112-dari-harga-jual-djp-kode-fakturnya-04) ·
[batas unggah tanggal 20 (Ortax)](https://ortax.org/batas-upload-fp-mundur-jadi-tanggal-20) ·
[UU KUP Ps. 28 (DDTC)](https://news.ddtc.co.id/literasi/kelas-pajak/16241/kewajiban-pembukuan-dan-pencatatan) ·
[UU 10/2020 Bea Meterai](https://www.pajak.go.id/sites/default/files/2020-11/UU%20Nomor%2010%20Tahun%202020%20tentang%20Bea%20Materai.pdf).
Paragraf SAK EP dibaca dari draf eksposur 2020, bukan teks final. Semua butir pajak perlu dikonfirmasi
konsultan pajak perusahaan sebelum dijadikan aturan aplikasi.

### 9.7 Perubahan terhadap rencana (bagian 3–6)

1. **Seri nomor jadi data, bukan kode.** Tabel seri (`sys_doc_series`: token urut / kode / bulan romawi /
   tahun, reset bulanan atau tahunan, per jenis dokumen dan per identitas) + tabel penghitung yang dikunci per
   baris, dengan layar input. Bawaan = format lama: invoice `{urut}/TM/{romawi}/{tahun}` bulanan, DP
   `{YY}{6 digit}` tahunan. Dokumen lain bisa pindah ke tabel ini nanti.
2. **Nomor terbit saat Confirm**, tidak bisa diketik, tidak dipakai ulang. Draft tanpa nomor (hapus draft
   tidak membuat lompatan). Invoice batal tetap memegang nomornya. Bulan nomor = bulan tanggal invoice. Ada
   laporan lompatan dan nomor ganda.
3. **Dasar tagih = qty diterima.** `do_line` diberi `qty_received`, diisi di Proof of Delivery (bawaan = qty
   kirim). Invoice mengusulkan diterima − sudah ditagih, per baris Surat Jalan.
4. **Qty Minim**: baris borongan, nilai = harga. Sama di CO dan invoice.
5. **Tanggal invoice bawaan = tanggal Surat Jalan terakhir** yang ditagih; jatuh tempo dihitung dari situ.
6. **Tagihan DP = permintaan, tanpa jurnal.** Jurnal lahir saat uang diterima (Bank pada Uang Muka Penjualan,
   + PPN bila diputuskan B). Sesuai SAP (permintaan uang muka), SAK EP, saat terutang PPN, dan kenyataan bahwa
   47 tagihan DP lama tidak pernah berlanjut. Umur piutang hanya berisi piutang dagang.
7. **Nilai invoice selalu penuh**; DP tampil sebagai baris potongan dan "sisa yang harus dibayar". Laporan
   penjualan memakai nilai penuh.
8. **Data pajak per invoice**: tarif dari sistem, harga jual + DPP nilai lain + PPN, nomor Faktur Pajak (diisi
   sesudah unggah Coretax), rujukan faktur DP, NPWP / NIK dari master customer (bukan teks bebas).
9. **Cetakan**: tempat meterai di Tanda Terima Uang Muka dan kwitansi di atas Rp 5 juta.
10. **Dikeluarkan dari tahap 1**: mata uang asing, diskon persen di kepala invoice. FOB cukup sebagai satu baris
    biaya dari `co_order_external_cost`.
11. **Pengendalian**: tidak ada Delete sesudah Confirm; peran penagihan dan kasir terpisah; laporan pisah batas
    (sudah dikirim belum ditagih, dan sebaliknya).
12. **Saldo awal piutang lewat lembar kerja di aplikasi**: invoice lama yang kemungkinan masih terbuka
    ditampilkan, Finance mencentang lunas / belum dan mengisi sisa. Per 10-Sep-2026: 218 invoice belum jatuh
    tempo (Rp 3,07 miliar); 842 invoice dalam 90 hari terakhir (Rp 9,55 miliar).
