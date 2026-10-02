# CUSTOMER INVOICE + UANG MUKA — DOKUMEN REVIEW

> Untuk direview user. Tanggal: 2026-10-02.
> Yang dibangun: **Tahap 1 (menagih)** dari `customerinvoice_plan.md` **+ penerimaan pelunasan (Record Payment)**.
> **Perubahan 2026-10-02 (perintah user: "yang lama dihapus saja, gantikan dengan yang baru"):** layar lama
> **Order to Cash** dan **Down Payment** sudah dihentikan dan diarsipkan; Customer Invoice menggantikan keduanya,
> terpasang di **mockup.test dan tmerp.test** (bagian 9).
> **Tambahan 2026-10-02 (perintah user: "sarankan yang terbaik dan jalankan dulu"; yang pasti menurut user: tiap tagihan
> terkait Surat Jalan dan validasi customer atas yang terkirim, dan uang muka jelas dari CO yang mana dengan riwayat
> terlacak):** bukti terima per baris Surat Jalan dengan alasan selisih, tab Delivery di invoice, buku uang muka per
> order, tab Billing di Customer Order, daftar To Invoice, laporan AR Aging dan Down Payment Ledger, satu akun bank
> per rekening — bagian 2 (F–I), 3, 4, 10.
> Ketentuan keuangan dan akuntansinya: `customerinvoice_accounting.md`.

---

## 1. Ringkasan

| Hal | Status |
|---|---|
| Customer Invoice dari Surat Jalan (draft, simpan, Confirm, Cancel, cetak) | jalan di mockup.test |
| Tagihan uang muka (Down Payment) + Record Receipt + Tanda Terima Uang Muka | jalan di mockup.test |
| Potongan uang muka otomatis di invoice | jalan |
| Nomor dari seri, terbit saat Confirm (dua seri invoice + dua seri uang muka) | jalan |
| Jurnal otomatis (invoice, potongan uang muka, penerimaan uang muka, jurnal balik saat Cancel) | jalan |
| Master Company Bank Accounts dan Document Number Series | jalan |
| Qty diterima customer di Proof of Delivery | jalan |
| Help tiga layar baru + terjemahan Indonesia | sudah dimuat |
| Penerimaan pelunasan (**Record Payment**): satu penerimaan untuk satu atau beberapa invoice, sebagian atau penuh | jalan |
| Layar lama Order to Cash + Down Payment | **dihentikan dan diarsipkan** (bagian 9) |
| **Bukti terima per baris Surat Jalan**: qty diterima + alasan selisih (rusak di jalan, diretur, kurang kirim) + catatan kondisi | jalan |
| **Tab Delivery** di invoice: Surat Jalan di balik tiap baris, yang dikirim / diterima / selisih, penerima, jam tiba, foto | jalan |
| **Buku uang muka per Customer Order** (ditagih → diterima → dipotong di invoice mana → sisa) di Down Payment Invoice, Customer Order, dan laporan | jalan |
| **Tab Billing** di Customer Order (hanya baca) | jalan |
| **To Invoice** (order yang Surat Jalannya belum habis ditagih), **AR Aging**, **Down Payment Ledger** | jalan |
| Satu akun bank per rekening perusahaan (111210–111250), di ddt dan tm2 | dibuat |
| Test otomatis | 10 test, 152 pemeriksaan, lulus di ddt dan tm2 |
| PPh 23 / selisih / kelebihan bayar di penerimaan, Statement of Account, Invoice Register | **belum** — sisa Tahap 2 |
| Credit Note, pengembalian uang muka, batas kredit, e-Faktur, saldo awal | **belum** — Tahap 3 |

---

## 2. Cara mencoba di mockup.test

Data contoh yang sudah disiapkan: satu Draft invoice (`Draft #367`, order CO2600029, PAK RUDI LAWANG, PPN) dengan
uang muka 30% yang sudah dibayar (INV-DP-2026-10-0001).

**A. Menagih (Draft yang sudah ada)**

1. Finance › Transaction Flows › **Customer Invoice**. Daftar memuat invoice lama, uang muka, dan `Draft #367`.
2. Buka `Draft #367`. Periksa: baris Folding Box 500 pcs dari DO/26/00001, uang muka 268.358,83 sudah diusulkan,
   Sisa Tagihan 626.170,58.
