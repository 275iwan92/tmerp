# TIER 2 — DISPATCH · BUSINESS LOGIC & ALGORITMA
**PT. Telasih Makmur** · DB `tm2sdi`

| | |
|---|---|
| **Basis** | `dispatch_tier1.md` |
| **Cakupan** | §A Picker logic · §B No-merge rule (inti) · §C Status lifecycle · §D Validasi & kasus khusus · §E Price override · §F Cetak · §G State-form-button |

---

## A. CO LINE PICKER — logika tarik baris dari CO ke DO

### A.1 Trigger & prasyarat
Picker (Block C di layar Entry) **hanya aktif setelah `customer_id` di header DO terisi**. Sebelum itu, panel picker kosong/disabled dengan pesan "Pilih customer terlebih dahulu".

### A.2 Query sumber data
Picker menampilkan hasil VIEW `v_co_delivery_outstanding` (Tier 3 §4) difilter:
```
WHERE customer_id = :header_customer_id
  AND qty_available > 0
  AND co_status = 'CONFIRMED'
ORDER BY co_no, item_description
```
- `co_status = 'CONFIRMED'` — baris dari CO yang masih `DRAFT` **tidak pernah muncul** di picker (lihat Tier 1 §5.1: CO draft bisa berubah harga kapan saja).
- `qty_available` dihitung live (bukan kolom fisik) = `co_order_delivery.ship_qty - SUM(do_line.qty_do WHERE do_line.co_delivery_id = co_order_delivery.id_delivery AND do_line.status IN ('PLANNED','SHIPPED'))`. Baris `do_line` berstatus `CANCELLED` **tidak** ikut dihitung — otomatis melepas kembali qty ke pool tersedia.
- Baris yang `qty_available = 0` (sudah terserap penuh oleh DO lain, baik yang masih `PLANNED` maupun sudah `SHIPPED`) **hilang dari picker**, bukan ditampilkan disabled.

### A.3 Aksi "Tarik ke Delivery Order"
Input: array `co_delivery_id` yang dicentang user (multi-select, boleh lintas-CO, TIDAK boleh lintas-customer — dijamin karena picker sudah difilter per `customer_id`).

Untuk **setiap** `co_delivery_id` terpilih, sistem membuat **satu** `do_line` baru (1:1 pull → baris, tidak pernah digabung — lihat §B):
```
do_line.delivery_id        = <DO header aktif, harus berstatus DRAFT>
do_line.co_order_id        = co_order_delivery.order_id (via co_order_item)
do_line.co_order_item_id   = co_order_delivery.item_id
do_line.co_delivery_id     = co_order_delivery.id_delivery
do_line.item_description   = co_order_item.description   -- SNAPSHOT saat ditarik
do_line.qty_unit           = co_order_item.qty_unit       -- SNAPSHOT
do_line.unit_price         = co_order_item.unit_price     -- SNAPSHOT
do_line.qty_do             = LEAST(qty_available, co_order_delivery.ship_qty)  -- default penuh, boleh diedit turun oleh user SEBELUM Confirm
do_line.status              = 'PLANNED'
do_line.price_overridden   = 0
```
Baris yang sudah ditarik **hilang dari picker** pada render berikutnya (karena `qty_available` sudah berkurang; kalau berkurang jadi tepat 0, otomatis tidak muncul lagi — kalau berkurang tapi masih > 0, tetap muncul dengan sisa yang lebih kecil, mendukung penarikan bertahap ke DO lain).

### A.4 Guard baris duplikat
Sebelum insert, cek: `do_line` dengan `delivery_id` (DO aktif ini) + `co_delivery_id` yang sama **belum ada**. Kalau user mencoba menarik baris yang sudah ada di DO yang sama (mis. klik ganda), **tolak** — bukan menambah qty ke baris existing (itu sudah masuk kategori merge, dilarang oleh §B).

---

## B. NO-MERGE RULE — kunci identitas baris DO (INTI KEPUTUSAN)

### B.1 Aturan
**Kunci identitas satu baris `do_line` adalah `co_order_item_id`, bukan `item_id`/nama produk.** Setiap `co_order_item_id` berbeda yang ditarik ke DO **selalu** menjadi baris `do_line` tersendiri — apa pun isi `item_description`-nya, sekalipun identik di dua baris berbeda.

