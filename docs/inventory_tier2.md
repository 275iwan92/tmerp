# TIER 2 — INVENTORY / STOCK MOVEMENT · BUSINESS LOGIC & RULES

> Turunan `inventory_tier1.md`. Semua angka & aturan di sini bersifat mengikat untuk implementasi.
> Nilai ambang yang masih menunggu konfirmasi ditandai `[DELTA-CONFIRM]` — Claude Code tetap
> mengimplementasikannya dengan nilai default yang tertulis, karena semuanya tersimpan di
> tabel rule (`flow_config_step`, `sys_constant`), bukan di kode.

---

# PART A — HAPPY PATH

## A-0. Aturan yang berlaku untuk keempat dokumen

### A-0.1 Header wajib
| Field | Aturan |
|---|---|
| `doc_no` | Auto dari `sys_sequence`, dibentuk saat **simpan pertama**, tidak berubah selamanya |
| `doc_date` | Default hari ini. Harus jatuh di dalam `inv_period` berstatus `OPEN`. Tidak boleh > hari ini |
| `doc_type`, `doc_cat` | Ditetapkan oleh layar, **tidak bisa diubah user**, immutable setelah simpan |
| `warehouse_id` | Wajib. Mengunci daftar lokasi yang bisa dipilih di baris |
| `period_id` | Diisi sistem dari `doc_date` |
| `flow_status` | `DRAFT` saat dibuat |

### A-0.2 Aturan baris universal
1. Minimal satu baris; dokumen tanpa baris tidak bisa disimpan.
2. `location_id` wajib pada setiap baris. Legacy mengizinkan `NULL`/`0` — itu penyebab saldo yatim di `v_rm_onhand`; sekarang ditolak validasi.
3. `uom_code` disnapshot dari master item saat baris dibuat, tidak ikut berubah bila master diubah kemudian.
4. `qty` disimpan **selalu positif** kecuali `doc_cat IN ('AD','OP')` yang menyimpan nilai bertanda.
5. Kombinasi (`item`, `location_id`, `lot_no`) tidak boleh muncul dua kali dalam satu dokumen — kecuali dokumen Return, yang boleh punya dua baris item sama dengan `return_condition` berbeda.

### A-0.3 Kontrak posting — `StockPostingService`
Satu titik masuk untuk seluruh modul. Dipanggil di dalam `DB::transaction()`.

```
postDocument(stockClass, docId):

  1. Kunci header: SELECT ... FOR UPDATE. Tolak bila flow_status <> status
     yang sah untuk posting, atau stat_confirm sudah = 1.
  2. Tolak bila period_id tidak OPEN.
  3. Untuk tiap baris detail, urut doc_detail_id ASC:
     a. Lewati bila action_type = 'PURCHASE'          (tidak menyentuh stok)
     b. Lewati bila qty = 0 DAN doc_cat <> 'BB'       (baris opname tanpa selisih)
     c. Tentukan qty_final:
          doc_cat AD/OP  -> qty apa adanya (sudah bertanda)
          doc_cat RETURN -> ABS(qty)
          selain itu     -> doc_type = -1 ? -ABS(qty) : ABS(qty)
     d. Ambil baris kartu stok terakhir untuk (item, location_id):
          WHERE (doc_date < :docDate)
             OR (doc_date = :docDate AND doc_id < :thisDocId)
          ORDER BY doc_date DESC, doc_id DESC LIMIT 1  FOR UPDATE
        -> previous_doc_id, qty_bb (0 bila tidak ada)
     e. qty_eb = qty_bb + qty_final
     f. Tolak bila qty_eb < 0 DAN doc_cat NOT IN ('AD','OP')
        (koreksi saldo negatif adalah wewenang Adjustment, bukan efek samping)
     g. INSERT *_stockcard  (reference_name + reference_detail_id = kunci idempotensi)
     h. Perbarui nilai:
          costing_method AVG       -> moving average, tulis value_* & avg_unitprice
          costing_method FIFO/LIFO -> IN: INSERT inv_stock_layer
                                      OUT: konsumsi layer + INSERT inv_layer_consumption
  4. UPDATE header: stat_confirm = 1, flow_status = 'POSTED', posted_by/at
  5. UPDATE m_raw_material.cur_stock / m_product.cur_stock
  6. Tulis flow_log (action = 'AUTO_ADVANCED' atau 'APPROVED')
```

