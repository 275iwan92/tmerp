# FIX SPEC — Master Customer: Navigasi Tab Bersarang & Field Alamat Wilayah Terpadu
**TMERP · PT. Telasih Makmur · Modul Master Data**
Status: siap eksekusi Claude Code. Semua keputusan desain sudah final — jangan tanya ulang.

---

## BAGIAN A — FIX: Menu/Tab Bersarang

### A.1 Diagnosis (WAJIB dipahami sebelum ubah kode)
Screenshot bug menunjukkan: saat tab "Master Customer" aktif, klik main menu (mega menu) lagi
memunculkan **topbar + sidebar + mega menu utuh KEDUA** di dalam content area tab tersebut.
Root cause: view/komponen module (Master Customer, dst.) me-render `layouts.shell` lagi di
dalam dirinya sendiri — padahal `layouts.shell` (topbar, mega menu, sidebar, tab-bar) HARUS
cuma ada **satu instance**, di outer page.

Ini pelanggaran terhadap `GLOBAL_RULES.yaml → layouts_wajib`:
`layouts.guest (auth), layouts.shell (menu), layouts.app`.
`layouts.shell` = pemilik tunggal chrome (topbar/sidebar/tabs). Semua module view harus
extend `layouts.app` (atau langsung berupa Livewire component tanpa layout), **bukan**
`layouts.shell`.

### A.2 Kontrak Wajib (berlaku untuk SEMUA module, bukan cuma Master Customer)

1. **Satu shell, banyak tab-content.**
   `layouts.shell` render sekali: topbar + mega menu + sidebar submenu + tab-bar horizontal
   + 1 content-area kosong (`<div id="tab-content-area">` atau slot Livewire).
   Setiap module TIDAK PERNAH extend `layouts.shell`. Module = Livewire component polos yang
   di-mount ke content-area itu.

2. **Klik menu utama = 2 kemungkinan, bukan "selalu buka baru":**
   - Jika target module **belum** ada tab terbuka → buat tab baru, mount component, aktifkan.
   - Jika target module **sudah** ada tab terbuka → **fokus/aktifkan tab existing**, JANGAN
     mount ulang, JANGAN buat instance kedua.
   - State tab (radio row terpilih, panel grid/form/detail yang lagi aktif) **tetap tersimpan**
     saat pindah tab lain lalu balik — sesuai deskripsi existing "tetap terbuka meski Anda
     berpindah modul" di home page kamu. Jangan reset state saat re-fokus tab.

3. **Navigasi dalam 1 module = state-switch, BUKAN route baru.**
   Replikasi persis pola mockup `master_customer.html`:
   - 3 panel: `grid` (list) → `form` (new/edit) → `detail` (drill-down riwayat CO).
   - Pindah antar panel = toggle `class .active` pada div panel yang SAMA (via Livewire
     `$wire.activePanel = 'grid'|'form'|'detail'`), TIDAK ada full page reload, TIDAK ada
     route change, TIDAK ada nested layout.
   - Tombol "← Back to List" / "Batal" selalu kembali ke panel `grid` dalam komponen yang sama.
   - Ini pola universal: dipakai ulang untuk SEMUA master/transaksi form (Master Supplier,
     Master Produk, Inquiry, dst.) — bukan solusi khusus Master Customer.

4. **Implementasi teknis Livewire (kontrak, bukan saran):**
   - Komponen module = single-file Livewire (mis. `App\Livewire\MasterData\CustomerManager`)
     dengan property `public string $activePanel = 'grid';` dan `public ?int $selectedId = null;`.
   - Method: `showPanel(string $name)`, `selectRow(int $id)`, `openEdit()`, `openDetail()`,
     `backToList()` — 1:1 mapping dari fungsi JS di mockup (`showPanel`, `selectRow`,
     `openEdit`, `filterYear`).
   - Tab-manager (Alpine.js, di level `layouts.shell`) menyimpan daftar tab terbuka
     `{module: 'master-customer', label: 'Master Customer', wireId: '...'}` — cek existing
     sebelum push baru. Tab click → `wire:navigate` TIDAK dipakai untuk switch tab (itu untuk
     ganti halaman penuh); switch tab = toggle visibility div wrapper Livewire component yang
     sudah dimount, state Livewire tetap hidup di DOM (jangan destroy on tab-switch).