**Sistem TIDAK PERNAH melakukan operasi berikut, dalam kondisi apa pun:**
- Auto-merge dua baris `do_line` menjadi satu berdasarkan kesamaan nama item.
- Menjumlahkan `qty_do` dua baris dengan `co_order_item_id` berbeda ke satu baris.
- Merata-ratakan atau memilih salah satu `unit_price` saat dua baris punya nama item sama.
- Menyediakan tombol "Gabung Baris" di UI manapun pada v1.

### B.2 Kenapa (alasan locked, jangan didebat ulang oleh Claude Code)
Harga di `do_line.unit_price` adalah **snapshot** dari `co_order_item.unit_price`, bukan hasil kalkulasi ulang. Menggabung dua baris dengan `co_order_item_id` berbeda berarti salah satu jejak harga-ke-CO-asal **hilang secara permanen** — melanggar prinsip audit trail yang mendasari seluruh desain modul ini (Tier 1 §3.2). Ini konsisten dengan semua ERP besar yang diriset (SAP: pricing tidak di-replikasi ulang di delivery; Oracle/D365/Odoo: link ke order line selalu di level baris, bukan di-dedupe berdasar nama produk).

### B.3 Yang BOLEH dilakukan sistem (batasnya)
- **Badge informatif** di UI: kalau dua atau lebih baris `do_line` dalam satu DO punya `item_description` identik TAPI `unit_price` berbeda, tampilkan badge kecil "⚠ item sama, harga beda — baris tidak digabung" di sebelah nama item (murni UI, tidak mengubah data). Referensi implementasi visual: `mockup_delivery_order.html` fungsi `renderDoLines()`.
- **Grouping di cetakan** (kalau suatu saat diminta): boleh menampilkan sub-total per nama item di footer cetak, TAPI baris detail tetap tercetak terpisah per `co_order_item_id` di atasnya. Ini murni presentasi, tidak menyentuh tabel `do_line`.

### B.4 Test case wajib (acceptance criteria untuk Claude Code)
1. Tarik baris "Box Karton 5-Ply RSC" dari CO2600011 (harga Rp12.000) DAN dari CO2600014 (harga Rp13.500) ke satu DO yang sama → hasil **2 baris `do_line`**, masing-masing `co_order_item_id` berbeda, `unit_price` masing-masing tetap 12000 dan 13500, TIDAK ada baris gabungan qty 800 dengan harga tunggal.
2. Query total nilai DO = `SUM(qty_do * unit_price)` per baris, dijumlah — bukan `SUM(qty_do) * harga_rata_rata`.
3. Hapus salah satu dari dua baris tersebut → baris yang tersisa TIDAK berubah harga/qty-nya.

---

## C. STATUS LIFECYCLE & TRANSISI

```
DRAFT ──Confirm──> CONFIRMED ──Tandai Terkirim──> SHIPPED
  │                    │
  └──Batalkan──> CANCELLED <──Batalkan──┘
```

### C.1 DRAFT (status awal)
- Header & baris **full-edit**: tambah baris via picker (§A), hapus baris, ubah `qty_do` (dalam batas `qty_available` + `qty_do` baris itu sendiri — lihat §D.2), ubah `unit_price` manual (→ trigger §E).
- Tombol tersedia: **Simpan Draft**, **Confirm** (aktif hanya kalau ≥1 baris & semua baris valid), **Batalkan**.
- `do_no` **belum digenerate** — tampil placeholder "(auto saat Confirm)" sesuai mockup.

### C.2 DRAFT → CONFIRMED (aksi "Confirm Delivery Order")
**Syarat (server-side, wajib re-validasi, jangan percaya state client):**
1. Minimal 1 `do_line`.
2. Setiap `do_line.qty_do > 0`.
3. **Re-cek** `qty_do` tiap baris ≤ `qty_available` versi TERKINI dari `v_co_delivery_outstanding` (mencegah race condition: dua user menarik baris jadwal yang sama dari dua DO berbeda secara bersamaan — DO kedua yang Confirm harus gagal kalau qty sudah terserap DO pertama).
4. Semua `co_order_id` yang direferensikan baris-baris DO ini masih berstatus `CONFIRMED` di CO (belum dibatalkan sejak ditarik).