**Idempotensi.** `UNIQUE (reference_name, reference_detail_id)` pada tiap `*_stockcard`. Panggilan kedua gagal di level database. Ini penambal langsung bug double-posting `doc_id=95` di data legacy.

**Urutan kunci.** Baris dikunci berurutan `doc_detail_id ASC`; kartu stok dikunci `FOR UPDATE` per (item, lokasi). Konsisten di seluruh service agar tidak ada deadlock silang.

---

## A-1. STOCK IN

### A-1.1 Tiga sumber
| `ref_doc_type` | Pemicu | Alur |
|---|---|---|
| `PV` | Procurement memposting penerimaan | Dokumen dibuat & diposting **otomatis** dalam transaksi PV. Tidak muncul sebagai draft. |
| `WO` | Produksi melaporkan output (SFG/FG) | Otomatis, sama seperti PV |
| `MANUAL` | Pembelian tunai, hibah, temuan, koreksi migrasi | Draft → butuh persetujuan Kepala Gudang → posting |

**Stock In manual tetap dipertahankan** (bukan dihapus), karena kenyataan lapangan: pembelian tunai mendesak dan barang masuk sebelum administrasi PO selesai. Yang dijaga adalah pertanggungjawabannya:
- `attachment_path` **wajib** (nota/surat jalan supplier), validasi menolak simpan tanpa lampiran.
- Wajib melewati satu level persetujuan, tanpa memandang nilai.
- Muncul di laporan bulanan "Barang Masuk Tanpa PO" untuk Manajer Operasional.

### A-1.2 Saldo awal (`doc_cat = 'BB'`)
- Hanya boleh dibuat oleh peran dengan `INV.can_manage_period`.
- Hanya untuk item yang **belum punya** baris kartu stok sama sekali pada (item, lokasi) tersebut. Bila sudah ada, sistem mengarahkan ke Adjustment.
- Baris dengan `qty = 0` **tetap diposting** (satu-satunya pengecualian aturan A-0.3.3.b), karena saldo awal nol adalah pernyataan yang bermakna.
- Menghasilkan tipe kartu `BB`, `qty_bb = 0`.

### A-1.3 Harga
- Sumber `PV`: `unit_price` menetes dari `pv_line.c_unit_price`.
- Sumber `WO`: harga pokok produksi dari Production; bila belum tersedia, `0` dan ditandai `[PENDING_COST]`.
- Sumber `MANUAL`: diisi manual oleh peran Sisi A. Staff gudang menyimpan tanpa harga; Manajer Operasional melengkapi sebelum tutup periode.

---

## A-2. STOCK OUT

### A-2.1 Pembentukan
1. User memilih WO berstatus **dirilis**.
2. Sistem menarik `prd_wo_material` → satu baris kebutuhan per material, terisi `qty_required`.
3. Untuk tiap baris, sistem membaca `v_rm_available` → `qty_available`, `qty_booked`.
4. `qty` (qty keluar) default = `MIN(qty_required, qty_available)`.

Kelas stok dikunci ke **RM**. Selector kelas tidak ditampilkan.

### A-2.2 Tiga jenis baris (`action_type`)
| Nilai | Arti | Menyentuh stok? | `parent_detail_id` |
|---|---|---|---|
| `ISSUE` | Material yang diminta, dikeluarkan apa adanya | Ya | `NULL` |
| `SUBSTITUTE` | Material pengganti menutup kekurangan | Ya | menunjuk baris `ISSUE` induk |
| `PURCHASE` | Kekurangan yang tak tertutup, harus dibeli | **Tidak** | menunjuk baris `ISSUE` induk |