### A.3 Checklist verifikasi (Claude Code jalankan setelah fix)
- [ ] Buka Master Customer → klik "Master Data" di mega menu lagi → TIDAK ada topbar/sidebar
      kedua muncul di dalam tab.
- [ ] Klik "+ New" → isi form → klik "Back to List" → kembali ke grid, BUKAN reload module.
- [ ] Pilih row → Detail → "← Back to List" → kembali ke grid dengan row masih ter-select.
- [ ] Buka 2 module berbeda (tab A, tab B) → balik ke tab A → state grid/form terakhir A
      masih sama seperti sebelum ditinggal.

---

## BAGIAN B — Field Alamat: Autocomplete Wilayah Terpadu

### B.1 Keputusan
Field terpisah "Provinsi" (ada tapi salah, karena data provinsi bukan yang mau diisi user)
dan "Kota" (kosong) **dihapus**, diganti **1 field pencarian** "Wilayah" yang mencakup
**kelurahan + kecamatan + kota + provinsi + negara + kode pos** dalam satu ketikan — sesuai
desain `v_geo_full_address` (TIER_3A_GEO.sql), sekarang diperluas ke 5-level.

Ini sekaligus **menuntaskan 2 keputusan pending** yang dulu digantung di TIER_3A_GEO.sql:
- **PENDING #1 (4 vs 5 level) → DIPUTUSKAN: 5-level.** `geo_village` (kelurahan/desa)
  diaktifkan, tidak lagi opsional/comment-out.
- **PENDING #2 (cakupan luar negeri) → DIPUTUSKAN: tidak ada skema khusus per-negara.**
  Hierarki `country→region→city→district→village` sudah generik. Untuk negara di luar
  Indonesia, cukup isi `geo_country` + `geo_region` (state/province) + `geo_city`; kolom
  `district`/`village` dibiarkan kosong (LEFT JOIN sudah menangani ini — sama seperti kasus
  kota tanpa kecamatan). Detail alamat luar negeri (county, zip format lokal, dsb.) cukup
  masuk ke textarea "Alamat Lengkap" bebas teks — bikin skema per-negara di luar scope 10
  minggu, ROI rendah untuk basis customer yang mayoritas domestik.

### B.2 Perubahan Skema (WAJIB — sebelum koding form)

**(a) Aktivasi `geo_village`** — hapus status comment di `TIER_3A_GEO.sql`, jalankan sebagai
DDL riil (definisi tabel sudah lengkap di file, tinggal uncomment, tanpa ubah struktur).

**(b) Revisi `v_geo_full_address`** — tambah level village + kolom `postal_code` gabungan
(ambil dari level paling spesifik yang tersedia):

> **Format label ikut UI lama** (lihat screenshot referensi Jo: `"KABUPATEN Malang, JAWA
> TIMUR, INDONESIA"`) — **koma, urutan detail→umum**, BUKAN format panah `>`. Diperluas
> dengan kelurahan+kecamatan di depan (paling detail duluan, konsisten pola lama):

```sql
DROP VIEW IF EXISTS `v_geo_full_address`;
CREATE VIEW `v_geo_full_address` AS
SELECT
  v.id_village,
  d.id_district,
  c.id_city,
  r.id_region,
  ct.id_country,
  CONCAT_WS(', ',
    v.village_name,
    d.district_name,
    CONCAT_WS(' ', c.city_type, c.city_name),
    r.region_name,
    ct.country_name
  ) AS full_label,        -- "Kauman, Klojen, KOTA Malang, JAWA TIMUR, INDONESIA"
                           -- (city-only, spt contoh lama: "KABUPATEN Malang, JAWA TIMUR, INDONESIA")
  COALESCE(v.post_code, d.post_code, c.post_code) AS postal_code,  -- level tersempit menang
  ct.country_name,
  r.region_name,
  c.city_type,
  c.city_name,
  d.district_name,
  v.village_name
FROM `geo_city` c
JOIN `geo_region`  r  ON r.id_region   = c.region_id
JOIN `geo_country` ct ON ct.id_country = r.country_id
LEFT JOIN `geo_district` d ON d.city_id = c.id_city
LEFT JOIN `geo_village`  v ON v.district_id = d.id_district;
```
`CONCAT_WS` otomatis skip level yang NULL (kelurahan/kecamatan kosong ATAU data luar negeri
yang cuma sampai city) — tidak perlu `IF`/`COALESCE` manual per level seperti versi sebelumnya.

