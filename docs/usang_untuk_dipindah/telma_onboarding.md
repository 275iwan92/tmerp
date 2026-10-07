# Onboarding Telma — Modul Inventory & Delivery (kerja paralel)

> Dibuat 2026-08-28 oleh sesi Claude Code yang mengerjakan modul Production
> (PPIC/MPS), untuk menyiapkan Telma bekerja **paralel** di modul Inventory &
> Delivery/Dispatch dengan Claude Code-nya sendiri, tanpa saling timpa kerja.

---

## 1. Kenapa dokumen ini ada

Kita berdua jalankan Claude Code di waktu yang sama, di repo yang sama, tapi
di **branch dan (idealnya) database lokal yang terpisah**:

| | Saya (Production/PPIC-MPS) | Telma (Inventory & Delivery) |
|---|---|---|
| Branch | `claude/bentuk-customer-order-clarity-jnehjg` | `claude/inventory-delivery-telma` (sudah dibuat, tinggal `git pull`) |
| Fokus modul | `tm2prd` (Production Scheduling) | `tm2inv` (Inventory) + Dispatch/DO (kemungkinan tabel baru, lihat §4) |
| Database lokal | MySQL lokal saya | **Wajib MySQL lokal sendiri** (§2) — jangan connect ke server saya |

Alasan wajib database terpisah: migration & dummy data kita berpotensi
bentrok di tabel *shared* lintas modul (`tm2sys.sys_app_menu`, `sec_group`,
`sec_group_permission`, `tm2mst.*`) kalau jalan bersamaan di 1 instance MySQL
yang sama — race condition di auto-increment ID, migration timestamp
collision, dsb. Kerja di DB terpisah, nanti hasilnya digabung lewat
**migration files** saat branch di-merge (bukan lewat sinkronisasi DB
langsung).

---

## 2. Setup awal (sekali saja)

```bash
git clone https://github.com/275iwan92/tmerp.git
cd tmerp
git checkout claude/inventory-delivery-telma
git pull origin claude/inventory-delivery-telma

composer install
npm install
npm run build

cp .env.example .env   # kalau belum ada .env
php artisan key:generate
```

### 2.1 Siapkan MySQL lokal sendiri

Bikin user & 10 database kosong di MySQL lokal Telma sendiri (nama database
harus persis sama, ini yang dibaca `config/database.php`):

```sql
CREATE USER 'tm2user'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_SENDIRI';
CREATE DATABASE tm2sys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2mst CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2sdi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2pur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2inv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2prd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2acc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2hrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2hrr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tm2pyr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON tm2sys.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2mst.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2sdi.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2pur.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2inv.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2prd.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2acc.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2hrm.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2hrr.* TO 'tm2user'@'localhost';
GRANT ALL PRIVILEGES ON tm2pyr.* TO 'tm2user'@'localhost';
FLUSH PRIVILEGES;
```

> ⚠️ **Jangan pakai password `^yh7uj8iK`** — itu password lama yang sempat
> ke-commit ke git history (`.claude/settings.json`, sudah dibersihkan
> 2026-08-28, tapi tetap ada di riwayat commit lama). Pakai password baru
> untuk MySQL lokal Telma sendiri.

