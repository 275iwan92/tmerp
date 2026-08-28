# TIER 4 — DISPATCH · UI, MOCKUP & UAT
**PT. Telasih Makmur** · Livewire v4 + Blade + Tailwind

| | |
|---|---|
| **Basis** | `dispatch_tier1..3.md`, `mockup_delivery_order.html`, `griduistandard.md`, `FORM_UI_STANDARD.md` |
| **Cakupan** | §1 Peta layar & route · §2 DO List (grid) · §3-6 DO Entry (per blok) · §7 Cara pakai mockup → implementasi asli · §8 Skenario UAT · §9 Checklist wiring |

---

## 1. PETA LAYAR & ROUTE

| Layar | Route | Komponen Livewire |
|---|---|---|
| DO List | `dispatch.do.index` | `App\Livewire\Dispatch\DoList` |
| DO Entry (create/edit, DRAFT) & Detail (read-only, CONFIRMED/SHIPPED/CANCELLED) | `dispatch.do.entry/{doId?}` | `App\Livewire\Dispatch\DoEntry` |
| Cetak Delivery Order | `dispatch.do.print/{doId}` | `App\Livewire\Dispatch\DoPrint` (atau Blade biasa, non-Livewire, sesuai pola cetak modul lain) |
| Cetak Surat Jalan | `dispatch.do.print-surat-jalan/{doId}` | idem, query TANPA kolom harga (Tier2 §F) |

Satu komponen `DoEntry` menangani create (`doId` null) dan detail (`doId` terisi) — sama pola `co/entry.blade.php`.

---

## 2. DO LIST — grid

Ikuti `griduistandard.md` presisi (baca dulu file itu sebelum implementasi, jangan menebak dari sini saja):
- **Toolbar**: search + **"+ New Record"** — DO memang lahir dari grid ini (user klik New, masuk ke Entry kosong, pilih customer). Tidak ada filter/dropdown/checkbox.
- **Kotak statistik pastel**: Total DO / DRAFT / CONFIRMED / SHIPPED (4 kotak, palet pastel §2 `griduistandard.md`).
- **Kolom identitas**: avatar + `do_no` (atau "(Draft)" kalau belum digenerate) tebal, `customer_name` sebagai subtitle.
- **Kolom lain**: `do_date`, `line_count`, `co_count` (badge "3 CO" kalau >1, menonjolkan konsolidasi lintas-CO), `total_value`, badge `status` (palet pastel §2, dipetakan: DRAFT=abu, CONFIRMED=biru, SHIPPED=hijau, CANCELLED=merah).
- **Kolom Aksi**: **↗ Buka** selalu ada; **✓ Confirm** kondisional (`status='DRAFT'` DAN `line_count >= 1`), warna hijau, `wire:confirm` dialog, memanggil method Confirm yang SAMA dengan tombol Confirm di Entry (Tier2 §G) — jangan duplikasi logic.
- Sumber data: VIEW `v_do_list` (Tier 3 §4.2).
- Grid **master-detail expand-row** (`griduistandard.md` §9): klik chevron → tampilkan baris `do_line` inline (item, CO asal, qty, harga) tanpa pindah halaman — sama pola `co/list.blade.php`.

---

## 3. DO ENTRY — Blok A: Informasi Dokumen

Header navy sticky + tombol Back pill (`FORM_UI_STANDARD.md` §1, target `dispatch.do.index`, title "Kembali ke Daftar Delivery Order").

| Field | Sumber | Editable saat |
|---|---|---|
| No. Delivery Order | `do_no`, placeholder "(auto saat Confirm)" selama NULL | Read-only selalu |
| Tanggal DO | `do_date`, default hari ini | DRAFT |
| Rencana Kirim | `scheduled_ship_date` | DRAFT |
| Status | badge, warna sesuai Tier2 §G | Read-only selalu |

Grid 3 kolom → `class="grid three"` (**bukan** `grid grid-cols-3` mentah — `FORM_UI_STANDARD.md` §2, jebakan CSS unlayered).

---