Baris `PURCHASE` adalah **pemicu Purchase Requisition**, bukan pergerakan stok. Service posting melewatinya. Setelah PR dibuat, `pr_detail_id` diisi balik agar bisa ditelusuri.

### A-2.3 Substitusi
- Hanya boleh ditambahkan pada baris induk yang `qty` < `qty_required`.
- `replace_ratio` default dari master substitusi; boleh ditimpa dengan alasan.
- Substitusi wajib berkeputusan PPIC — dicatat di `note_detail`, bukan approval terpisah (menambah approval di sini akan menghentikan lantai produksi).
- Total tertutup = `qty` induk + Σ(`qty` anak `SUBSTITUTE` × `replace_ratio`). Sisa masuk baris `PURCHASE`.

### A-2.4 Larangan
- **Kelas FG ditolak** — pengeluaran barang jadi adalah Delivery Note.
- Lokasi dengan `is_usable = 0` (waste) tidak muncul di pilihan.
- `qty` > `qty_available` ditolak, kecuali peran `INV.can_override_stock` memberi alasan tertulis (kasus: stok fisik ada tapi kartu stok tertinggal — tetap dicatat sebagai penyimpangan).

### A-2.5 Booking
Bila WO punya `rm_booking` terbuka, posting Stock Out menambah `rm_booking_d.qty_issued`. Bila `qty_issued >= qty_book` untuk seluruh baris, `rm_booking.stat_closed = 1`.

---

## A-3. STOCK ADJUSTMENT & OPNAME

### A-3.1 Input adalah hasil hitung fisik, bukan selisih
Ini aturan yang tidak boleh dikompromikan di UI.

```
qty_system   = saldo kartu stok saat baris ditarik (snapshot, disimpan)
qty_physical = angka yang diketik user
qty          = qty_physical − qty_system     ← dihitung sistem, disimpan bertanda
```

`qty_system` disnapshot dan **disimpan**, bukan dibaca ulang saat posting. Kalau tidak, penyesuaian yang disetujui tiga hari kemudian akan menghitung selisih terhadap saldo yang sudah bergerak, dan hasilnya salah dua kali.

**Konsekuensinya:** bila terjadi pergerakan lain antara pembuatan dan persetujuan, sistem menampilkan peringatan "saldo berubah sejak dihitung" pada layar persetujuan dan menawarkan tarik ulang. Penyetuju yang memutuskan.

### A-3.2 Beda Adjustment dan Opname
| | Adjustment (`AD`) | Opname (`OP`) |
|---|---|---|
| Pemicu | Kejadian tunggal (rusak, hilang, salah input) | Hitung fisik terjadwal |
| Baris | Hanya item yang bermasalah | Seluruh item di cakupan lokasi |
| Field khusus | — | `opn_officer`, `opn_start`, `opn_stop`, saksi — semua wajib |
| Baris selisih 0 | Tidak lazim | **Wajib tetap disimpan** sebagai bukti sudah dihitung |
| Nomor | `ADJ/YY/#####` | `OPN/YY/#####` |

Baris selisih 0 disimpan di `*_stock_d` tapi **dilewati** service posting — tidak menghasilkan baris kartu stok. Bukti audit tanpa mengotori ledger.

### A-3.3 Alasan
`reason_code` wajib, dari daftar terkunci (di `sys_constant`, bukan enum kode):

`OPNAME_SCHEDULED` · `DAMAGED` · `SHRINKAGE` · `WRONG_ENTRY` · `LOST` · `FOUND` · `MIGRATION`

Alasan per baris (`note_detail`) wajib untuk setiap baris berselisih.