Isi `.env` (`DB_PASSWORD` = password yang baru dibuat di atas):

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=tm2user
DB_PASSWORD=<password_baru_telma>
DB_SYS=tm2sys
DB_MST=tm2mst
DB_SDI=tm2sdi
DB_PUR=tm2pur
DB_INV=tm2inv
DB_PRD=tm2prd
DB_ACC=tm2acc
DB_HRM=tm2hrm
DB_HRR=tm2hrr
DB_PYR=tm2pyr
```

### 2.2 Restore dump per-database (docs/tm2*.sql)

Dump di `docs/tm2*.sql` sudah di-*refresh* 2026-08-28 (state MySQL lokal saya
hari ini, termasuk seluruh kerja Production/PPIC-MPS + backlog modul lain).
Restore semuanya supaya Telma mulai dari titik yang sama:

```bash
mysql -utm2user -p tm2sys < docs/tm2sys.sql
mysql -utm2user -p tm2mst < docs/tm2mst.sql
mysql -utm2user -p tm2sdi < docs/tm2sdi.sql
mysql -utm2user -p tm2pur < docs/tm2pur.sql
mysql -utm2user -p tm2inv < docs/tm2inv.sql
mysql -utm2user -p tm2prd < docs/tm2prd.sql
mysql -utm2user -p tm2acc < docs/tm2acc.sql
mysql -utm2user -p tm2hrm < docs/tm2hrm.sql
mysql -utm2user -p tm2hrr < docs/tm2hrr.sql
mysql -utm2user -p tm2pyr < docs/tm2pyr.sql
```

`tm2mst.sql` ~28MB (raw material + customer dummy data asli, bukan salah
generate) — normal, tunggu saja.

### 2.3 Verifikasi

```bash
php artisan migrate:status   # harus SEMUA "Ran", jangan migrate ulang dari dump
php artisan test             # baseline: 37/38 lulus, 1 gagal pra-eksisting
                              # (MainMenuShellSmokeTest::test_sales_staff_sees_only_permitted_modules,
                              #  tak terkait modul manapun, sudah begitu sejak sebelum sesi ini)