3. Tab **Jurnal**: pratinjau jurnal yang akan terbentuk.
4. **Confirm**. Nomor terbit `1/LJM/X/2026`, status jadi Confirmed, tab Jurnal berisi voucher yang terposting.
5. **Print**: cetakan kop CV. Langgeng Jaya Makmur, uang muka sebagai potongan, Sisa Tagihan tebal.
6. **Cancel** + alasan (bila ingin mencoba): jurnal dibalik, Surat Jalan dan uang muka bisa dipakai lagi, nomor tidak
   dipakai ulang.

**B. Uang muka baru**

1. Customer Invoice › **+ New Record** › **Down Payment** › pilih order (mis. CO2600027) › **Create**.
2. Isi Percent of Order (bawaan 50) atau kosongkan dan isi Bill Amount › **Save** › **Confirm**. Nomor
   `…/UM-LJM/X/2026` terbit; tab Jurnal masih kosong.
3. **Record Receipt** › tanggal, rekening, nilai › **Confirm Receipt**. Status Received, jurnal muncul, tombol
   **Print Receipt** mencetak Tanda Terima Uang Muka.

**C. Dari Surat Jalan sampai invoice**

1. Sales › Delivery › **Proof of Delivery** › buka Surat Jalan › Result = Received (atau Partially received dan isi
   qty per baris) › Save.
2. Customer Invoice › + New Record › Invoice › pilih order › **Next** › biarkan Invoice = Ya pada baris yang
   ditagih › **Create**.
3. Di Draft, kurangi Qty Ditagih bila menagih sebagian › Save › Confirm.

**D. Menerima pelunasan**

1. Buka invoice yang sudah Confirmed › **Record Payment**.
2. Isi tanggal dan rekening. Daftar memuat semua invoice terbuka customer itu (identitas yang sama); ketik
   **Paid Now** pada invoice yang dibayar — boleh sebagian, boleh beberapa invoice sekaligus.
3. **Confirm Payment**. Bukti bank masuk terbentuk (Dr Bank / Cr Piutang), tab Payments terisi, invoice yang lunas
   berstatus **Closed**. Invoice yang sudah menerima pembayaran tidak bisa di-Cancel.

**E. Bukti terima dengan selisih (retur / rusak di jalan)**

1. Sales › Delivery › **Proof of Delivery** › buka Surat Jalan yang belum dicatat › Result = **Partially received**.
2. Di **Received Quantity**: ketik qty yang benar-benar diterima, pilih **Reason for Difference** (Damaged in
   transit / Returned by customer / Short shipped / Other), isi Condition / Note bila perlu › Save. Tanpa alasan pada
   baris yang kurang, Save ditolak.
3. Finance › Transaction Flows › **To Invoice**: order itu tampil **Ready to invoice** dengan nilai yang diterima saja.
   Buka barisnya › Create › Draft invoice hanya memuat qty yang diterima.
4. Di invoice, tab **Delivery**: Surat Jalan, dikirim, diterima, selisih, alasan, penerima, jam tiba, foto.

**F. Riwayat uang muka**

1. Buka `INV-DP-2026-10-0001` (Customer Invoice › tab Down Payment) › tab **Down Payment History**: ditagih, uang
   diterima (nomor bukti bank), dipotong di invoice mana, saldo. Ringkasan di bawah memuat **Down Payment Left**.
2. Tombol **Open Customer Order** membuka order pemiliknya.
3. Sales › Order › **Customer Order** › buka CO2600029 › tab **Billing**: angka order, Surat Jalan dan invoicenya,
   dokumen invoice / uang muka, dan riwayat uang muka.

**G. Laporan**

- Finance › Transaction Flows › **To Invoice**: yang siap ditagih dan yang masih menunggu bukti terima.
- Finance › Financial Reports › **AR Aging**: sisa tagihan per customer menurut umur lewat jatuh tempo.
- Finance › Financial Reports › **Down Payment Ledger**: semua peristiwa uang muka per order, dengan saldo.

**H. Master**

- Finance › Transaction Flows › **Company Bank Accounts**: lima rekening dari sistem lama, masing-masing dengan akun
  buku besarnya sendiri (111210–111250).
- Setup › Settings › **Document Number Series**: empat seri (`INV_TM`, `INV_LJM`, `DP_TM`, `DP_LJM`).
- Setup › Constants: `DP_VAT_ON_RECEIPT` (1), `DP_DEFAULT_PERCENT` (50).