### A-3.4 Persetujuan berjenjang
Nilai dokumen = `Σ ABS(qty × unit_price)` — **nilai mutlak**, bukan neto. Penyesuaian +1000 dan −1000 bernilai nol secara neto tetapi merupakan dua kesalahan yang perlu ditinjau.

| Level | Jabatan | `min_amount` | `max_amount` | Tolak → |
|---|---|---|---|---|
| 1 | Kepala Gudang | 0 | 5.000.000 `[DELTA-CONFIRM]` | `REVISION_NEEDED` |
| 2 | Manajer Operasional | 5.000.000 `[DELTA-CONFIRM]` | 25.000.000 `[DELTA-CONFIRM]` | `REVISION_NEEDED` |
| 3 | Direksi | 25.000.000 `[DELTA-CONFIRM]` | `NULL` | `REVISION_NEEDED` |

Lampiran bukti (foto/berita acara) **wajib** bila nilai melampaui level 1.

**Layar persetujuan terpisah dari layar entri** — mengikuti aturan global. Tombol Setujui/Tolak tidak pernah muncul di layar entri, bahkan bila user kebetulan punya kedua hak.

### A-3.5 Posting
Kartu stok baru terbentuk setelah step approval **terakhir yang berlaku** disetujui. Tipe kartu `AD` untuk `AD` maupun `OP`.

---

## A-4. RETURN FROM PRODUCTION

### A-4.1 Pembentukan
1. User memilih dokumen **Stock Out berstatus POSTED**.
2. Sistem menarik baris `ISSUE` + `SUBSTITUTE` dari dokumen itu (baris `PURCHASE` dilewati — tak pernah keluar, tak bisa kembali).
3. Tiap baris membawa: `qty_keluar`, `qty_returned` (akumulasi retur sebelumnya), `qty_max = qty_keluar − qty_returned`.

### A-4.2 Batas qty
`qty` retur ≤ `qty_max`. Divalidasi di dua tempat: sisi klien (umpan balik langsung) dan sisi server sebelum posting (kebenaran). Kelebihan tidak bisa "dimaafkan" — bahan lebih dari yang pernah keluar bukan retur, itu temuan, dan jalurnya Adjustment `FOUND`.

### A-4.3 Kondisi & lokasi tujuan
| `return_condition` | Lokasi tujuan | Efek |
|---|---|---|
| `GOOD` | Lokasi normal di gudang asal (`is_usable = 1`) | Menambah on hand **dan** available |
| `SCRAP` | Lokasi di gudang `warehouse_type = 'WASTE'` (`is_usable = 0`) | Menambah on hand, **available tetap 0** |

Satu material boleh dipecah jadi dua baris dengan kondisi berbeda. Kombinasi (item, lokasi, lot, kondisi) unik per dokumen.

Validasi silang: `return_condition = 'SCRAP'` dengan lokasi `is_usable = 1` ditolak, dan sebaliknya. Ini bukan sekadar konsistensi data — ini yang mencegah bahan afkir bocor ke produksi.

### A-4.4 Harga
Retur memakai **harga saat keluar**, bukan harga pasar terbaru:
- AVG: `unit_price` = `avg_unitprice` yang berlaku pada baris OUT sumbernya.
- FIFO/LIFO: layer dikembalikan dengan `unit_cost` asli dari `inv_layer_consumption`.
- `SCRAP`: nilai penuh tetap dicatat. Penurunan nilai barang afkir adalah keputusan Accounting (penyisihan), bukan keputusan gudang.

### A-4.5 Rekonsiliasi WO
Setelah retur diposting, sistem menghitung per material:
```
terpakai_bersih = qty_keluar − qty_retur_GOOD − qty_retur_SCRAP
varian          = terpakai_bersih − qty_standar (dari prd_wo_material)
```
Varian dikirim ke Production sebagai data waste riil, dan menjadi umpan balik untuk asumsi waste di Product Costing.

---

# PART B — KASUS KHUSUS