**(c) Kolom diskriminator level** — `cust_customer.default_area_id` (comment lama:
"FK -> geo_city/geo_district") **ambigu**, sekarang 3 kemungkinan tabel. Tambahkan:

```sql
ALTER TABLE `cust_customer`
  ADD COLUMN `default_area_level` ENUM('CITY','DISTRICT','VILLAGE') NULL DEFAULT NULL
    COMMENT 'Level rujukan default_area_id: CITY->geo_city.id_city | DISTRICT->geo_district.id_district | VILLAGE->geo_village.id_village'
    AFTER `default_area_id`;
```

Terapkan pola yang sama ke **semua tabel lain yang punya `*_area_id`** (mis. supplier,
alamat pengiriman/shipping address bila ada tabel terpisah) — konsisten satu aturan.

> Catatan: update `TIER_3A_GEO.sql`, `TIER_3A_PREFIX_LIST.md` (hapus tanda "*opsional" di
> `geo_village`), `TIER_3A_MASTER_inquiry.sql`, dan `SALES_STATE.yaml` untuk mencatat
> keputusan ini (lihat Bagian C).

### B.3 Komponen Livewire: `AddressPicker`
Reusable component, dipakai di Master Customer dan module lain manapun yang butuh pilih
wilayah (Supplier, Shipping Address, dst).

**Pola UI = replikasi persis versi lama (referensi: screenshot "Area Pengiriman" Jo),
bukan desain baru:**
- Trigger: kotak select tertutup (border abu, panah ▾ di kanan) — persis field select biasa.
- Klik → terbuka jadi combobox: search-input muncul **menempel di dalam** dropdown (bukan
  di field asal), auto-focus, placeholder kosong/menerima ketikan langsung.
- Di bawah search-input: daftar hasil, tiap baris **boleh wrap 2 baris** kalau `full_label`
  panjang (jangan truncate/ellipsis — data alamat harus utuh terbaca).
- Baris ter-hover/terpilih → background biru solid + teks putih (persis contoh: baris
  "KABUPATEN Malang, JAWA TIMUR, INDONESIA" highlight biru saat match teratas/aktif).
- Baris lain → putih, border tipis pemisah antar baris.
- Tutup dropdown saat klik di luar atau setelah 1 baris dipilih.

**Props masuk:**
- `wire:model` → binding ke 2 field parent: `areaId` (int) + `areaLevel`
  ('CITY'|'DISTRICT'|'VILLAGE').
- Optional `initialLabel` (string) untuk render label saat edit existing record (avoid query
  ulang saat mount).

**Behavior:**
1. Input text bebas, placeholder: `"Ketik kelurahan / kecamatan / kota / provinsi / kode pos..."`.
2. `wire:model.live.debounce.300ms` ke property `$search`. Minimal 2 karakter baru query.
3. Query ke `v_geo_full_address` — cocokkan `full_label` ATAU `postal_code` (user boleh ketik
   angka kode pos langsung untuk cari wilayah):
   ```sql
   SELECT id_village, id_district, id_city, full_label, postal_code
   FROM v_geo_full_address
   WHERE full_label LIKE CONCAT('%', ?, '%')
      OR postal_code LIKE CONCAT(?, '%')
   ORDER BY full_label
   LIMIT 15;
   ```
4. Dropdown hasil tampilkan `full_label` (mis. `Kauman, Klojen, KOTA Malang, JAWA TIMUR,
   INDONESIA`; untuk data yang belum ada kecamatan/kelurahan cukup `KABUPATEN Malang, JAWA
   TIMUR, INDONESIA` — sama seperti contoh lama) + `postal_code` kecil di sebelah kanan tiap
   baris bila ada. Baris grain lebih kasar (city-only/district-only) tetap tampil apa adanya
   (view sudah handle via LEFT JOIN berjenjang) — user boleh pilih level sekasar apa pun data
   yang tersedia (penting untuk data luar negeri yang cuma sampai city).