## 4. DO ENTRY — Blok B: Customer & Ship-to

| Field | Sumber | Catatan |
|---|---|---|
| Customer | dropdown `cust_customer` (search-as-you-type kalau daftar panjang) | **Terkunci begitu baris pertama ditarik** (Tier2 §D.1) — `@disabled($this->doLines->count() > 0)`. Set `customer_id` di header, memicu re-query picker Blok C. |
| Alamat Kirim | dropdown `cust_address` milik customer terpilih, atau input manual (`ship_address_label`/`ship_address_detail`) | Terisi otomatis dari alamat default customer, boleh diubah selama DRAFT |

Grid 2 kolom → `class="grid two"`.

---

## 5. DO ENTRY — Blok C: CO Line Picker

Panel/card terpisah, HANYA aktif kalau `customer_id` sudah terisi. Query = `v_co_delivery_outstanding` difilter (Tier2 §A.2).

**Kolom tabel:** checkbox · No. CO (badge, warna beda per CO biar visually distinct — lihat §7.2) · Item · Qty Outstanding (`qty_available`) · UoM · Harga Jual/Unit · Jadwal Kirim.

**Interaksi:**
- Klik baris (bukan cuma checkbox) → toggle centang (lihat mockup `togglePick()`).
- Tombol **"↓ Tarik ke Delivery Order"** di toolbar bawah tabel, disabled kalau tidak ada baris dicentang.
- Setelah tarik: baris yang berhasil ditarik **hilang/dinonaktifkan** dari picker (server re-query `v_co_delivery_outstanding`, bukan manipulasi visual client-side seperti di mockup — mockup pakai `opacity+disable` sebagai simulasi karena tidak ada backend sungguhan).

Panel ini **hanya tampil saat status DRAFT**. Begitu CONFIRMED, Blok C hilang total dari layar (tidak relevan lagi, baris sudah final).

---

## 6. DO ENTRY — Blok D: Baris Delivery Order (hasil) & Footer

**Kolom tabel:** No. CO Asal (badge) · Item (+ badge peringatan no-merge bila relevan, Tier2 §B.3) · Qty DO (editable saat DRAFT, dalam batas Tier2 §D.2) · UoM · Harga/Unit (editable saat DRAFT → trigger price-override Tier2 §E) · Subtotal · Hapus (✕, saat DRAFT saja).