```

---

## 3. Scope kerja: Inventory & Delivery

### 3.1 Inventory (`tm2inv`) — sebagian besar SUDAH live

Cek dulu status nyata sebelum menganggap sesuatu belum ada:
- **Sudah live**: Master Stok & Kartu Stok (switch RM/FG/SFG via `v_inv_movement`),
  Stock In, Stock Out, Stock Return, Stock Adjustment + Approval — semua di
  `resources/views/pages/inventory/*.blade.php`.
- **Belum dibangun** (dari catatan sesi lalu): Input Inventory yang benar-benar
  membedakan 3 alur/kelas stok secara utuh, dan Purchase Receive 3 alur
  (PO biasa / Customer punya stok khusus / Subcon → SFG) — lihat
  `docs/inventory_tier1.md` §7 dan trigger legacy `confirm->Stock IN` yang
  ditemukan di dump lama tapi belum di-port ke PHP Service.
- Baca `docs/inventory_tier1.md` s/d `tier4.md` dulu sebelum mulai — jangan
  tebak kolom, DDL sudah lengkap.

### 3.2 Delivery / Dispatch (DO/Surat Jalan) — SPEC-ONLY, belum ada kode sama sekali

- `docs/dispatch_tier1.md` s/d `tier4.md` + `dispatch_rules.yaml` +
  `dispatch_state.yaml` sudah lengkap ditulis (dibaca sesi 2026-08-28, murni
  spesifikasi — **belum ada DDL migration, model, atau layar apa pun**).
- Poin kunci dari spec yang sudah digali (jangan re-derive dari nol,
  langsung baca tier docs untuk detail lengkap): DO + Surat Jalan itu **1
  tabel, 2 print** (bukan 2 dokumen terpisah), aturan *no-merge* dikunci ke
  `co_order_item_id` (1 baris CO item tak boleh kepecah/gabung diam-diam ke
  DO berbeda tanpa jejak).
- Modul ini yang menyambungkan **Sales (CO) → Inventory (Stock Out FG) →
  dokumen kirim fisik** — perhatikan CLAUDE.md §2 (FK lintas-DB dilarang,
  relasi via Eloquent relationship/VIEW, bukan `->join()`) karena Dispatch
  akan banyak menyentuh `tm2sdi` (CO) sekaligus `tm2inv` (stok FG keluar).

### 3.3 Yang BUKAN scope Telma (biar tak dobel kerja dengan Production)

- `tm2prd` (Production Scheduling/PPIC-MPS) — itu punya saya, sedang jalan
  paralel. Kalau Dispatch/Inventory butuh data dari WO (`prd_wo`), akses
  read-only via Eloquent relationship logis (query terpisah), **jangan ubah
  skema `tm2prd`** dari branch Telma — kalau ternyata perlu, catat sebagai
  "delta requirement" (pola sama yang sudah dipakai di project ini untuk
  temuan cross-modul) dan didiskusikan dulu sebelum eksekusi.

---

## 4. Aturan main biar tidak bentrok pas merge

1. **Selalu kerja di branch `claude/inventory-delivery-telma`** — jangan
   `checkout` ke branch Production saya, jangan `push` langsung ke `main`.
2. **Migration**: pakai prefix tanggal hari ini + urutan `_0000XX` (lihat
   pola nama file di `database/migrations/` yang sudah ada). Kalau nama file
   migration Telma & saya kebetulan sama persis (tanggal+nomor sama), itu
   akan ketahuan pas merge (conflict jelas di nama file) — bukan silent bug,
   aman.
3. **Tabel *shared*** (`tm2sys.sys_app_menu`, `sec_group`,
   `sec_group_permission`, apa pun di `tm2mst`) — kalau migration Telma
   nambah row baru di situ (mis. menu baru untuk Dispatch), itu textbook
   aman digabung (migration = kode, bukan state DB yang di-share real-time).
   Yang TIDAK aman adalah kalau kita berdua run migration bersamaan ke
   **instance MySQL yang sama** — makanya §2 wajib DB lokal terpisah.
4. **Commit & push** boleh kapan saja ke branch masing-masing — tidak perlu
   koordinasi real-time. Merge ke `main` (atau gabung 2 branch) dibicarakan
   manual nanti, bukan otomatis.
5. Ikuti `griduistandard.md` (grid) dan `FORM_UI_STANDARD.md` (form entry) —
   itu standar UI wajib lintas-modul, sudah dipakai konsisten di
   Sales/Procurement/HRM/Production. Jangan bikin pola UI baru sendiri.
6. `CLAUDE.md` di root wajib dibaca — itu memori proyek utama (arsitektur
   DB, konvensi nama, prinsip desain). Semua yang berlaku untuk saya di
   Production berlaku sama persis untuk Telma di Inventory/Delivery.

---

## 5. Cara kerja yang terbukti efektif (dari sesi Production hari ini)

- **Verifikasi lebih dari `php -l`.** Syntax check doang tidak menangkap bug
  logika/data. Pola yang dipakai sesi ini: tulis service/Livewire method →
  `php artisan tinker` panggil langsung terhadap data dummy nyata → cek hasil
  di DB, bukan cuma percaya kalau "tidak error". 2 bug nyata ketahuan cuma
  lewat cara ini (bukan dari `php -l`).
- **`Livewire::test()`** untuk verifikasi Livewire component (render + call
  method + assert state DB berubah benar) — lebih murah dari buka browser
  tiap kali, dan jadi test permanen kalau ditulis ke `tests/Feature/`.
- Kalau nemu compiled-view error yang membingungkan ("unexpected token
  endforeach" dsb walau `@if`/`@foreach` kelihatan balance), baca file yang
  BENAR-BENAR ter-compile di `storage/framework/views/*.php` (bukan cuma
  source `.blade.php`) — lihat `feedback_livewire_wirekey_inline_php_gotcha`
  kalau ada `wire:key` di dalam loop yang isinya `@php` inline.

---

## 6. Kontak / eskalasi

Kalau nemu keputusan desain baru yang mempengaruhi modul lain (termasuk
Production) — jangan diputuskan sendiri, catat dulu & tanya user (pola sama
yang dipegang CLAUDE.md §7 "Bila ada keputusan desain baru, konfirmasi ke
user dulu"). Ini berlaku dua arah: saya juga akan catat kalau ada temuan
yang menyentuh scope Inventory/Delivery.
