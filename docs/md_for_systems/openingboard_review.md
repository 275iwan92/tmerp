# Opening Board (papan widget layar pembuka) — untuk review user

Dibangun 2026-10-02 atas permintaan user: *"bisakah disediakan many widget including graphics dan lain-lainnya, lalu dengan
bantuan asisten AI seseorang bisa meminta untuk dipasangkan widget-nya — jadi opening screen menjadi lebih fleksibel"* dan
*"top management dulu yang diprioritaskan, yang lainnya masih ikut template yang dibuat"*.

**Status:** jalan di **mockup.test (database ddt)**. **tmerp.test (tm2) belum** — tabelnya belum dibuat di sana, layar
pembuka tmerp.test tidak berubah. Dipasang ke tm2 setelah user setuju.

---

## 1. Cara mencoba (mockup.test)

1. Login sebagai top management (yang boleh membuka Laporan Manajemen: Presiden Direktur, Direktur, General Manager,
   Operational Manager). Sesudah login langsung tampil **layar pembuka sendiri**, tanpa tab yang menutupinya.
2. Bagian atas: salam, jabatan, atasan, ringkasan hari ini (nilai order bulan ini, sisa ke target, menunggu keputusan
   Anda, sisa hari kerja), jam, cuaca kantor.
3. **Papan Saya**: 12 widget bawaan profil Management.
4. Klik **Customize** → tiap widget mendapat tombol pindah lebih awal / akhir, lebih sempit / lebar, lepas. **Add Widget**
   membuka katalog. **Reset to Template** mengembalikan susunan bawaan. **Done** menutup mode atur. Tiap perubahan langsung
   tersimpan.
5. Buka **Asisten AI** (roda gigi di sebelah nama → asisten) dan ketik:
   - `pasang widget umur piutang` / `add widget top customers`
   - `lepas widget kata harian`
   - `widget apa saja`
   - `reset widget`

   Papan berubah saat itu juga, tanpa memuat ulang halaman.

![Papan](../mockups/opening_board_app/1_board.jpg)

![Customize](../mockups/opening_board_app/2_customize.jpg)

![Add Widget](../mockups/opening_board_app/3_add_widget.jpg)

![Asisten](../mockups/opening_board_app/4_assistant.jpg)

---

## 2. Widget yang tersedia (16)

| Widget | Bentuk | Arti angkanya | Sumber |
|---|---|---|---|
| Order Value This Month | KPI | Jumlah subtotal baris Customer Order **Confirmed / Closed** bertanggal bulan ini; dibanding target dan bulan lalu | `co_order`, `co_order_item`, target `REVENUE` |
| New Customer Orders | KPI | Jumlah dokumen Customer Order bertanggal bulan ini, semua status kecuali Cancelled | `co_order`, target `NEW_ORDERS` |
| Overdue Receivables | KPI | Sisa tagihan customer yang sudah lewat jatuh tempo, per hari ini | `v1_fin_ar_aging`, batas `OVERDUE_RECEIVABLES` |
| On-time Delivery | KPI | Persen Delivery Order customer yang berangkat bulan ini tepat atau sebelum tanggal janji CPDEL | `do_delivery`, `do_line`, `co_order_delivery`, target `ONTIME_DELIVERY` |
| Sales Today | KPI | Nilai Customer Order yang masuk hari ini, Draft + Confirmed (sama dengan jawaban asisten "sales today") | `co_order`, `co_order_item` |
| Order Value: Target vs Actual | Grafik batang + garis | Order value 12 bulan terakhir (batang) dan target bulanannya (garis) | sama dengan Order Value |
| Targets by Area | Daftar pencapaian | Target perusahaan bulan ini: nilai order, order baru, customer baru, pengiriman tepat waktu, piutang lewat jatuh tempo | `sys_kpi_target` + sumber tiap angka |
| Sales Pipeline | Grafik batang mendatar | Jumlah dokumen bertanggal bulan ini: Inquiry, harga terbit (Pro-Cost), Quotation terbit, Customer Order | `iqp_inquiry`, `iqp_costing`, `quo_quotation`, `co_order` |
| Target Achievement per Sales | Tabel peringkat | Order value Confirmed bulan ini per sales person terhadap target pribadinya | `co_order`, `sales_person`, target `REVENUE` per orang |
| Waiting for Your Decision | Daftar | Dokumen di antrian approval yang menunggu keputusan user login | flow engine (sama dengan Approval Center) |
| Customer Orders by Status | Donat | Customer Order tahun ini per status | `co_order` |
| Top Customers | Grafik batang mendatar | Lima customer dengan order value Confirmed tertinggi tahun ini | `co_order`, `cust_customer` |
| Receivables Aging | Grafik batang | Sisa tagihan per umur lewat jatuh tempo (belum jatuh tempo, 1-30, 31-60, 61-90, > 90 hari) | `v1_fin_ar_aging` |
| Material Shortage | Daftar | Bahan yang kurang menurut Material Planning (angka yang sama dengan popup merah) | Material Planning |
| Deadstock Value | KPI | Nilai stok yang tidak bergerak lebih dari 6 bulan (sama dengan jawaban asisten "deadstock") | stok on hand RM / SFG / FG |
| Quote of the Day | Kata harian | Satu baris per hari dalam setahun | `sys_daily_quote` |