---

## 3. Layar (potret dari aplikasi, database dummy)

**Daftar** — satu daftar untuk invoice dan uang muka; baris merah muda = lewat jatuh tempo.

![Daftar](../mockups/customer_invoice_app/1_grid.jpg)

**+ New Record, langkah pilih baris Surat Jalan**

![New Record](../mockups/customer_invoice_app/2_new_record.jpg)

**Form Draft** — potongan uang muka, ringkasan, terbilang.

![Form](../mockups/customer_invoice_app/3_form_draft.jpg)

**Cetakan**

![Cetakan](../mockups/customer_invoice_app/4_print.jpg)

**Invoice, tab Delivery** — bukti di balik tagihan.

![Tab Delivery](../mockups/customer_invoice_app/5_invoice_delivery_tab.jpg)

**Customer Order, tab Billing** — Surat Jalan, invoice, dan riwayat uang muka satu order.

![Tab Billing](../mockups/customer_invoice_app/6_customer_order_billing_tab.jpg)

**Down Payment Invoice, tab Down Payment History**

![Riwayat uang muka](../mockups/customer_invoice_app/7_down_payment_history.jpg)

**To Invoice**

![To Invoice](../mockups/customer_invoice_app/8_to_invoice.jpg)

**AR Aging**

![AR Aging](../mockups/customer_invoice_app/9_ar_aging.jpg)

**Down Payment Ledger**

![Down Payment Ledger](../mockups/customer_invoice_app/10_down_payment_ledger.jpg)

**Proof of Delivery** — qty diterima, alasan selisih, catatan kondisi per baris.

![Proof of Delivery](../mockups/customer_invoice_app/11_proof_of_delivery_entry.jpg)

---

## 4. Aturan yang dijaga aplikasi

| Aturan | Yang terjadi di aplikasi |
|---|---|
| Tiap invoice berasal dari Surat Jalan satu Customer Order | invoice hanya bisa dibuat dari baris Surat Jalan; Confirm ditolak bila ada baris tanpa Surat Jalan atau tanpa order |
| Selisih terima harus beralasan | di Proof of Delivery, baris yang diterima kurang dari yang dikirim wajib punya Reason for Difference; yang tidak diterima tidak ditagih |
| Uang muka milik satu Customer Order | tagihan uang muka hanya dari Customer Order; hanya memotong invoice order yang sama; riwayatnya tampil di uang muka, order, dan laporan |
| Yang ditagih = qty diterima customer | Surat Jalan tanpa Proof of Delivery tidak muncul di pilihan; qty tagih > sisa ditolak saat Confirm |
| Tidak bisa tagih dobel | qty tertagih disimpan di baris Surat Jalan; baris yang habis ditagih hilang dari pilihan |
| Harga tidak diketik | diambil dari baris Surat Jalan (harga Customer Order); kolom Harga tidak bisa diisi |
| Qty Minim = harga borongan | nilai baris = harga; ditagih sekali, invoice berikut untuk baris itu bernilai 0 |
| Nomor terbit saat Confirm | Draft tampil `Draft #id`; nomor dari seri sesuai identitas dan bulan tanggal invoice |
| Nomor tidak dipakai ulang | invoice batal tetap memegang nomornya; invoice pengganti mendapat nomor berikutnya |
| Tagihan uang muka tidak menjurnal | tab Jurnal kosong sampai Record Receipt |
| PPN uang muka saat uang diterima | jurnal penerimaan memisah Uang Muka dan PPN Keluaran (order PPN, `DP_VAT_ON_RECEIPT` = 1) |
| Uang muka dipotong otomatis | diusulkan saat Draft dibuat; boleh dikurangi; tidak bisa melebihi sisa uang mukanya |
| Sesudah Confirm tidak berubah | Save dan Delete ditolak; hanya Cancel dengan alasan |
| Cancel dibatasi | ditolak bila sudah ada pembayaran atau periode buku sudah ditutup |
| Pelunasan tidak melebihi sisa | Paid Now > Outstanding ditolak; invoice customer lain atau identitas lain ditolak |
| Uang muka yang sudah diterima tidak bisa di-Cancel | ditolak (pengembalian uang muka = Tahap 3) |
| Periode buku | Confirm, Cancel, Record Receipt, dan Record Payment ditolak pada tanggal di periode tertutup |
| Rekening sesuai identitas | rekening PPN tidak bisa dipilih di dokumen Non PPN, dan sebaliknya |

