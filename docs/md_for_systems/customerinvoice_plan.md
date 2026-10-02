# CUSTOMER INVOICE + ACCOUNTS RECEIVABLE — RENCANA MODUL

> Status: **RENCANA, menunggu OK user** (2026-10-02). Belum ada kode yang diubah.
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
| CO ber-DP sejak 2025 (109 CO) | 30 punya invoice akhir **tanpa potongan DP tercatat**, 3 potongannya beda nilai, 10 belum ada invoice akhir | risiko tagih dobel / DP terlupa — perlu dicek Finance |
| Pembayaran customer | `sdi_cinv_rcp` 0 baris, `remain_of_bill` kosong | sistem lama **tidak tahu siapa masih berhutang berapa** |
| Invoice per CO | 88% satu invoice, sisanya 2 s.d. 10+ (kirim bertahap) | 1 invoice = 1 CO, banyak Surat Jalan |
| Qty tagih ≠ qty diterima | 14% baris | qty tagih harus bisa beda dari qty kirim |
| Satu baris menggabung beberapa Surat Jalan | 9% baris | baris cetak = per product, daftar No. SJ |
| Syarat bayar | 30 hari 65% · Dimuka 12% · Kontan 9% · 45 hari 7% | jatuh tempo dihitung dari master syarat bayar |

Catatan: angka "30 CO tanpa potongan DP" dicocokkan lewat teks "DP" di keterangan diskon; bisa saja dipotong
dengan cara lain. Tetap layak diperiksa sebelum saldo awal piutang dimasukkan.

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

## 8. Yang perlu diputuskan user

1. **Nomor invoice.** Tetap format lama `275/TM/IX/2026` (urut per bulan) dan seri sendiri untuk DP, atau
   format baru? Usul: tetap format lama, customer sudah mengenalnya.
2. **Qty Minim** (kolom "Qty Minim: Ya / Tidak" di layar lama, 183 baris sejak 2025): aturan tagihnya apa?
3. **Uang muka pada order PPN.** Apakah DP diterbitkan Faktur Pajak sendiri (PPN diakui saat DP), atau PPN
   seluruhnya di invoice akhir?
4. **Siapa yang membuat invoice.** Finance saja, atau admin Sales membuat draft dan Finance yang Confirm?
5. **Rekening di cetakan.** Lima rekening lama boleh disalin ke master baru? Mana default PPN dan Non PPN?
6. **Saldo awal piutang.** Sistem lama tidak mencatat pembayaran; daftar piutang terbuka saat pindah harus
   datang dari catatan Finance (Excel). Siapa yang menyiapkan?