Catatan definisi:
- **Order value memakai Customer Order Confirmed / Closed saja.** Draft dan yang menunggu approval belum dihitung. Di data
  dummy bulan ini: Confirmed Rp 2,4 jt, sedangkan Draft Rp 162 jt — jadi angkanya terlihat kecil.
- **Sales Pipeline bukan corong.** Customer Order boleh masuk langsung tanpa Quotation, jadi jumlahnya tidak selalu mengecil;
  ditampilkan sebagai batang supaya tidak menyesatkan.
- **On-time Delivery** menampilkan tanda strip bila belum ada Delivery Order yang berangkat bulan itu (di data dummy belum ada).
- Widget tanpa target menampilkan "No target" / "Target bulan ini belum diisi".

---

## 3. Yang bisa diatur user (semua lewat layar, tidak ada yang hardcode)

| Layar | Isinya |
|---|---|
| Setup → **Opening Board Widgets** | Katalog widget: judul, uraian, kelompok, modul + level minimum yang boleh memasang, lebar bawaan, kata yang didengar asisten, aktif |
| Setup → **Opening Board Templates** | Susunan bawaan per profil (sekarang: Management): widget, urutan, lebar |
| Setup → **KPI Targets** | Target per bulan: perusahaan (Sales Person = Company) atau per sales person untuk nilai order |
| Setup → **Daily Quotes** | Kata harian, 366 baris |
| Settings → Constants | `COMPANY_WEATHER_CITY`, `COMPANY_LATITUDE`, `COMPANY_LONGITUDE` (lokasi cuaca) |

Job Description dan SOP di salam diambil dari master jabatan (Position) dan SOP departemen; tombolnya tampil bila datanya ada.

---

## 4. Aturan yang dipakai

- **Siapa yang mendapat papan:** yang boleh membuka modul Laporan Manajemen (aturan yang sama dengan pendaratan login
  sebelumnya). Jabatan lain: layar pembuka lama, sampai templatenya dibuat.
- **Siapa yang boleh memasang widget apa:** hak modul widget (atau Laporan Manajemen) + level minimum + hak melihat harga
  jual untuk widget berisi rupiah penjualan. Memakai security yang sudah ada, tidak ada skema hak baru.
- **Asisten tidak menghitung apa pun.** Ia mencocokkan nama widget dari katalog lalu memanggil aturan papan yang sama dengan
  tombol Customize. Kata "widget" selalu berarti perintah papan.
- **Papan pertama** = salinan template. Mengubah template tidak mengubah papan yang sudah diatur pemiliknya.
- Widget yang dimatikan di katalog hilang dari semua papan tanpa menghapus susunan siapa pun.
- Satu widget yang gagal dimuat tampil sebagai kartu kosong; layar pembuka tetap terbuka.

---

## 5. Yang belum / perlu keputusan user

1. **Pasang ke tmerp.test (tm2)?** Migrasi `2026_10_02_000160` + `000161` belum dijalankan di tm2. Target contoh hanya dibuat
   di ddt; di tm2 tabel target kosong sampai diisi.
2. **Jabatan lain** (Sales, umum): template `x-dl::dashboard` sudah ada di DevLib dengan data contoh, belum dipasang di
   mockup.test dengan data database.
3. **Pilihan periode** (harian / mingguan / bulanan / 3-6-12 bulan) seperti di template lama: papan widget sekarang memakai
   periode tetap per widget (bulan ini, tahun ini, 12 bulan). Perlu?
4. **Widget berikutnya:** margin kotor (perlu aturan siapa yang boleh melihat HPP), output produksi, status Work Order,
   pembelian outstanding, stok per gudang, kehadiran.
5. **Order value = Confirmed saja** — atau Draft ikut dihitung?
6. **Layar HP:** papan sudah menjadi satu kolom di layar sempit, tetapi laci menu untuk HP (dari cabang lama) belum dipasang.

---

## 6. Teknis (untuk developer)

- **DevLib:** `x-dl::widget-board` (`packages/devlib/src/Components/WidgetBoard.php`, view `widget-board.blade.php`, skrip
  `widget-board.js`); `x-dl::dashboard` menampilkan papan bila datanya memuat kunci `widgets`. Contoh: devlib.test →
  Opening Dashboard → nomor 4 (`devlib/app/Support/BoardDemo.php`, susunan di sesi).
- **ERP:** `app/Support/V2/Board/` — `Board` (profil, susunan, aksi, isi layar), `WidgetCatalog` (kelas + baris `sys_widget`),
  `Numbers` (satu tempat hitung, diuji), `Widget` (induk), `Widgets/*` (16 kelas). Route `v2.board` (GET = HTML papan, POST = aksi).
- **Asisten:** `app/Assistant/Metrics/BoardWidgets.php` (`Assistant\Contracts\Command`: perintah menang atas pertanyaan angka);
  `Reply::$refresh` → layar menyiarkan `dl:refresh` → papan memuat ulang.
- **Tabel (tm2sys):** `sys_widget`, `sys_board_template`, `sys_user_board`, `sys_user_widget`, `sys_kpi_target`, `sys_daily_quote`.
- **Widget baru** = satu kelas turunan `Widget` + satu baris di `WidgetCatalog::CLASSES`; baris katalognya lahir sendiri.
- **Test:** `tests/Feature/OpeningBoardTest.php` (7 test, 58 pemeriksaan) dijalankan terhadap ddt; di tm2 dilewati karena
  tabelnya belum ada. User uji tidak disimpan; baris papan dan target tahun 2099 dihapus di akhir (sudah diperiksa ke database).