---

## 5. Jurnal yang terbentuk

Angka dari `Draft #367` (order PPN, tarif 11%):

| Saat | Jurnal |
|---|---|
| Uang muka diterima (dummy lama, cara lama) | Dr Bank 268.358,83 / Cr Piutang 268.358,83 |
| Confirm invoice | Dr 112100 Piutang 894.529,41 / Cr 411000 Penjualan 805.882,35 / Cr 212100 PPN Keluaran 88.647,06 |
| Potongan uang muka (voucher yang sama) | Dr 213200 Uang Muka 241.764,71 / Dr 212100 PPN Keluaran 26.594,12 / Cr 112100 Piutang 268.358,83 |
| Sisa piutang | 626.170,58 |

Uang muka baru (cara baru): Record Receipt → Dr Bank / Cr 213200 Uang Muka Penjualan (+ Cr 212100 PPN Keluaran
untuk order PPN). Rincian dan contoh lain: `customerinvoice_accounting.md` bagian 5 dan 6.

---

## 6. Yang berbeda dari mockup yang disetujui

| # | Mockup | Aplikasi | Alasan |
|---|---|---|---|
| 1 | pilih Customer, lalu Customer Order | satu pilihan "Customer · CO · PPN" | satu langkah, tanpa memuat ulang layar |
| 2 | tombol **To Invoice** di daftar | belum ada; order yang bisa ditagih tampil di + New Record | daftar kerja terpisah = Tahap 2 |
| 3 | panel kanan Amount / Journal / Faktur Pajak | Ringkasan di bawah baris; Journal dan Pajak jadi tab | mengikuti bentuk form baku DevLib |
| 4 | centang baris Surat Jalan | tombol Ya / Tidak per baris | objek baris DevLib tidak punya kotak centang |
| 5 | Customer Receipt (bagian 5 mockup): layar tersendiri | dialog **Record Payment** di form invoice | cukup untuk mengganti layar lama; layar tersendiri dengan PPh 23 / selisih = sisa Tahap 2 |
| 6 | ikon cetak di tiap baris daftar | cetak dari form | menyusul bila diinginkan |
| 7 | rekening di cetakan: tebal | huruf biasa | objek cetak mencetak kalimat pembuka dengan huruf biasa |

---

## 7. Batasan yang perlu diketahui

1. **Pelunasan invoice** dicatat lewat **Record Payment** di form invoice. Yang belum: PPh 23 dipotong customer,
   selisih bayar, kelebihan bayar, dan uang yang belum tahu untuk invoice mana (sisa Tahap 2).
