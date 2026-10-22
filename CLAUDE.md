# CLAUDE.md ERP CV. Telasih Makmur v2

> Disusun ulang 2026-10-14 (disetujui Jo) dari 515 baris. Versi lama dengan bagian usangnya: `tmerp_unused/CLAUDE_before_ringkas_2026-10-14.md` dan riwayat git.
> Aturan user bernomor (A.n..E.n): indeks `docs/ringkas/globaluserrules.md`, detail `docs/globaluserrules.md` pada nomor yang sama. Bahasa jawaban ke Jo: Indonesia, pendek, jujur.
> Awal sesi: `php artisan qc:reviews --env=mockup` (perintah milik library DevLib, tersedia juga di tmerp; kerjakan Need improvement, D.19), `php artisan gen:requests` dari folder `../devlib/site` (perintah situs acuan, tidak ada di tmerp), dan baca rating <= 3 bintang di devlib.test > Quality & Rating. Sebelum diskusi atau usul di modul X, baca TG (D.24): `docs/tg/00_big-map.md`, halaman sub-modul X, kartu terkait, dan kartu modul hulunya.

## 1. Bisnis dan aturan keras
- OFFSET PRINTING (karton box/kemasan). Semua data dummy mulai dari INQUIRY dan wajib punya **Master Card + artwork + dieline** dengan ukuran jelas (P x L x T, datar, mm). Berkas asli Jo di `docs/attachments/MC-*.xlsx` dan `docs/attachments/mastercard/` dipakai apa adanya; dieline tidak digambar ulang. **Per level (stage) hanya 2 set, maksimum 3, dan dua set itu wajib benar-benar real dan lengkap (B.17); Test/DEMO/Contoh bukan set sah; dummy wajib bisa di-reset.**
- **Semua zona beku**: flow data dan flow dokumen tidak diubah kecuali Jo meminta; jangan pernah mengubahnya tanpa seizin Jo. Ragu = tanya dulu (file, elemen, sebelum -> sesudah). Teks UI Indonesia -> English sudah diizinkan.
- Master REAL (`prod_machine`, `prod_process`, `prod_process_machine`, pegawai) tidak disentuh, tidak diberi `data_origin='DUMMY'`; spek mesin tidak dikarang. Dummy hanya di `ddt*`, data real di `tm2*`. **Sebelum UAT dimulai (Jo 2026-10-16): apa pun yang berubah di mockup.test (kode, skema, migration) wajib diterapkan juga di tmerp.test; cara kerja identik, beda hanya data dan penanda (mis. logo).** Setelah UAT berjalan, tmerp.test tidak diuji agen.
- **ATTENTION ENGINE (Jo 2026-10-13):** baca `docs/ENGINE-ATTENTION.md` sebelum menyentuh engine (ProDev / ProCost / solver PPIC); butir terbuka ATTENTION-ENGINE-01..08.
- MRP/MPS lama sengaja ditinggalkan (tag `arsip/mrp-mps-2026-08-30`); tabel `prd_mrp_*` dibiarkan.

## 2. Prinsip layar PPIC (scheduling secepat dan seakurat mungkin)
- Secepat: interaksi minimum, pilihan awal langsung menampilkan data relevan, pratinjau dampak seketika.
- Seakurat: angka dari data nyata; bentrok mesin/jam/crew/bahan dicek engine di server dan ditandai; waktu dan persen konsisten dari satu sumber.
- Pemeriksa tujuan: tidak ada proses BOP yang tidak dikerjakan (laporkan celahnya). **Proses BOP tanpa mesin = SUBCON**, sah, bukan celah.
- Hulu: Inquiry -> ProDev (engine: BOP, BOM, layout, Master Card auto) -> ProCost -> CO -> Planning. PPIC hanya lanjutan; jangan menambah logika ProDev/ProCost di layar PPIC.

