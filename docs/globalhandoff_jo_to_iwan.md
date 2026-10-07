# Serah Terima Agen: Jo -> Iwan (Claude Code) — 2026-10-04

> Dibaca agen **Iwan** di awal sesi berikutnya. Laptop & aplikasi Claude Code SAMA dengan Jo, jadi
> memori lokal (`MEMORY.md`, `project_handoff_*`, `feedback_*`) tetap tersedia dan tetap berlaku —
> file ini melengkapi, bukan mengganti. Urutan baca: `CLAUDE.md` -> `docs/globaluserrules.md`
> (Changelog baris 2026-10-04) -> file ini -> `docs/LOG_JOB_DONE.md` tiga entri terakhir.

## 1. Keadaan repo saat diserahkan
- Branch kerja `main`, HEAD `d8563fe` (2026-10-04 10:24 WIB), working tree bersih, semua sudah di-push.
- File ini dibuat dari sesi cloud (tanpa MySQL/Herd): **tidak ada test atau migrate yang dijalankan di sana**.
  Verifikasi lokal = tugas pertama Iwan (bagian 3).
- Satu jalur git (`docs/globalgitbranch.md`); branch `claude/*` hanya kendaraan PR dari sesi cloud, merge lalu hapus.

## 2. Yang Jo kerjakan 2026-10-03 .. 04 (ringkas, detail di LOG_JOB_DONE + rulebook)
| Tanggal | Pekerjaan | Jejak |
|---|---|---|
| 10-03 | Kartu stok: posting bertanggal mundur dihitung ulang (`StockCardChain`, `StockPostingService`) | backlog 05.23, rulebook A.24, `StockPostingOrderTest` |
| 10-03 | Material Category = pembentuk kode item: `x-dl::code-chain`, `MaterialCategoryService`, layar v2 `md-matcat` | rulebook B.16, `matcategory_delta.md` 5-6, `MaterialCategoryChainTest` |
| 10-03 | Inventory & Warehouse terpasang di tmerp.test (menu, 6 laporan stok, gedung A10/A5/A6) | migration `2026_10_03_0000{10,20,21,30,40}`, `WarehouseBuildingSeeder` |
| 10-04 | Menu Setup = grup tetap (Security, Support & Ticket, Help and Support, Language and Localization, Dummy Data & Migration, Constants, Dashboard and Widgets, Menu, Identification, Others); grup DevLib + layar DevLib UI UX dihapus; Acting Officer -> HRM > Organization | rulebook C.18, migration `2026_10_04_0000{10,20,30,40}` |
| 10-04 | Semua bantuan hanya di Profile > Help & support (4 tab: Help Topics, User's Guide, Application's Flow, Tickets dengan lampiran); Help Center hilang dari menu utama | rulebook E.1, migration `2026_10_04_000050` |
| 10-04 | "General" = sifat akses, bukan menu: 3 laporan CO tanpa harga pindah ke Sales > Sales Order > General Report (`required_object=GENERAL`) | rulebook C.18, migration `2026_10_04_000060` |
| 10-04 | Objek DevLib baru `x-dl::side-screen` (Help & support, Edit profile): layar penuh / floating ala Windows; laci Chat, AI Assistant, Notifications dapat Maximize/Restore (`x-dl::drawer` prop `maximize`) | `SideScreenTest`, mockup `docs/mockups/side_screen_drawer.jpg` |

## 3. Tugas pertama Iwan (verifikasi, sebelum kerja baru)
```bash
git pull origin main
php artisan migrate                 # tm2*  (6 migration 2026_10_04 + 2026_10_03)
php artisan migrate --env=mockup    # ddt*
npm run build
php artisan test --filter="SideScreenTest|UiRatchetTest|DevlibOnlyRatchetTest|MultiLanguageRatchetTest|StockPostingOrderTest|MaterialCategoryChainTest"
```
Lalu buka mockup.test: Profile > Help & support (Side Screen, Float, 4 tab), Setup (10 grup), Sales > Sales Order >
General Report, HRM > Organization > Temporary Acting Officer. Laporkan ke user kalau ada yang gagal.

## 4. Menunggu user / belum dikerjakan (jangan dikerjakan diam-diam)
- **Setup > Security > Authorities** — user menyebutnya, belum mendefinisikan isinya. Tanya dulu.
- **Side Screen & dua mode laci** — baru dibangun 2026-10-04, menunggu penilaian user di browser.
- **05.23** jurnal koreksi (keputusan accounting) + hapus/bangun ulang dummy kartu stok yang salah; **05.37** layar tutup periode inventory.
- **05.24** approval Stock Transfer di tm2 (`StockTransferApprovalSeeder`) menunggu Setting Approval/Approval Levels terpasang di tm2.
- **05.39** Ink Mixing — ditunda user ("dibahas nanti"), pertanyaan T1-T5 belum dijawab.
- Material Category lanjutan: pola `code-chain` untuk Product, form Raw Material / PR ke objek baru, kode item lama di `mat_raw`; 7 penyimpangan data asal (delta 5.7) tidak diubah.
- Tiga laporan General Report belum pernah terpasang di tm2 (hanya ddt).
- Tata letak gudang tahap 1 + putaran 2 menunggu penilaian user (backlog 05 judul).

## 5. Pengingat yang sering dilanggar (dari memori feedback)
- Zona beku §6B: Inquiry Product / ProDev / ProCost — tanya sebelum menyentuh esensi/flow; ID -> EN boleh.
- Kerja UAT = `--env=mockup`, **jangan menulis ke `tm2*`**; data real mesin/proses jangan dikarang.
- Aturan baru dari user -> tambahkan ke `docs/globaluserrules.md` (bagian + Changelog) saat itu juga.
- Label English + kamus; nilai data dibungkus `raw()`; komponen baru masuk `_manifest.yaml` + katalog devlib.test.
- `npm run build` setelah ubah class Tailwind; commit + push per potongan kerja.