**Efek:**
- Generate `do_no` (format `DO/YY/#####`, pola global — lihat Tier 3 §5).
- `status` → `CONFIRMED`. Header (`customer_id`, `ship_address_*`) dan semua baris (`co_order_item_id`, `qty_do`, `unit_price`) **terkunci** (read-only), kecuali transisi status berikutnya.
- Tombol berubah: **Tandai Terkirim**, **Batalkan** (masih boleh, sebelum Shipped).

### C.3 CONFIRMED → SHIPPED (aksi "Tandai Terkirim")
Satu aksi tunggal men-transisikan **seluruh baris DO sekaligus** (Tier 1 §3.3 — satu DO = satu shipping event):
```
1. UPDATE do_line SET status='SHIPPED' WHERE delivery_id = :id AND status='PLANNED'
2. UPDATE do_delivery SET status='SHIPPED', actual_ship_date=NOW() WHERE id_delivery = :id
3. Untuk setiap co_delivery_id unik yang direferensikan baris-baris DO ini:
     IF SUM(do_line.qty_do WHERE co_delivery_id=X AND status='SHIPPED') >= co_order_delivery.ship_qty
       THEN UPDATE co_order_delivery SET status='SHIPPED' WHERE id_delivery=X
     -- kalau belum >= (baris ini baru sebagian dari jadwal), co_order_delivery TETAP 'PLANNED',
     --  sisa qty_available otomatis berkurang (dihitung live, lihat §A.2) dan masih bisa ditarik ke DO lain
```
Seluruh langkah dalam **satu DB transaction**. Tidak ada rollback parsial.

**Di luar scope Tier ini** (dicatat, jangan diimplementasikan tanpa keputusan lanjutan): pemotongan stok FG. Lihat Tier 1 §5.2 & `dispatch_state.yaml` open item `DISPATCH-STOCK-OUT-FG`.

### C.4 → CANCELLED (dari DRAFT atau CONFIRMED, TIDAK BISA dari SHIPPED)
- Wajib isi `notes` (alasan pembatalan).
- Semua `do_line.status` → `CANCELLED`.
- `qty_available` di `v_co_delivery_outstanding` otomatis pulih (karena baris `CANCELLED` dikecualikan dari SUM di §A.2) — tidak perlu aksi manual tambahan ke `co_order_delivery`.
- Final state — tidak ada transisi keluar dari `CANCELLED`.

---

## D. VALIDASI & KASUS KHUSUS

### D.1 Kunci customer (hard block, pola sama PV-02)
Semua `do_line` dalam satu `do_delivery` **wajib** berasal dari CO yang customer-nya (`co_order.quotation_id → quo_quotation.customer_id`) **sama** dengan `do_delivery.customer_id`. Dijamin di level query picker (§A.2 sudah difilter per customer), tapi **tetap divalidasi ulang di server saat insert `do_line`** — jangan percaya filter UI saja.

`customer_id` header **terkunci begitu baris pertama ditarik** (sama pola PV-03: kunci dokumen). Kalau user ingin ganti customer setelah ada baris, harus hapus semua baris dulu (kembali ke `qty_available`), baru boleh ganti customer.

### D.2 Edit `qty_do` turun (partial pull)
User boleh menurunkan `qty_do` di bawah `qty_available` saat menarik (mis. jadwal 500 pcs, tapi truk cuma muat 300 — sisa 200 tetap `PLANNED` di `co_order_delivery`, bisa ditarik ke DO lain kapan saja). User **tidak boleh** menaikkan `qty_do` melebihi `qty_available + qty_do baris itu sendiri` (batas atas dinamis, karena baris itu sendiri sudah "memegang" sebagian qty).

### D.3 CO dibatalkan setelah barisnya ditarik ke DO DRAFT
Kalau `co_order.status` berubah jadi selain `CONFIRMED` **setelah** barisnya sudah ada di DO `DRAFT` (skenario jarang, tapi mungkin) — DO tidak otomatis berubah, tapi **Confirm akan ditolak** (syarat C.2 poin 4). Pesan error harus eksplisit menyebut baris mana yang bermasalah, bukan generic error.

