# Serah terima untuk agen Jo: arsip dan pembersihan V1

Ditulis 6 Oktober 2026 oleh agen Iwan (sesi cloud, thread proyek). Pekerjaan **ditahan** atas permintaan Iw; Gelombang 2 belum dikerjakan. Branch: `claude/project-thread-xwjy77`, PR #10 (draft) ke `main`. Semua pekerjaan sudah di-push; pohon kerja bersih.

## 1. Tujuan dari Iw
- Semua modul V1 diarsipkan sebagai zip **di luar GitHub**, lalu V1 dikeluarkan dari repo (menu, dashboard, objek ikut).
- Layar V1 yang belum punya padanan v2 **tidak dihapus begitu saja**: v2-nya dibangun dulu, per gelombang, dan Iw menyetujui tiap langkah.
- Keputusan Iw sejauh ini: kandidat hapus yang aman saja (hasil akhirnya 12 layar), "Bangun v2 dulu" untuk 22 layar tanpa padanan v2, "Mulai Gelombang 1".

## 2. Letak arsip (PENTING: bukan di GitHub)
Folder proyek `/mnt/project-files/arsip_v1/`:
| Berkas | Isi |
|---|---|
| `tmerp_v1_2026-10-06.zip` | 400 berkas V1, diambil dari commit `adfa2ec` (tepat sebelum pembersihan) |
| `daftar_v1.csv` | Inventaris 315 baris: jenis, berkas, keterangan, kategori (masih dipakai v2 / zona beku / infra / kandidat hapus) |
| `rencana_v2_pengganti_v1.md` | Rencana 22 layar pengganti, per gelombang |
| `data_origin_v2.jpg`, `financial_statements_v2.jpg` | Mockup yang diajukan ke Iw |

Zip **tidak** dimasukkan ke repo. **Iw harus mengunduhnya dan menyimpan di laptop**; folder proyek bukan backup jangka panjang. Bila perlu memulihkan sebuah layar V1: ambil dari zip, atau `git show adfa2ec:<path>`.

## 3. Status per gelombang
| Gelombang | Status |
|---|---|
| Penghapusan aman (12 layar + 15 objek form tak terpakai) | Selesai, commit `7c89f5e` |
| 1 Setup: Identification | Selesai, `a7a0750`. Layar `v2:company-identity` (`App\Support\V2\Data\Identification`) |
| 1 Setup: Localization | Selesai, `b3deeb9`. Jadi lookup `UiTextKeyLookup` (`v2:ui-text-keys`), lihat §5 |
| 1 Setup: Data Origin | Selesai, `41e72bf`. Layar 5 tab `v2:data-origin` (`App\Support\V2\Data\DataOrigin`, lookup `DataOriginRuleLookup`, `DataRequirementLookup`); mockup `docs/mockups/data_origin_v2.jpg` disetujui Iw |
| 2 Finance (9 layar) | **Belum dibangun.** Mockup pertama `docs/mockups/financial_statements_v2.jpg` (1 layar bertab menggantikan Balance Sheet, Income Statement, Cash Flow, Financial Statements) **menunggu persetujuan Iw** (kartu keputusan terbuka di thread) |
| 3 HRM (6 layar) | Belum dimulai |
| Ditunda | MPS Kanban dan Spreadsheet (menunggu redesain MPS), laporan `coming-soon` |

Detail Gelombang 2 dan 3 ada di `rencana_v2_pengganti_v1.md`. Bahan untuk Finance: service sudah ada (`LedgerService::balanceSheet|incomeStatement|cashFlow`, `BankReconService`, `CloseService`, `TaxEngine`, `FinanceMetrics`); halaman V1-nya di `resources/views/pages/finance/*`. Pola laporan v2: `App\Support\V2\Grid\Acc\TrialBalanceReport`.

## 4. Layar V1 yang sudah dihapus
Halaman: `setup/{language,identification,localization,data-origin}`, `master/{holiday,produk}`, `recruitment/sync-log`, `inventory/stock-adjustment-approval`, `pur/{po,pr,prt}/approval`, `pur/pq/{entry,index,print}`, `production/mts-forecast`. Route di `routes/web.php` dan entri `MenuRoutes` ikut dihapus.
Objek: `calendar/board` dan 14 `form/*` (case-toggle-input, currency-input, date-range, decimal-input, masked-input, mini-calendar, number-stepper, otp-input, password-input, phone-input, radio-group, range-slider, split-button, star-rating), plus barisnya di `_manifest.yaml`.
Test: dua test yang menggerakkan layar terhapus dibuang (`/setup/language`, `test_mts_forecast_entry_computes_accuracy`) dan baris smoke `/setup/localization`. Baseline `UiRatchetTest` diturunkan (sekarang raw_button 899, hex_color 5136, hand_modal 63, raw_table 220, inline_style_block 17).

