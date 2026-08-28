# Laporan Top Management — Konsep & Cakupan (Tier 1)

> Status: **PERSIAPAN / BELUM DIGARAP.** Dokumen ini catatan rencana (keputusan
> user 2026-08-27: "yang penting tulis dulu md file bahwa nanti harus ada
> report buat top management"), bukan spec final siap-implementasi. Banyak
> keputusan desain di §5 masih terbuka — konfirmasi ke user dulu sebelum mulai
> membangun layar sungguhan.

---

## 1. Tujuan & Audiens

Kumpulan laporan untuk **Direksi/Manajemen Puncak** (bukan operator harian) —
beda kebutuhan dari grid transaksi/worklist yang sudah ada (griduistandard.md):
operator butuh **daftar & aksi** per baris, direksi butuh **ringkasan & tren**
dulu, baru turun ke detail kalau perlu.

## 2. Prinsip Desain — Summary → Detail

Setiap laporan di sini **WAJIB** mengikuti pola 2 lapis:

1. **Lapis Summary** — mirip pivot table Excel: baris/kolom = dimensi
   (mis. per bulan, per customer, per departemen), sel = ukuran teragregasi
   (SUM/COUNT/AVG). Didampingi **grafik pivot** (bar/line/heatmap — bentuknya
   ikut sifat datanya, bukan satu jenis grafik dipaksa untuk semua).
2. **Lapis Detail (drill-down)** — klik satu sel/batang grafik di Summary →
   turun ke daftar baris transaksi asli di balik angka itu (mirip cube OLAP:
   "slice" pilih dimensi, "drill" turun ke row-level).

Pola ini **infrastruktur bersama** dengan item checklist lain yang masih
tertunda — *custom view/sort/group-by* di grid (lihat catatan backlog user
2026-08-27) kemungkinan jadi fondasi teknis lapis Summary di sini, bukan dua
hal terpisah. Baru diputuskan detail teknisnya saat mulai digarap.

## 3. Kerangka Tiap Laporan (dipakai konsisten di §4)

| Field | Isi |
|---|---|
| **Tujuan** | Pertanyaan bisnis apa yang dijawab laporan ini |
| **Dimensi (pivot)** | Sumbu baris/kolom — biasanya waktu × satu dimensi bisnis |
| **Ukuran (measures)** | Angka yang diagregasi |
| **Sumber data** | Tabel/VIEW yang sudah ada (atau belum) di database |
| **Grafik** | Jenis grafik pendamping summary |
| **Drill-down ke** | Layar/tabel detail tujuan saat sel diklik |

## 4. Katalog Laporan (per domain — cakupan disetujui user 2026-08-27: SEMUA)

### 4.1 Sales & Margin

| Laporan | Tujuan | Dimensi | Ukuran | Sumber data | Grafik |
|---|---|---|---|---|---|
| Corong Inquiry→Quotation | Berapa banyak inquiry berubah jadi quotation, di tahap mana macet | bulan × sub_state/stat_confirm | count inquiry, conversion rate | `tm2sdi.iqp_inquiry` | Funnel/bar |
| Margin per Produk/Customer | Produk/customer mana paling untung | bulan × customer atau product_draft_name | HPP vs harga jual (dari Costing Hub) | `tm2sdi.iqp_costing`, `iqp_costing_detail` | Bar + %margin overlay |
| Win-rate Sales | Performa PIC sales | bulan × pic_sales | count won/lost, nilai quotation | `tm2sdi.iqp_inquiry` (butuh field hasil menang/kalah — **belum ada**, lihat §5) | Bar |

> Laporan "pipeline Customer Order" **belum bisa dibangun** — `co_order`/`co_line`
> masih status "diusulkan" (prefixlist.md §B), CO belum ada layar sama sekali
> (lihat item checklist user 2026-08-27 #3, terpisah dari dokumen ini).

### 4.2 Procurement & Inventory

| Laporan | Tujuan | Dimensi | Ukuran | Sumber data | Grafik |
|---|---|---|---|---|---|
| Spend per Supplier/Kategori | Kemana uang belanja pergi | bulan × supplier atau mat_category | total nilai PO/PV | `tm2pur.po_order`, `pv_receipt` | Bar/treemap |
| Outstanding PR/PO | Berapa lama approval nyangkut | umur (bucket hari) × status | count & nilai dokumen | sudah ada versi dasar (Laporan grup Procurement) — tinggal ditambah lapis pivot | Bar bucket umur |
| Nilai & Turnover Stok | Stok RM/FG/SFG kebesaran/kekecilan | bulan × warehouse/kategori | qty_onhand, value_onhand, turnover ratio | `tm2inv.v_rm_onhand`/`v_fg_onhand`/`v_sfg_onhand` (baru diperbaiki 2026-08-27) | Line (tren) + heatmap slow-moving |
| Movement Bahan | Pola masuk-keluar per periode | bulan × stock_class | qty_in, qty_out, qty_adj | `tm2inv.v_inv_movement` (baru diperbaiki 2026-08-27) | Stacked bar |

### 4.3 Finance

| Laporan | Tujuan | Dimensi | Ukuran | Sumber data | Grafik |
|---|---|---|---|---|---|
| Posisi Kas | Saldo kas/bank real-time & tren | harian/mingguan × akun kas/bank | saldo | `tm2acc.fin_voucher` (BKM/BKK/BBM/BBK) | Line |
| Umur Piutang/Hutang (AR/AP Aging) | Piutang/hutang mana yang jatuh tempo/lewat | bucket umur (0-30/31-60/61-90/90+) × customer/supplier | nilai outstanding | `tm2acc.fin_dp_allocation`, dokumen AP terkait | Stacked bar bucket |
| Ringkasan Neraca Saldo | Kesehatan akun per kategori | akun × bulan | debit, kredit, saldo | View `v1_fin_trial_balance` (sudah ada) | Table pivot + bar per account_type |

### 4.4 HR

| Laporan | Tujuan | Dimensi | Ukuran | Sumber data | Grafik |
|---|---|---|---|---|---|
| Headcount | Komposisi karyawan | departemen × work_status/employment_type | count | `tm2mst.emp_employee`, `emp_contract` | Bar/donut |
| Kedisiplinan | Departemen/individu mana butuh perhatian | departemen × tipe (Verbal/SP1-3) | count | `tm2mst.emp_disciplinary` | Heatmap |
| Cross-check Keluarga Sesama Karyawan | Potensi nepotisme yang perlu direview | — (daftar, bukan pivot) | count notifikasi belum direview | `tm2sys.sys_notifications` (`family_cross_check`, baru dibangun 2026-08-27) | Daftar, bukan grafik |

## 5. Terbuka / Belum Diputuskan

Poin ini **HARUS** dikonfirmasi user sebelum mulai membangun layar sungguhan:

1. **Lokasi di menu**: grup sidebar baru "Laporan Eksekutif" (top-level,
   lintas modul) atau tetap tersebar di tiap modul dengan badge "Eksekutif"?
2. **Kontrol akses**: siapa yang boleh lihat (Direksi saja? Level manajer ke
   atas?) — perlu `min_mgmt_level` khusus per griduistandard.md pola yang
   sudah ada di `sec_module`, atau modul akses baru.
3. **Engine pivot**: dibangun generik (1 komponen Livewire dipakai ulang tiap
   laporan, dimensi/ukuran dikonfigurasi) atau layar custom per laporan?
   Terkait langsung ke item checklist "grid custom view/sort/group-by" —
   sebaiknya didesain bersama, bukan dua proyek terpisah yang tumpang tindih.
4. **Grafik**: library apa (Chart.js? ApexCharts? native SVG?) — belum ada
   preseden grafik di project ini sejauh sesi ini berjalan.
5. **Win-rate Sales** butuh kolom baru di `iqp_inquiry` (status
   menang/kalah eksplisit) yang belum ada di skema — perlu keputusan desain
   tersendiri, bukan cuma laporan.
6. **Refresh data**: real-time (query langsung tiap buka) atau snapshot
   periodik (materialized/cache, lebih cepat tapi bisa basi)? Volume data
   masih kecil (ratusan-ribuan baris) jadi real-time kemungkinan cukup untuk
   sekarang — tapi perlu keputusan eksplisit, bukan asumsi.

## 6. Non-Goals (sengaja di luar cakupan dokumen ini)

- Bukan spec Customer Order manual input / Product Costing manual input
  (checklist user 2026-08-27 #3) — itu fitur transaksi, bukan laporan.
- Bukan spec "grid custom view/sort/group-by" (#1) — meski salah satu opsi
  di §5.3 menyarankan keduanya dibangun bersama, keputusan arsitektur itu
  sendiri di luar cakupan dokumen ini.