### D.4 Baris `co_order_delivery` yang sudah 100% `qty_available = 0` karena DO lain masih `DRAFT` (belum Shipped)
Baris tersebut tetap **hilang dari picker** (qty terserap, meski DO penyerapnya belum Confirm/belum Shipped) — ini "reservasi lunak". Kalau DO penyerapnya di-`CANCELLED`, qty otomatis pulih (§C.4) dan baris muncul lagi di picker DO lain.

### D.5 Retur setelah SHIPPED
**Di luar scope.** Kalau customer mengembalikan barang setelah DO `SHIPPED`, itu didesain sebagai modul terpisah (setara `PRT` vs `PV` di Procurement) — jangan improvisasi mekanisme retur di dalam Tier ini.

### D.6 Delivery gratis / sample (FREE_SAMPLE)
Procurement punya kasus `FREE_SAMPLE` (masuk stok tanpa nilai beli). Dispatch v1 **tidak** merancang skenario setara di sisi jual (kirim gratis) — kalau `unit_price = 0` muncul di `co_order_item`, tetap boleh ditarik apa adanya (harga 0 sah, bukan error), tapi tidak ada penanganan khusus.

---

## E. PRICE OVERRIDE (kolom reserved, TANPA approval di v1)

User **boleh** mengedit `unit_price` sebuah `do_line` secara manual selama status `DRAFT` (mis. diskon last-minute yang belum sempat masuk CO). Saat terjadi:
```
do_line.original_unit_price = unit_price LAMA (snapshot sebelum override, hanya diisi SEKALI — override kedua kali TIDAK menimpa original_unit_price yang sudah ada)
do_line.unit_price           = harga baru dari user
do_line.price_overridden     = 1
do_delivery.price_change_flag = 1   -- kalau minimal 1 baris overridden
```
`do_delivery.price_change_approval_status` **tetap NULL** di v1 — tidak ada blocking approval, tidak ada notifikasi ke approver. Kolom ini disiapkan murni untuk Fase 2 (approval 3-party Costing+Sales+Finance, dicatat di Tier 1 §8) — **jangan bangun UI approval apa pun untuk kolom ini sekarang.**

---

## F. CETAK — dua template, satu sumber data

| | Delivery Order (internal) | Surat Jalan (eksternal) |
|---|---|---|
| Kolom harga (`unit_price`, subtotal, total) | ✔ tampil | ✖ **tidak ada di query cetak** (Sisi B, sama pola PV) |
| Kolom No. CO per baris | ✔ tampil (referensi audit) | ✔ tampil kecil (untuk klaim/retur) |
| Kolom tanda tangan penerima | ✖ | ✔ nama, tanggal, cap |
| Kolom tanda tangan pengirim/checker gudang | opsional | ✔ |
| Mode layout | TERPUSAT | TERPUSAT |

Kedua cetakan hanya bisa diakses dari DO berstatus **`CONFIRMED` atau `SHIPPED`** (bukan `DRAFT` — belum ada `do_no`, belum final).

---

## G. STATE → FORM → BUTTON (ringkasan UI per status)

| Status | Field header/baris | Tombol tersedia |
|---|---|---|
| `DRAFT` | Full-edit | Simpan Draft · Confirm (guard §C.2) · Batalkan |
| `CONFIRMED` | Read-only | Tandai Terkirim · Batalkan · 🖨️ Cetak DO · 🖨️ Cetak Surat Jalan |
| `SHIPPED` | Read-only, badge hijau | 🖨️ Cetak DO · 🖨️ Cetak Surat Jalan *(tidak ada aksi lanjutan lain di v1)* |
| `CANCELLED` | Read-only, badge merah | *(tidak ada aksi lanjutan)* |

Grid DO List (Tier 4 §2) menampilkan kolom Aksi mengikuti `griduistandard.md` §via pola PR/PO/PV: **↗ Buka** selalu ada; **✓ Confirm** kondisional muncul hanya saat `status='DRAFT'` DAN punya ≥1 baris (guard dokumen kosong), memanggil method yang sama dengan §C.2 (bukan re-implementasi terpisah).