| # | Kasus | Penanganan |
|---|---|---|
| B-1 | Saldo jadi negatif saat posting OUT | Ditolak. Pesan menyebut item, lokasi, saldo tersedia. Jalan keluar: Adjustment dulu, atau override oleh `can_override_stock` dengan alasan. |
| B-2 | Dokumen dikonfirmasi dua kali (bug legacy) | Gagal di `UNIQUE (reference_name, reference_detail_id)`. Ditangkap service, dikembalikan sebagai "dokumen sudah diposting", bukan error 500. |
| B-3 | Posting mundur ke periode tertutup | Ditolak `inv_period`. Diarahkan membuat Adjustment bertanggal periode berjalan. |
| B-4 | Saldo bergerak antara pembuatan Adjustment dan persetujuan | Peringatan di layar persetujuan + tombol "Tarik ulang saldo sistem". Penyetuju memutuskan. |
| B-5 | Opname menemukan item yang tak ada di kartu stok | Baris ditambah manual dengan `qty_system = 0`. Selisih positif penuh, `reason_code = 'FOUND'`. |
| B-6 | Item ada di kartu stok tapi tidak dihitung saat opname | Tidak otomatis dianggap nol. Baris tak tersentuh = saldo tak berubah. Hanya opname `full_scope = 1` yang memaksa seluruh item masuk daftar. |
| B-7 | WO dibatalkan setelah bahan keluar | Seluruh bahan diretur lewat RTP dengan `reason_code = 'WO_CANCELLED'`. Booking ditutup. |
| B-8 | Retur bahan yang lotnya sudah habis di kartu stok | Tetap boleh — retur membuat saldo lot itu positif kembali. Yang dibatasi hanya `qty_max`. |
| B-9 | Material pengganti diretur | Baris retur menunjuk `source_detail_id` baris `SUBSTITUTE`, bukan induknya. Batas qty mengikuti baris pengganti. |
| B-10 | PV dibatalkan setelah stok masuk | Procurement memanggil `StockPostingService::reverse()` → membuat dokumen Stock Out `doc_cat='RETURN_SUPPLIER'` otomatis. Baris kartu stok asli **tidak dihapus**. |
| B-11 | Dua user memposting item & lokasi sama bersamaan | `SELECT ... FOR UPDATE` pada baris kartu stok terakhir. Yang kedua menunggu, lalu menghitung `qty_bb` yang benar. |
| B-12 | Konversi UOM beda antara master dan fisik | `uom_code` disnapshot per baris. Konversi memakai `m_uom_conversion`; bila tidak ada jalur konversi, posting ditolak dengan pesan eksplisit. |
| B-13 | Lot kosong / `NULL` | Diizinkan (banyak item tidak ber-lot). `lot_no` bukan bagian kunci saldo — saldo dihitung per (item, lokasi). Lot murni jejak fisik & FIFO. |
| B-14 | Adjustment ditolak di level 2 | `flow_status = 'REVISION_NEEDED'`, dokumen bisa diedit lagi, lalu diajukan ulang. Riwayat penolakan tetap di `flow_log`. |
| B-15 | Gudang WASTE penuh / barang afkir dibuang | Pembuangan adalah Adjustment `DAMAGED` di gudang WASTE — keluar dari sistem lewat jalur yang sama seperti selisih lain, tetap berjenjang persetujuan. |

---

# PART C — STATE · FORM · BUTTON

## C-1. Status dokumen (`flow_status`)

| Status | Arti | Bisa diedit? |
|---|---|---|
| `DRAFT` | Baru dibuat / dikembalikan untuk revisi | Ya |
| `SUBMITTED` | Diajukan, menunggu persetujuan level 1 | Tidak |
| `APPROVED_L1` … `APPROVED_Ln` | Sebagian level sudah menyetujui | Tidak |
| `REVISION_NEEDED` | Ditolak, dikembalikan ke pembuat | Ya |
| `POSTED` | Kartu stok terbentuk | **Tidak pernah** |
| `CANCELLED` | Dibatalkan sebelum posting | Tidak |