## 5. Yang BELUM dihapus dan alasannya
Daftar penuh dan kategorinya ada di `daftar_v1.csv`.
- **68 layar masih dipakai v2** lewat `v1Route()` / `v1Component()` di grid v2 (form, simpan, toggle). Hapus hanya setelah grid v2-nya berdiri sendiri.
- **17 layar zona beku** (Inquiry, ProDev, ProCost): jangan disentuh tanpa izin eksplisit Iw (CLAUDE.md §6B).
- **Infrastruktur / publik**: login, register, ganti password, security setup, portal kandidat `careers.*` (12 berkas).
- **Menu v1 tetap**: `routes/web.php` mengarahkan ke `/menu` bila `APP_UI` bukan v2, dan `App\Support\V2\Data\Account` memakai komponen Shell. `MenuRoutes::map()` masih dibaca Menu v2 (145 dari 179 kode menunjuk layar v1).
- **Halaman laporan** tetap karena login mengarahkan Direksi ke `report.executive`.
- **Filament** (`AdminPanelProvider`) tidak dihapus; belum diputuskan.
- **`sys_translations` (Localization)**: rencana awal "dipensiunkan" ternyata tidak bisa, karena `Translator::t`, login/register dan label menu ber-`trans_key` masih membacanya (sekitar 40 halaman v1). Penggantinya lookup UI Text Keys; baru bisa dipensiunkan setelah v1 yang membacanya habis.

## 6. Keputusan Iw yang perlu dipegang
- Filament **ditinggalkan** (sudah CLAUDE.md §6): jangan bangun atau pindahkan modul ke Filament.
- **Riwayat git dibiarkan**; berkas V1 tidak dibersihkan dari histori. Menulis ulang riwayat (force-push) butuh persetujuan eksplisit terpisah; jangan lakukan tanpa itu. Kartu pertanyaannya sempat dikirim; jawaban Iw belum tercatat sebagai keputusan, jadi anggap "dibiarkan".
- Data Origin dibuat sebagai layar sendiri (bukan digabung ke Dummy Data Reset).
- Aturan kerja Iw: sampaikan rencana dan **minta konfirmasi sebelum membangun atau menghapus**; layar yang bentuknya tak baku dibuatkan mockup JPEG dulu (skill `tmerp-ui-mockup`); objek dibuat di DevLib (turunan `DevlibComponent`), layar v2 hanya memakai; jawaban ke Iw dalam Bahasa Indonesia; label UI dan database English.
- Jangan tulis ke `tm2*` saat kerja UAT/mockup; jangan set status menu `LOCKED`; tiap commit memuat `Agent: Iwan` atau `Agent: Jo` di atas `Co-Authored-By`.

## 7. Belum teruji: Iw perlu menjalankan di laptop
Container cloud hanya punya PHP 8.3 (lock file butuh 8.4+) dan tanpa MySQL, jadi **tidak ada test yang pernah dijalankan** untuk pekerjaan ini; hanya `php -l` dan hitungan ratchet lewat skrip. Urutan:
1. `php artisan migrate --env=mockup` (migrasi menu `2026_10_06_000010`, `000020`, `000030`)
2. `php artisan test` (waspadai `UiRatchetTest`, `DevlibOnlyRatchetTest`, `MultiLanguageRatchetTest`, `HelpContentTest`, `MainMenuShellSmokeTest`)
3. `php artisan build:catalog --env=mockup` (perbarui `resources/data/v2/build.json`, lalu commit)
4. Buka di mockup.test: Setup > Identification, Localization, Data Origin dan cek tampilan. Layar v2 baru **belum pernah dirender**; kemungkinan ada galat Blade atau prop DevLib yang perlu dibetulkan.
Bila ada yang gagal: perbaiki di branch PR #10 sebelum PR dibuka dari draft.

## 8. Status PR #10
Draft, 7 commit di atas `adfa2ec`, tanpa CI (tidak ada `.github/workflows`). Deskripsi PR sudah diperbarui. Catatan: `docs/globalgitbranch.md` menetapkan satu jalur tanpa cabang, sedangkan pekerjaan ini lewat branch karena sesi cloud memang bekerja di branch. Gabungkan ke jalur utama sesuai kebijakan itu setelah Iw puas.

## 9. Langkah berikut yang disarankan
1. Tunggu Iw mengunggah dan menyimpan zip, menjalankan §7, dan menjawab persetujuan mockup Financial Statements.
2. Setelah disetujui: bangun Financial Statements (grid laporan bertab + print), hapus 4 layar V1-nya, arahkan menu `ACC_FS|ACC_BS|ACC_PL|ACC_CF` ke `v2:<slug>` lewat migration, turunkan baseline ratchet.
3. Lanjut layar Finance berikutnya satu per satu (mockup dulu untuk Bank & Cash dan Period Closing), lalu Gelombang 3.
