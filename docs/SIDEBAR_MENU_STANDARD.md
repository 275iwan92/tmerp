# SIDEBAR_MENU_STANDARD.md — Acuan Struktur Menu Sidebar per Modul

> Ditulis 2026-08-22 setelah restrukturisasi sidebar Sales (Transaksi dipecah
> 3 sub-grup) dan Inventory (grup laporan diberi nama ringkas + label item
> disamakan dengan nama layar aslinya). **Baca file ini sebelum menambah
> menu baru ke `sys_app_menu` atau menata ulang sidebar modul mana pun.**

---

## 1. Pola wajib: selalu ada grup Input & grup Laporan

Setiap modul (`sys_app_menu.menu_type = 'module'`) yang punya lebih dari
sekadar 1-2 layar **wajib** mengelompokkan child-nya ke dalam minimal 2 grup
(`menu_type = 'group'`):

- **Grup input/transaksi** — layar tempat data dibuat/diubah (form entry,
  approval, worklist). Boleh dipecah jadi beberapa sub-grup kalau jumlahnya
  banyak & punya kategori alami (lihat contoh Sales di bawah) — TAPI setiap
  sub-grup itu tetap kategori "input", bukan campur dengan laporan.
- **Grup Laporan** — layar read-only (print, rekap, grid monitoring). Judul
  grup ini **ringkas, cukup "Laporan"** (bukan "Stok & Laporan", bukan
  "Monitoring & Laporan Penjualan", dst.) — kalau modul itu juga punya layar
  monitoring non-print yang masih masuk akal disebut laporan (grid Daftar
  Sales Order, Monitoring Order), itu tetap masuk grup "Laporan" yang sama,
  tidak perlu grup terpisah "Monitoring" lagi.

**Jangan** taruh item laporan di dalam grup transaksi atau sebaliknya —
walau cuma 1 layar, tetap kelompokkan secara eksplisit supaya polanya
konsisten begitu modul itu tumbuh (item baru tinggal masuk grup yang sudah
ada, bukan menambah grup dadakan tanpa pola).

### Contoh yang sudah diterapkan

**Inventory** (`INV`):
```
Transaksi                    <- grup input
  Stock In / Stock Out / Return from Production /
  Stock Adjustment & Opname / Persetujuan Penyesuaian
Laporan                      <- grup laporan (2026-08-22: dulu "Stok & Laporan")
  Kartu Stok                 <- INV_CARD, live
  Master Stok                <- INV_RAW, live (dulu "Stok Bahan Baku", diganti — lihat §2)
  Stok Barang Jadi           <- INV_FG, belum dibangun (placeholder abu-abu, tetap tampil)
  Riwayat Mutasi             <- INV_HIST, belum dibangun
  Periode Stok               <- INV_PERIOD, belum dibangun
```

**Sales** (`SALES`) — contoh modul dengan input dipecah beberapa sub-grup
karena kategorinya beda tahap alur (lihat [[project-sidebar-groups-collapsible]]):
```
Inquiry & Quotation          <- grup input (tahap sebelum order)
  Inquiry Product / Imposition Engine / Product Costing / Quotation
Sales Order                  <- grup input (order sudah jadi)
  Customer Order
Delivery                     <- grup input (pengiriman)
  Plan Delivery / Delivery Order
Monitoring & Laporan         <- grup laporan (belum di-generic-kan jadi "Laporan"
                                 saja — evaluasi lagi kalau modul lain sudah konsisten)
```

**Catatan konsistensi**: Sales masih pakai label "Monitoring & Laporan" (label
lama), belum diseragamkan ke "Laporan" polos seperti Inventory. Ini SENGAJA
belum disentuh saat sesi 2026-08-22 (scope waktu itu cuma Inventory) — kalau
menyentuh Sales lagi, pertimbangkan menyamakan ke "Laporan" saja untuk
konsistensi lintas modul, tapi konfirmasi ke user dulu (bukan keputusan
otomatis, karena label itu terlihat langsung oleh user tiap hari).

---

## 2. Nama item laporan = nama asli layarnya, tanpa awalan "Laporan" berulang

Karena grup sudah bertajuk "Laporan", item DI DALAMNYA jangan mengulang kata
itu lagi (`Laporan > Laporan Master Stok` itu redundan). Aturan:

- Pakai potongan nama yang sama dengan `#[Title]`/heading asli layarnya
  (bukan istilah baru yang tidak muncul di mana pun di kode) — supaya user
  yang buka menu & yang lihat judul halaman melihat nama yang sama persis.
  Contoh: layar `inventory/print/master-stock.blade.php` selalu menyebut
  dirinya "Laporan Master Stok" (title bar, header, kop cetak) → label
  sidebar cukup **"Master Stok"** (buang "Laporan" karena sudah jadi nama
  grup), BUKAN label lama "Stok Bahan Baku" yang tidak match nama layarnya
  sama sekali dan menyesatkan (kesannya snapshot RM doang, padahal ini
  rekap periodik lintas item begitu SFG/FG live — lihat komentar migration
  `2026_08_22_000004_rename_inventory_report_menu_labels.php`).
- Kalau namanya sudah istilah baku industri (mis. **"Kartu Stok"** / stock
  card — sudah dipahami universal), tidak perlu diubah meski secara teknis
  tidak persis sama dengan sesuatu di kode.
- Kalau ragu nama sidebar sudah pas atau belum, cek dulu `#[Title]` &
  heading di file layarnya — itu sumber kebenaran, jangan menebak dari nama
  kode/kolom.

---

## 3. Grup sidebar bisa diciutkan (built-in, generik semua modul)

Sejak 2026-08-22, SETIAP caption grup di sidebar (`.side-cap`) otomatis jadi
tombol yang bisa diklik untuk ciutkan/buka (Alpine `x-show` + chevron) — lihat
`resources/views/pages/main-menu.blade.php` (loop `sidebarGroups`) dan
[[project-sidebar-groups-collapsible]]. Ini BUKAN sesuatu yang perlu
diaktifkan per modul — begitu sebuah grup mendaftar di `sys_app_menu`, dia
otomatis collapsible. Tidak perlu kerjaan tambahan saat menambah grup baru.

---

## 4. Checklist saat menambah/menata ulang menu modul

- [ ] Ada minimal 1 grup input (`menu_type='group'`, isinya layar create/edit/approve).
- [ ] Ada tepat 1 grup Laporan berlabel **"Laporan"** (bukan variasi lain,
      kecuali modul lama yang belum diseragamkan — lihat catatan §1).
- [ ] Label tiap item laporan cocok dengan nama asli layarnya (`#[Title]`),
      tanpa mengulang kata "Laporan".
- [ ] `sort_order` grup input < grup Laporan (grup input tampil duluan).
- [ ] Item yang belum dibangun (`status_code='NOT_BUILT'`) TETAP ditampilkan
      abu-abu sebagai placeholder roadmap — jangan disembunyikan
      (`is_active=false`) hanya karena belum jadi; itu cuma dipakai utk
      menu yang benar-benar retired/digantikan (lihat pola `INV_MOVE`/
      `INV_ADJ`/`SALES_NOTA`/`SALES_TRX` di riwayat migration).
- [ ] `sys_app_menu.id` BUKAN auto_increment — selalu `MAX(id)+1` manual
      saat insert baris baru (lihat migration mana pun sesi ini sbg contoh).