## 3. Kode: remark, sederhana, tanpa dead code (WAJIB tiap tugas)
- Setiap file yang diubah memuat blok REMARK di atas: **Tujuan, Sumber hulu, Input, Output, Dipakai oleh, Status**; baris layarnya di `docs/SCREEN-INDEX.md` diperbarui. PHP `/* REMARK ... */` (jangan `*/` di dalam teks), Blade `{{-- REMARK ... --}}`, JS/CSS `/* REMARK ... */`; hindari pola `label="..."`/`title="..."` di teks remark. "Ini untuk apa?" dijawab dari REMARK dan SCREEN-INDEX.
- Periksa dan HAPUS dead code pada file yang disentuh (fungsi/rute/prop/CSS/JS tak dirujuk, variabel tak terbaca, duplikasi): buktikan dulu lewat grep rute/view/JS/tes, hapus (bukan komentar), perilaku tidak berubah. Yang meragukan (flag URL seperti `?layout=tabs`, `?extras=1`) jangan dihapus, daftarkan di laporan.
- Kode minimal yang menyelesaikan masalah; tanpa fitur spekulatif. Komentar hanya untuk yang tidak lazim.
- **Teks HITAM (latar terang) atau TERANG (latar gelap), tanpa abu-abu**; abu-abu hanya token `--dl-disabled` untuk yang disabled. Ditulis di token / objek DevLib, bukan per layar (ubah satu, semua turunan ikut). Layar review = blog (`x-dl::review-feed`), huruf besar, tombol keputusan besar.
- Dasar: OO + komponen modular + library; **DevLib membuat, tmerp mewarisi** (skill `devlib-oo`, `devlib-inheritance`; rulebook D.15, D.17, D.18, D.21). **Cek `../devlib` dan `packages/mockup-parts` dulu**, pakai atau turunkan objek yang ada; layar v2 = objek `x-dl::*` / `x-mp::*`, tanpa tag mentah, warna literal, atau kelas lokal (dijaga `DevlibOnlyRatchetTest`).
- UI: tombol dokumen selalu English (New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back; status Draft/Confirmed/Cancelled/Closed); aksi di panel atas form, tab Riwayat selalu ada. Input teks = `x-dl::field.text` (badge aa/Aa/AA); nilai yang ada di master dipilih lewat lookup; warna lewat token, bukan hex. Tanggal `dd-Mmm-yyyy`.

## 3b. Prinsip bisnis (rinci di rulebook)
- Quotation WAJIB integrated (inquiry costing PUBLISHED); Customer Order boleh direct/INSTANT (A.1-A.2). 1 Quotation = 1 CO; `due_date` wajib sebelum Confirm CO (A.4, A.10).
- Alur Inquiry → ProDev → ProCost lewat tiga tanda `stat_confirm_sales/_prodev/_procost` (A.20). Engine menyarankan, petugas memutuskan (A.7).
- Costing rahasia: HPP/margin hanya Costing + Direksi; publish = deklasifikasi ke Sales; harga disaring di server (A.8-A.9).
- Snapshot: transaksi membekukan master saat momen penting (A.13). Due date = 14 hari kerja dari approval proof FISIK.
- Alur vital Inquiry/ProDev/ProCost/Quotation/CO/CPDEL: baca `docs/claude_vital_flows.md` sebelum menyentuhnya.

## 4. Stack dan perintah
PHP ^8.3, Laravel 13; UI = objek DevLib (`x-dl::*`) dan MockupParts (`x-mp::*`) dengan CSS/JS siap pakai, tanpa Livewire, Filament, PowerGrid, Tailwind, Vite, atau npm (v1 dibuang 2026-10-22, arsip di `tmerp_unused/arsip_v1_*.zip`). Tiga situs: **devlib.test** (situs acuan objek `x-dl::*`, DB `devlib`; kodenya di repo devlib, `../devlib/site`, bukan di repo tmerp), **mockup.test** (UAT, DB dummy `ddt*` via `.env.mockup`) dan **tmerp.test** (live, `tm2*`; Herd) = satu kode tmerp.

```bash
composer setup                       # install + .env + key + migrate
php artisan test --filter=NamaTest   # test (MySQL lokal sungguhan, bersihkan sendiri di tearDown)
vendor/bin/pint --dirty              # format PHP
php artisan build:catalog --env=mockup   # setelah ubah layar/fitur; commit build.json
php artisan engine:catalog               # setelah daftar engine berubah
```