**Total bar**: Total Qty, Total Nilai — `SUM(qty_do)` dan `SUM(qty_do * unit_price)` per baris (**bukan** dihitung dari harga rata-rata, Tier2 §B.4 test case #2).

**Footer aksi** (per status, Tier2 §G):
- DRAFT: Simpan Draft · Confirm Delivery Order (disabled kalau 0 baris) · Batalkan
- CONFIRMED: Tandai Terkirim · Batalkan · 🖨️ Cetak DO · 🖨️ Cetak Surat Jalan
- SHIPPED: 🖨️ Cetak DO · 🖨️ Cetak Surat Jalan
- CANCELLED: *(tidak ada tombol aksi)*

---

## 7. CARA PAKAI `mockup_delivery_order.html` → IMPLEMENTASI ASLI

Mockup adalah **simulasi visual statis** (HTML+CSS+vanilla JS, TANPA Livewire/backend sungguhan) untuk memperlihatkan interaksi & aturan no-merge sebelum kode ditulis. Berikut pemetaan literal elemen mockup ke komponen asli, supaya Claude Code tidak perlu menebak:

### 7.1 Pemetaan blok visual → Blade/Livewire
| Elemen di mockup | Class/ID di HTML | Jadi apa di implementasi asli |
|---|---|---|
| Header navy + backpill | `<header>`, `.backpill` | `FORM_UI_STANDARD.md` §1 pola pill (mockup pakai `<div>` statis, asli pakai `<a href="{{ route('dispatch.do.index') }}">`) |
| Banner kuning skenario | `.banner` | **DIHAPUS di implementasi asli** — itu murni catatan simulasi untuk reviewer, bukan bagian UI produksi |
| Blok A Informasi Dokumen | `<div class="card">` pertama, id implisit | Tier 4 §3 di atas |
| Blok B Customer & Ship-to | `<div class="card">` kedua | Tier 4 §4 |
| Blok C CO Line Picker | `#pickerBody`, `togglePick()`, `pullToDo()` | Tier 4 §5 — di mockup data `CATALOG` di-hardcode di JS; di asli data datang dari VIEW `v_co_delivery_outstanding` via Livewire property `$this->pickerLines` |
| Blok D Baris DO | `#doBody`, `renderDoLines()`, `removeLine()` | Tier 4 §6 — di mockup state disimpan di variabel JS `doLines[]`; di asli itu adalah query `do_line` sungguhan milik `do_delivery` yang sedang dibuka |
| Badge "⚠ item sama, harga beda" | `.samewarn`, dihitung via `itemGroups` di JS | Tier2 §B.3 — logikanya **PERSIS SAMA**, pindahkan ke PHP: group `doLines` by `item_description`, tandai kalau `count(distinct unit_price) > 1` dalam grup itu |

### 7.2 Yang di mockup HANYA simulasi (jangan ditiru literal)
- **`CATALOG` object di JS** (baris `A/B/C/D` dengan data hardcode) → di asli, ini hasil query real-time ke `v_co_delivery_outstanding`, bukan data statis.
- **`picked`/`doLines` sebagai variabel JS lokal** → di asli, `doLines` adalah state Livewire (`public Collection $lines` atau setara) yang di-persist ke tabel `do_line` setiap kali berubah (atau di-buffer sampai "Simpan Draft" ditekan — **putuskan salah satu**: rekomendasi = **persist langsung per aksi** (tarik baris → langsung INSERT `do_line`; hapus baris → langsung DELETE), supaya "Simpan Draft" tidak dibutuhkan sebagai tombol terpisah dari auto-save, KECUALI kamu (Telma) mau pola draft-buffer eksplisit seperti CO Entry — cek dulu pola CO Entry existing sebelum memutuskan, ikuti yang sudah established supaya konsisten lintas modul).
- **Tombol "Hapus" (✕) mengembalikan baris ke picker secara visual (`opacity=1`)** → di asli, ini otomatis terjadi lewat re-query VIEW (Tier 3 §4.1) setelah DELETE `do_line`, TIDAK butuh logic manual "kembalikan tampilan".
- **Warna `co-tag`/`co-tag.co2` hardcode 2 kelas** → di asli, kalau baris berasal dari >2 CO berbeda dalam satu DO, butuh skema warna yang scalable (mis. warna dari hash `co_order_id`, atau cukup satu warna aksen konsisten + teks nomor CO yang membedakan — badge warna berbeda per CO **bukan** kebutuhan fungsional, murni bantu visual, boleh disederhanakan jadi satu warna kalau lebih dari 2-3 CO).

### 7.3 Yang HARUS dipertahankan persis (aturan fungsional, bukan gaya visual)
- **No-merge**: pull dua `co_order_item_id` berbeda SELALU hasilkan 2 baris `do_line`, walau `item_description` identik — ini diuji di §8 di bawah, BUKAN preferensi visual yang boleh disederhanakan.
- **Baris yang sudah ditarik hilang dari picker** — konsekuensi query `qty_available > 0`, bukan state UI yang perlu di-manage manual.
- **Total Qty/Total Nilai dihitung per baris lalu dijumlah**, bukan dari harga rata-rata (Tier2 §B.4).

---

## 8. SKENARIO UAT (pakai seed data Tier 3 §6)

| # | Langkah | Hasil diharapkan |
|---|---|---|
| 1 | Buka DO Entry baru, pilih customer PT Sinar Abadi Kemasan | Blok C picker aktif, menampilkan 5 baris outstanding (2 dari CO2600011, 2 dari CO2600014, 1 dari CO2600018) |
| 2 | Centang baris "Box Karton 5-Ply RSC" dari CO2600011 (Rp12.000) DAN dari CO2600014 (Rp13.500), klik Tarik | Blok D menampilkan **2 baris terpisah**, masing-masing dengan `co_order_item_id` & harga sendiri; badge "⚠ item sama, harga beda" muncul di kedua baris; kedua baris hilang dari picker |
| 3 | Cek Total Nilai di footer Blok D | = (500×12000) + (300×13500) = Rp10.050.000, **bukan** (800×harga-rata) |
| 4 | Tarik baris "Label Sticker" dari CO2600018 (qty 100), lalu **edit `qty_do` turun jadi 60** sebelum Confirm | Baris tersimpan qty 60; kalau kembali ke picker (hapus baris dari DO ini dulu, atau buka DO baru), baris CO2600018 masih tersedia sisa qty **40** |
| 5 | Coba edit `qty_do` baris manapun jadi lebih besar dari batas atasnya (`qty_available + qty_do sendiri`) | Ditolak, pesan error jelas |
| 6 | Hapus salah satu dari 2 baris "Box Karton 5-Ply RSC" (langkah #2) | Baris yang dihapus kembali muncul di picker dengan qty penuh; baris yang tersisa **tidak berubah** qty/harganya |
| 7 | Klik "Confirm Delivery Order" dengan minimal 1 baris valid | `do_no` tergenerate format `DO/26/#####`; status → CONFIRMED; semua field terkunci; Blok C picker hilang dari layar |
| 8 | Buka DO lain (DO baru), coba tarik `co_delivery_id` yang qty-nya sudah habis terserap DO di langkah #7 | Baris tersebut **tidak muncul** di picker DO baru ini |
| 9 | Dari DO CONFIRMED (langkah #7), klik "Batalkan", isi alasan | Status → CANCELLED; semua `do_line` → CANCELLED; kembali ke DO baru (langkah #8), baris yang tadi hilang **muncul lagi** di picker dengan qty penuh |
| 10 | Buat DO CONFIRMED baru (ulangi #1-7 dengan data yang belum terpakai), klik "Tandai Terkirim" | Status → SHIPPED; `actual_ship_date` terisi; cek `co_order_delivery` terkait — kalau qty DO = qty jadwal penuh, statusnya ikut SHIPPED; kalau partial, tetap PLANNED dengan sisa `qty_available` berkurang |
| 11 | Cetak DO dari dokumen SHIPPED (langkah #10) | Kolom harga & total nilai tampil |
| 12 | Cetak Surat Jalan dari dokumen SHIPPED (langkah #10) | Kolom harga **tidak ada sama sekali** (cek query, bukan cuma CSS), ada kolom tanda tangan penerima |

---

## 9. CHECKLIST WIRING

- [ ] Route `dispatch.do.index` / `dispatch.do.entry/{doId?}` / `dispatch.do.print/{doId}` / `dispatch.do.print-surat-jalan/{doId}` terdaftar.
- [ ] Menu sidebar Dispatch ditambahkan (cek `sidebarmenustandard.md` untuk konvensi penamaan menu & posisi).
- [ ] VIEW `v_co_delivery_outstanding` dan `v_do_list` dibuat sebelum komponen Livewire ditulis (komponen bergantung langsung ke VIEW ini).
- [ ] `DispatchService` (atau nama setara) berisi: `pullLines()` (Tier2 §A.3-A.4), `confirm()` (Tier2 §C.2), `markShipped()` (Tier2 §C.3), `cancel()` (Tier2 §C.4) — method ini dipanggil SAMA persis dari DO Entry maupun tombol ✓ Confirm di grid (Tier 4 §2), jangan duplikasi logic di dua tempat.
- [ ] Guard no-merge (Tier2 §B) ada test unit tersendiri, bukan cuma dicek manual — ini aturan inti yang mudah ter-regresi kalau ada refactor.
- [ ] `npm run build` dijalankan setelah semua Blade/Tailwind selesai (Vite production build, bukan hot-reload — `griduistandard.md` §7).
- [ ] Update `dispatch_state.yaml` begitu tiap layar selesai (lihat file itu untuk format `next_step`/`artefak_selesai`).