2. **Layar lama dihentikan**: Order to Cash dan Down Payment (v1 dan grid v2-nya) serta cetakan `sales-invoice`
   diarsipkan ke `C:\Users\iwanp\tmerp_unused\`; baris menunya dihapus. Umur piutang sekarang ada di laporan AR Aging.
   Invoice tanpa Surat Jalan **sengaja tidak ada lagi** (ketentuan user: tiap tagihan terkait Surat Jalan).
2a. **Retur sesudah invoice di-Confirm belum ada** (Credit Note). Retur dan rusak di jalan yang diketahui saat serah
   terima sudah tertangani di Proof of Delivery (tidak ditagih). Retur yang baru diketahui kemudian menunggu wawancara
   pemakai: kirim ganti, potong tagihan berikutnya, atau kembalikan uang (`customerinvoice_accounting.md` 7A).
2b. **Nasib barang yang tidak diterima** (kembali ke gudang, dikerjakan ulang, dimusnahkan) belum dicatat aplikasi.
3. **Hak akses** masih per modul (Finance & Accounting). Pemisahan hak buat / Confirm / terima uang / Cancel per orang
   belum ada.
4. **Akun pendapatan** semua baris = 411000. Pemetaan per kategori product belum.
5. **Biaya lain** (ongkos kirim) diketik di invoice; belum mengambil dari biaya eksternal Customer Order.
6. **Akun bank**: tiap rekening punya akun buku besar sendiri (111210–111250), dibuat dengan nama usulan di ddt dan
   tm2. Finance boleh mengganti nama / kodenya di Master of Accounts dan pemetaannya di Company Bank Accounts.
7. **Invoice lama** (36 baris dummy sebelum modul ini) tidak punya baris Surat Jalan: tampil dan tercetak sebagai satu
   nilai, bisa menerima Record Payment.
8. **Pesan salah saat Confirm** (yang memuat nomor baris) masih bahasa Inggris.
9. **Draft tidak memesan baris**: dua Draft bisa memuat baris Surat Jalan yang sama; yang kedua ditolak saat Confirm.
10. **E-mail** invoice dari form belum diaktifkan (tombol disembunyikan).
11. **Format cetak belum A** (menunggu koreksi user): Customer Invoice, Down Payment Invoice.

---

## 8. Yang dibuat dan diubah

**Tabel (ddt dan tm2):**
`tm2sys.sys_doc_series`, `sys_doc_series_counter` · `tm2acc.fin_company_bank`, `fin_invoice_line` · 21 kolom baru di
`tm2acc.fin_invoice` (nomor boleh kosong selama Draft) · `tm2sdi.do_line.qty_received` · view `v1_fin_ar_aging`
sekarang hanya memuat invoice Confirmed · `tm2sdi.do_line.receive_diff_reason`, `receive_note` · lima akun bank di
`tm2acc.fin_account` (111210–111250).

**Kode:**

| Bagian | File |
|---|---|
| Aturan dan jurnal | `app/Services/Acc/CustomerInvoiceService.php` |
| Seri nomor | `app/Support/Docs/DocSeries.php`, `app/Models/Sys/SysDocSeries.php` |
| Model | `app/Models/Acc/FinInvoiceLine.php`, `FinCompanyBank.php`, `FinInvoice.php` (kolom + relasi) |
| Layar | `resources/views/v2/screens/customer-invoice.blade.php`, `app/Support/V2/Data/CustomerInvoice.php`, `routes/v2_finance.php` |
| Master | `app/Support/V2/Grid/Acc/CompanyBankLookup.php`, `Setup/DocSeriesLookup.php` |
| Cetakan | `app/Support/V2/Print/CustomerInvoicePrint.php`, `DownPaymentInvoicePrint.php` |
| Proof of Delivery | `app/Services/Sales/DeliveryPromiseService.php`, `resources/views/v2/screens/proof-of-delivery.blade.php` |
| Objek DevLib | cetakan: kalimat pembuka berisi data (`nbRaw`); tabel: kolom angka yang sudah diformat (`raw`) |
| Migrasi | `2026_10_02_000140` (tabel, seri, rekening, konstanta, menu) · `000141` (terjemahan, menu Down Payment lama mati) · `000142` (contoh data, hanya dummy) · `000143` (hapus menu dan Help layar lama, terjemahan Record Payment) |
| Help | `resources/data/help/finance.json` (2 topik; topik layar lama dihapus), `master_setup.json` (1 topik) |
| Diarsipkan | `pages/finance/receivables.blade.php`, `pages/finance/dp-invoice.blade.php`, `Grid/Acc/ReceivableGrid.php`, `DownPaymentGrid.php`, `Print/SalesInvoicePrint.php`, `screen_finance_dp_invoice.md`, `mockup_down_payment.html`, `sales_invoice_a5.jpg` → `C:\Users\iwanp\tmerp_unused\` (MANIFEST) |
| Jejak Surat Jalan dan uang muka | `CustomerInvoiceService::deliveryChain`, `downPaymentLedger`, `orderBilling`; tab Billing di `resources/views/v2/screens/customer-order.blade.php` |
| Daftar dan laporan | `app/Support/V2/Grid/Acc/ToInvoiceGrid.php`, `ArAgingReport.php`, `DownPaymentLedgerReport.php` |
| Migrasi tambahan | `2026_10_02_000170` (alasan selisih, akun bank per rekening, menu To Invoice / AR Aging / Down Payment Ledger, terjemahan) |
| Objek DevLib (tambahan) | judul kolom tabel berbentuk `['Label', 'num' => true]` sekarang ikut diterjemahkan |
| Test | `tests/Feature/CustomerInvoiceTest.php` |

**Test** (`CustomerInvoiceTest`, 10 test / 152 pemeriksaan, lulus di ddt dan tm2): format nomor · invoice Non PPN dengan uang muka + Cancel + nomor tidak dipakai ulang · uang muka PPN
(pisah harga dan PPN, reklas di invoice) · tagih sebagian dan tolak qty lebih · baris borongan · hapus Draft dan tolak
Cancel uang muka yang sudah diterima · alur lengkap lewat layar (buat, simpan, Confirm, terima uang, cetak, Record Payment sebagian lalu lunas, Cancel
ditolak sesudah ada pembayaran; tab Delivery, tab Billing di Customer Order, tiga laporan terbuka) · satu penerimaan untuk dua
invoice dan tolak nilai di atas sisa · terima sebagian tanpa alasan ditolak, dengan alasan tersimpan, invoice hanya memuat
yang diterima · buku uang muka satu order (ditagih, diterima, dipotong, saldo) dan uang muka tanpa order ditolak.
Semua data buatan test dihapus dan penghitung nomor dikembalikan; sudah diperiksa ke database.

---

## 9. Keadaan kedua situs (2026-10-02)

| Hal | mockup.test (ddt) | tmerp.test (tm2) |
|---|---|---|
| Migrasi `000140`, `000141`, `000143`, `000170` | terpasang | terpasang (dijalankan satu per satu dengan `--path`) |
| Menu To Invoice, AR Aging, Down Payment Ledger | ada | ada |
| Menu Customer Invoice, Company Bank Accounts, Document Number Series | ada | ada |
| Menu Order to Cash, Down Payment | dihapus | dihapus (salinan barisnya: `tmerp_unused/database/retired_order_to_cash_rows_tm2_2026_10_02.json`) |
| Empat seri nomor, dua konstanta | ada | ada |
| Lima rekening perusahaan | ada, akun 111210–111250 | ada, akun 111210–111250 |
| Help | dimuat | dimuat |
| Contoh data (`Draft #367`) | ada | tidak ada (migrasi `000142` hanya untuk dummy) |
| Invoice lama | 36 | 36 |