- Layar/komponen baru: deklarasi objek `x-dl::*` / `x-mp::*` (skill `devlib-oo`; aturan entri di service `app/Services/*`); mockup JPEG: `tmerp-ui-mockup`. Prioritas skill: `devlib-oo` + `devlib-inheritance` > `tmerp-stack-rules`/`tmerp-ui-mockup` > skill komunitas > skill generik.
- Test tanpa `RefreshDatabase`; test yang menyisipkan data wajib cleanup sendiri lalu diverifikasi ke DB. `CareersPortalV2Test` skip bila Firebase Emulator mati (normal).
- Artisan situs dummy: `php artisan ... --env=mockup`. Mode Developer = F10.

## 5. Database (LOCKED)
| Koneksi = nama DB | Isi |
|---|---|
| `tm2mst` | master netral (customer, product, material, geo, currency, uom) |
| `tm2sdi` | sales: inquiry `iqp_*`, `quo_*`, `co_*`, `do_*`, `crm_*` |
| `tm2pur` / `tm2inv` / `tm2acc` | procurement / inventory / finance |
| `tm2prd` | production, WO, scheduling, QC |
| `tm2hrm` / `tm2hrr` / `tm2pyr` | HR organisasi / rekrutmen / payroll (terenkripsi) |
| `tm2mnt` | maintenance (dummy `ddtmnt`) |
| `tm2sys` | user, menu, konstanta, audit (default connection) |
| `tm2old`, `tm2crp` | data lama untuk migrasi, staging portal kandidat |

- Dummy = `ddt*` (salinan penuh `tm2*`): `ddt:copy`, `ddt:copy-sys`, `ddt:stage`, `ddt:baseline`. Dummy salah dihapus per baris, tanpa cadangan; jangan `ppic:seed-dummy --purge` total.
- **Lintas-DB**: tanpa FK lintas-database. Default: relasi Eloquent, query terpisah, cocokkan di PHP. VIEW read-only di DB tujuan (JOIN `db.table`) hanya bila performa baca jadi prioritas nyata. JANGAN `->join()`/`whereHas()` bertingkat lintas koneksi. SQL mentah lintas-DB wajib `App\Support\DbName::table('tm2mst.x')`. Integritas lintas modul = validasi `exists:conn.table,col` dan logika Service.
- **Penamaan**: PK `id_<entitas>` BIGINT auto number di semua tabel; kolom English snake_case; master `activate` + `created_at/by`, `updated_at/by`; dokumen `stat_confirm/stat_cancel/stat_close` + `cancel_reason`; transisi di `doc_state_log`. Prefix tabel per domain (`cust_`, `prod_`, `mat_`, `wh_`, `iqp_`, `quo_`, `co_`, `do_`, `crm_`, ...). Model wajib set `$connection`, `$table`, `$primaryKey`. Nama file/folder tanpa emoji atau non-ASCII.
- **Bahasa**: database dan formula English; bahasa lain hanya layar/cetak lewat `sys_dictionary` (`t()`, `tf()`); nilai data dibungkus `raw()`; tombol, badge, status selalu English.
- Payroll: baca lewat model (cast `PayrollEncrypted`), jangan SQL mentah.