5. Klik 1 baris → set `areaId` + `areaLevel`, urutan prioritas dari level tersempit yang
   terisi:
   - `id_village` tidak null → `areaLevel = 'VILLAGE'`, `areaId = id_village`.
   - else `id_district` tidak null → `areaLevel = 'DISTRICT'`, `areaId = id_district`.
   - else → `areaLevel = 'CITY'`, `areaId = id_city`.
   - Tutup dropdown, tampilkan `full_label` terpilih sebagai read display (bukan input lagi,
     seperti chip/pill) + tombol "Ganti" kecil untuk buka ulang search.
6. Kode pos: TIDAK auto-lock. Setelah wilayah dipilih, prefill field Kode Pos dari
   `postal_code` hasil view (sudah COALESCE ke level tersempit), tapi field tetap editable
   manual (kode pos di level kecamatan/kota sering berupa rentang, user perlu pastikan angka
   pasti untuk kirim paket).

### B.4 Perubahan pada Form Master Customer (`master_customer.html` → Livewire)
Section "Alamat" jadi:
```
Alamat Lengkap (textarea)   — jalan, nomor, RT/RW, patokan (teks bebas, tak berubah)
Wilayah (AddressPicker)     — GANTI field "Kota/Kabupaten" yang lama
Kode Pos (input, prefill dari wilayah, editable)
```
Hapus field "Provinsi" yang berdiri sendiri — sudah terkandung dalam `full_label` hasil
AddressPicker, tidak perlu field terpisah.

### B.5 Validasi
- `areaId` + `areaLevel` wajib diisi sebelum Confirm (bukan wajib saat Save/Draft).
- Simpan ke `cust_customer.default_area_id` + `default_area_level`.

---

## BAGIAN C — Update Wajib ke SALES_STATE.yaml (ingatkan Jo, jangan lupa commit)
Tambahkan di `isu_aktif` atau `selesai.dokumen`:
```yaml
  - id: GEO-5LEVEL
    judul: "PENDING #1 (4 vs 5 level) DIPUTUSKAN: 5-level. geo_village diaktifkan (bukan opsional lagi)"
    status: DIPUTUSKAN — uncomment DDL di TIER_3A_GEO.sql, hapus tanda *opsional di TIER_3A_PREFIX_LIST.md
  - id: GEO-LUAR-NEGERI
    judul: "PENDING #2 (cakupan luar negeri) DIPUTUSKAN: tanpa skema per-negara khusus. Hierarki generik country>region>city>district>village; luar Indonesia cukup isi sampai city, district/village kosong. Detail lokal masuk textarea alamat bebas teks"
    status: DIPUTUSKAN — tak perlu integrasi dr5hn/countries-states-cities-database untuk subdivision, cukup country+region+city
  - id: SCHEMA-AREA-LEVEL
    judul: "Tambah kolom default_area_level ENUM(CITY,DISTRICT,VILLAGE) di semua tabel *_area_id (cust_customer dll) — resolve ambiguitas FK v_geo_full_address"
    status: DIPUTUSKAN — terapkan di migration berikutnya
  - id: VIEW-GEO-FULL-ADDRESS-V2
    judul: "v_geo_full_address direvisi: +id_village/village_name di full_label, +kolom postal_code (COALESCE level tersempit: village>district>city)"
    status: DIPUTUSKAN, DDL final di FIX_MASTER_CUSTOMER_NAV_ADDRESS.md Bagian B.2
  - id: COMPONENT-ADDRESS-PICKER
    judul: "AddressPicker Livewire component (autocomplete v_geo_full_address, search full_label+postal_code) — reusable lintas module"
    status: DIPUTUSKAN, spec di FIX_MASTER_CUSTOMER_NAV_ADDRESS.md
  - id: NAV-SHELL-CONTRACT
    judul: "Kontrak wajib: layouts.shell instance tunggal, module = Livewire panel-switch, tab-manager cek existing sebelum mount baru"
    status: DIPUTUSKAN — berlaku universal semua module, bukan cuma Master Customer
```