Pemetaan ke kolom legacy (dipertahankan agar view & procedure lama tetap jalan):
`flow_status = 'POSTED'` ⇔ `stat_confirm = 1` · `flow_status = 'CANCELLED'` ⇔ `stat_cancel = 1`

## C-2. Alur per dokumen

| Dokumen | Alur |
|---|---|
| Stock In (PV/WO) | (dibuat sistem) → `POSTED` |
| Stock In (Manual) | `DRAFT` → `SUBMITTED` → `POSTED` |
| Stock Out | `DRAFT` → `POSTED` |
| Return | `DRAFT` → `POSTED` |
| Adjustment / Opname | `DRAFT` → `SUBMITTED` → `APPROVED_L1` → (`APPROVED_L2`) → `POSTED` |

Stock Out dan Return tidak melewati persetujuan: keduanya menjejak dokumen hulu yang sudah tervalidasi (WO dirilis / Stock Out terposting), dan menahannya berarti menahan lantai produksi.

## C-3. Matriks tombol

**Stock In**
| Status | Simpan Draft | Ajukan | Posting | Batalkan | Cetak |
|---|---|---|---|---|---|
| DRAFT | ✔ create/edit | ✔ (manual) | ✔ (non-manual) | ✔ | — |
| SUBMITTED | — | — | ✔ approve | ✔ approve | — |
| POSTED | — | — | — | — | ✔ |
| CANCELLED | — | — | — | — | ✔ |

**Stock Out / Return**
| Status | Simpan Draft | Posting | Batalkan | Cetak |
|---|---|---|---|---|
| DRAFT | ✔ | ✔ | ✔ | — |
| POSTED | — | — | — | ✔ |
| CANCELLED | — | — | — | ✔ |

**Adjustment / Opname** (layar entri)
| Status | Simpan Draft | Ajukan | Batalkan | Cetak |
|---|---|---|---|---|
| DRAFT / REVISION_NEEDED | ✔ | ✔ | ✔ | — |
| SUBMITTED / APPROVED_Ln | — | — | — | — |
| POSTED | — | — | — | ✔ |

**Adjustment / Opname** (layar persetujuan — terpisah)
| Status | Setujui | Tolak | Tarik Ulang Saldo |
|---|---|---|---|
| SUBMITTED / APPROVED_Ln | ✔ bila user pemegang jabatan step berjalan (atau PLT-nya) | ✔ (alasan wajib) | ✔ |

## C-4. Aturan tombol universal
1. Tombol yang tak berlaku **dihilangkan**, bukan di-disable — kecuali bila alasannya perlu dijelaskan, maka disable + tooltip.
2. Posting selalu lewat dialog konfirmasi yang menyebut akibatnya ("kartu stok terbentuk, dokumen tidak dapat diedit").
3. Approve/Reject tidak pernah muncul di layar entri.
4. Cetak tersedia untuk `POSTED` dan `CANCELLED`; nota dokumen batal diberi watermark "DIBATALKAN".

## C-5. Permission (model global 4 aksi + 3 khusus)

| Kode | Arti |
|---|---|
| `INV_IN.view/create/edit/approve` | Stock In |
| `INV_OUT.view/create/edit/approve` | Stock Out |
| `INV_ADJ.view/create/edit/approve` | Adjustment & Opname |
| `INV_RTP.view/create/edit/approve` | Return |
| `INV_CARD.view` | Kartu stok & saldo |
| `INV.can_view_cost` | Membuka kolom nilai (Sisi A) |
| `INV.can_override_stock` | Melewati blokir saldo negatif dengan alasan |
| `INV.can_manage_period` | Buka/tutup periode, buat saldo awal |

`approve` di sini berarti "boleh memposting/menyetujui", bukan sekadar melihat.