tmerp.test sekarang bisa menerima uang (Record Receipt dan Record Payment): tiap rekening sudah punya akun buku besar.
Finance cukup memeriksa nama akun usulannya.

---

## 10. Yang perlu diputuskan saat review

Atas perintah "sarankan yang terbaik dan jalankan dulu", butir 1, 3, 4, 5, 7 **dijalankan dengan pilihan bawaan** dan
bisa diubah sesudah wawancara pemakai:

1. **Kode seri**: `LJM`, `UM-TM`, `UM-LJM` — **dipakai** (bisa diganti di Document Number Series; berlaku untuk nomor berikutnya).
2. ~~Menu Down Payment lama dan Order to Cash~~ — **diputuskan user 2026-10-02: dihapus, diganti yang baru.**
3. **Tanggal invoice** bawaan = tanggal Surat Jalan terakhir — **dipakai** (sama dengan 92% invoice sistem lama; bisa diubah per invoice).
4. **Format cetak** Customer Invoice dan Down Payment Invoice: **tetap seperti sekarang**, belum ditandai A sampai ada koreksi.
5. **Penanda tangan** di cetakan invoice: **tetap empat kolom** sampai ada koreksi.
6. Sisa Tahap 2: To Invoice, AR Aging, Down Payment Ledger, tab Billing **sudah dibangun**. Yang tersisa: PPh 23 /
   selisih / kelebihan bayar di penerimaan, Statement of Account, Invoice Register.
7. **Akun bank per rekening**: **lima akun baru 111210–111250** (satu per rekening, supaya bisa direkonsiliasi).

Yang **perlu jawaban dari wawancara pemakai** sebelum dibangun:

- **Retur sesudah invoice**: kirim ganti barang, potong tagihan berikutnya (Credit Note), atau kembalikan uang?
- **Barang yang tidak diterima customer**: kembali ke gudang barang jadi, dikerjakan ulang, atau dimusnahkan — dan siapa
  yang memutuskan?
- **Siapa memvalidasi di pihak customer** dan dalam bentuk apa (tanda tangan di Surat Jalan, berita acara, portal)?
  Sekarang: nama penerima + foto Surat Jalan bertanda tangan.
- **Uang muka**: apakah ada uang muka yang tidak terkait satu order (deposit customer)? Sekarang tidak dimungkinkan.
- **PPh 23**: apakah customer memotongnya, dan untuk pekerjaan apa?