## 6. Peta kode
- Halaman v2: `routes/v2*.php`, `resources/views/v2`, menu dari `sys_app_menu` lewat `App\Support\V2\AppMenu` (`target='v2:<slug>'`). Layar v1 (Livewire SFC) sudah seluruhnya dibuang (D.25/D.26); yang tersisa hanya layar v2.
- Logika bisnis di `app/Services/*` (`Costing`, `Imposition`, `Sales`, `Scheduling`, `Payroll`, ...), bukan di komponen. Engine terdaftar di `App\Support\Engines\EngineRegistry`.
- Grid: `DbGrid` + kelas turunan di `App\Support\V2\Grid`; objek DevLib di `../devlib` (repo git sendiri `Herd\devlib`, setara tmerp, dipasang lewat Composer path repository `jo/devlib` (tautan `vendor/jo/devlib`); dipindah dari `packages/devlib` 2026-10-19; situs acuan devlib.test = `../devlib/site`; **perubahan DevLib di-commit di repo itu, bukan di git tmerp**), bagian khusus di `packages/mockup-parts`.
- `DataOriginScope` (web) menyembunyikan DUMMY/LEGACY sesuai setelan admin; console/test melihat semua.
- Peta lengkap dokumen: `docs/index_master.md`, `docs/md_for_systems/uistandardindex.md`, `docs/claude_docs_reference.md`; alur vital Inquiry/ProDev/ProCost/Quotation/CO/CPDEL: `docs/claude_vital_flows.md` (WAJIB dibaca sebelum menyentuhnya).
- **"Usang" / "arsipkan" (Jo 2026-10-21, D.26) = masuk ZIP di `tmerp_unused/` lewat `php artisan archive:zip <batch> <path>...`, berkas dihapus dari situs; jangan ditanyakan lagi.**
- Dokumen usang dicari per topik (isi lengkap tersimpan di `sys_md_library`): `php artisan md:library search "kata kunci" --env=mockup`, baca dengan `md:library show <id>`; tambah arsip baru: `md:library add` setelah memindah berkas ke `tmerp_unused`.

## 7. Cara kerja
1. Baca dokumen/DDL dulu, jangan menebak kolom. Ragu atau >1 tafsir: sebut opsi; ambiguitas berbahaya (sulit dibalik, arsitektural, lintas modul) = berhenti dan tanya.
2. Iterasi kecil, satu layar/fitur per waktu; konfirmasi perjalanan user dulu (buka X -> lihat Y -> klik Z).
3. **Perubahan flow dan mockup UI/UX hanya seizin Jo (pemilik proyek).** Izin didapat lewat **PREVIEW** (JPEG/tangkapan layar) + penjelasan singkat yang memuat: disimpan ke tabel mana, database mana, apa yang disimpan, dan cara menyimpannya (rumusnya). Jo agree -> commit dan push ke GitHub otomatis. Jo tidak agree -> rollback.
4. **Uji hasil sendiri, otomatis, tanpa Jo**, senyap di latar belakang, selalu dengan Sonnet 5.5 medium; metode apa pun boleh asal tidak bertanya atau meminta prompt dari Jo. Layar dinilai dari tampilan nyata (tangkapan layar sendiri), bukan dari "test lulus".
5. Setiap nilai yang dibaca aplikasi (konstanta, master, identitas) wajib punya layar input.
6. Commit/push hanya setelah Jo agree (butir 3); `git add` path eksplisit karena satu folder, satu git index; satu branch `main`, tanpa branch/worktree. Trailer commit `Agent: Iwan` (Senin-Selasa) atau `Agent: Jo` (Rabu-Sabtu). Mengganti aturan lama "commit+push tiap selesai".
7. Backlog: item selesai pindah ke `docs/LOG_JOB_DONE.md`; aturan baru dari Jo ditambahkan ke rulebook.
8. **Uji mandiri lewat login admin (Jo 2026-10-14, berlaku selamanya):** agen BOLEH dan HARUS menguji sendiri sebagai user `admin` di **devlib.test**, **mockup.test**, dan (selama UAT belum dimulai; tahap sekarang masih jauh dari UAT) **tmerp.test**, termasuk login admin di browser uji (Edge headless / CDP). **TIDAK BOLEH** login atau menguji di **tmerp.test setelah UAT berjalan**.
9. Jangan memakai `employee_id=1` untuk uji; jangan mengetik password akun nyata Jo atau layanan lain.
10. **Technical Guide (D.24):** `docs/tg` = kondisi sistem sekarang, English. Usul skema/field/layar/aturan baru wajib blok "Already exists? Searched where, result" dari `php artisan concept:find "<kata>"`. Mengubah berkas yang dikutip kartu = memperbarui kartunya dalam commit yang sama (hook pre-commit menolak; `php artisan docs:check`). Halaman sub-modul wajib punya Read/Write map dan Table relations; gambarnya dibangun `php artisan docs:rw` (jangan digambar tangan). Setelah mengubah `docs/tg` jalankan `php artisan docs:rw` dan `guides:import` di mockup dan tmerp.
