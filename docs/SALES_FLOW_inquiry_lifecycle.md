# SALES_FLOW_inquiry_lifecycle.md — Siklus Inquiry → PO → Master Product

> Khas modul 01_Sales. Menunjuk `GLOBAL_RULES.yaml` (rantai otoritas, doc_types) &
> `SALES_RULES.yaml` (lock/selector, imposition, master card). TIDAK menyalin — hanya
> menajamkan alur end-to-end + koreksi pemahaman.
> Status: **koreksi pemahaman** (Jo, 2026-08-14). Pasangan: `SALES_STATE.yaml`.

---

## 1. Ringkasan koreksi (delta dari pemahaman lama)

| # | Sebelumnya (keliru/samar) | Koreksi |
|---|---|---|
| 1 | Imposition/Product Designer selalu di jalur | **KONDISIONAL** — di-*bypass* bila ukuran = plano normal & penataan mudah; costing hitung langsung |
| 2 | Master Product terbentuk saat inquiry/costing confirmed | **Terbentuk saat PO customer diterima**, dan PO SELALU didahului `PRICE_APPROVED`. Eksekusi tanpa harga (atas dasar kepercayaan) adalah **anomali bisnis, BUKAN acuan desain sistem** |
| 3 | Master Card = Master Product | **BEDA entitas & beda trigger.** Master Card (produksi) terbit saat Costing CONFIRMED; Master Product (katalog) lahir saat PO |
| 4 | Inquiry tipe REVISE = "inquiry NEW yang direvisi/diedit" | **SALAH, BEDAKAN TEGAS.** Lihat §6a — REVISE & RECALC hanya berlaku untuk **REORDER** produk yang Master Product-nya sudah eksis (pernah PO). NEW yang belum di-PO, kalau diedit, tetap NEW (masih draft/belum materialized) — bukan REVISE |

---

## 2. Alur happy-path (NEW, jalur penuh)

```
SALES                DESIGNER            COSTING            ATASAN         CUSTOMER
  │
  1. buat Inquiry
     (spec produk)
  │
  2. CONFIRM ─────────► (sinyal: spec FIX, siap diproses)
                          │
                          3. Imposition + Routing/BOP
                             (layout terbaik utk qty besar /
                              ukuran non-plano-normal)
                          │
                          4. CONFIRM ──────► 
                                              5. Costing (harga jual)
                                                 spec 🔒, pilih baris harga ◆
                                              │
                                              6. CONFIRM ──────► 
                                                                  7. APPROVED
  ◄──────────────────── data mengalir balik ke Sales ◄──────────────────┘
  │
  8. jadikan Quotation
  9. CONFIRM quotation
  10. kirim PDF quotation ──────────────────────────────────────────────► terima
                                                                          │
                                                          11. setuju → kirim PO
  ◄──────────────────────── PO diterima ◄────────────────────────────────┘
  │
  12. Inquiry TERVALIDASI → MATERIALISASI ke MASTER PRODUCT:
      • m_product (identitas produk)
      • part_of_product (semua part)
      • semua kebutuhan pemenuhan produk (material/warna/proses/finishing)
      • relasi CUSTOMER (siapa pemesan)
```

### Jalur bypass Designer (produk ukuran umum & penataan mudah)
```
1. Inquiry → 2. CONFIRM ───────────────► 5. Costing langsung hitung
   (langkah 3–4 dilewati; imposition default/trivial, tak perlu Product Designer)
```
Selebihnya (6→12) identik.

---

## 3. Gerbang confirm/approve (siapa & maknanya)

| Gerbang | Aktor | Makna sinyal |
|---|---|---|
| Confirm Inquiry | Sales | Spec sudah FIX — costing & designer boleh mulai |
| Confirm Imposition | Product Designer | Layout/UP/plano/routing terkunci → kontrak read-only ke Costing |
| Confirm Costing | Officer Costing | Perhitungan siap direview atasan |
| **Approved** Costing | Atasan | Harga sah → mengalir balik ke Sales |
| Confirm Quotation | Sales | Quotation final → boleh kirim PDF ke customer |
| **PO diterima** | Customer (aksi Sales rekam) | Inquiry tervalidasi → **trigger materialisasi Master Product** |

Pola tetap: **engine suggests, officer confirms** (GLOBAL_RULES).

---

## 4. Status kanonik inquiry (usulan — untuk state machine)

```
DRAFT
  → CONFIRMED            (gerbang 2; spec fix)
  → IMPOSITION_DONE      (opsional; skip bila bypass)
  → COSTED               (costing confirmed)
  → PRICE_APPROVED       (atasan approve)
  → QUOTED               (quotation confirmed & terkirim)
  → PO_RECEIVED          (PO customer masuk)
  → MATERIALIZED         (master product ter-insert)  ← terminal sukses
```
Cabang: quotation ditolak/menggantung → berhenti di `QUOTED` (tak pernah `MATERIALIZED`).

---

## 5. Materialisasi Master Product (aturan)

- **Trigger:** PO customer diterima (status `PO_RECEIVED`).
- **Aksi:** auto-insert dari snapshot inquiry yang sudah `PRICE_APPROVED` ke:
  `m_product` + `part_of_product` + kebutuhan pemenuhan (material/warna/proses/finishing) + relasi customer.
- **Sumber data:** snapshot terkunci (spec dari Inquiry+Imposition, harga dari Costing) — bukan input ulang.
- **Idempoten:** satu inquiry → satu materialisasi (guard di `MATERIALIZED`).

### Master Card vs Master Product (JANGAN dicampur)
| | Master Card | Master Product |
|---|---|---|
| Sifat | Dokumen produksi (print A4, `vw_master_card`) | Entitas katalog (`m_product`+`part_of_product`) |
| Terbit saat | Costing NEW **CONFIRMED** | **PO diterima** |
| Lokasi | `tm2mst` (m_card*) | `tm2mst` (m_product*) |
| Bisa eksis tanpa yang lain? | Ya — Master Card ada walau PO belum masuk | Tidak lahir tanpa PO |

---

## 6. Precondition doc-type (NEW vs RECALC vs REVISE)

| Tipe | Master Product harus ada dulu? | Keterangan |
|---|---|---|
| **NEW** | Tidak | Dari nol; berujung MELAHIRKAN Master Product saat PO |
| **RECALC** | **Ya** | Reorder produk lama, **spec tetap**, harga dihitung ulang |
| **REVISE** | **Ya** | Reorder produk lama, **spec berubah** saat pemesanan berikut |

Konsekuensi: RECALC/REVISE selalu berpangkal pada produk yang sudah pernah ter-PO
(sudah materialized) — inquiry-nya adalah inquiry BARU yang mereferensikan Master
Product tersebut, bukan mengedit inquiry lama.

---

## 6a. REVISE vs RECALC — definisi dikunci (JANGAN ditawar ulang) 🔒

**Prasyarat sama untuk keduanya:** produk sudah PERNAH dipesan (Master Product exists,
lahir dari PO sebelumnya) dan SEKARANG dipesan LAGI (reorder). Bedanya ada di PEMICU:

| | **REVISE** | **RECALC** |
|---|---|---|
| Pemicu | Customer minta **perubahan SPEC** saat reorder (dimensi, bahan, warna, dll berubah) | **Kondisi ekonomi/harga bahan baku** berubah (naik/turun); spec identik |
| Spec | BERUBAH | TETAP |
| Harga | Dihitung ulang KARENA spec baru | Dihitung ulang KARENA harga input (bahan) baru |
| Mekanisme teknis | Copy-record (revision_no+1); versi lama `is_cancelled=1` `cancel_reason=SUPERSEDED` (GLOBAL_RULES doc_types) | Hitung ulang costing atas spec yang sama; tidak ada perubahan spec untuk di-supersede |

### ⚠️ Kesalahpahaman yang WAJIB dihindari
"Inquiry tipe REVISE" **BUKAN** sama dengan "inquiry NEW yang sedang direvisi/diedit
sebelum PO turun". Selama sebuah inquiry belum PO (belum jadi Master Product), setiap
perubahan padanya tetap berstatus **NEW** (draft/in-flight) — bukan doc-type REVISE.
Doc-type REVISE HANYA muncul pada siklus REORDER atas Master Product yang sudah ada.

> Ini domain rule, berlaku selamanya di project ini — bukan interpretasi per-kasus.

---

## 7. Poin yang perlu konfirmasi Jo (belum dikunci)

1. Nama tabel katalog: `m_product` + `part_of_product` — konfirmasi prefix/nama final (`prod_*`? `m_product`?).
2. Apakah materialisasi juga menautkan `mcard_id` (Master Card yang sudah terbit lebih awal) ke Master Product baru?
3. Reorder REVISE/RECALC: field mana di Master Product yang jadi ANCHOR pencarian
   ("produk yang sudah pernah dipesan ini") — customer+item_code? customer+dimensi+bahan?
   Perlu ini utk desain UI "pilih produk existing untuk reorder".

> §7.1 & §7.4 versi sebelumnya (timing PRICE_APPROVED→PO, dan status berpangkal-master-product)
> sudah DIKUNCI oleh Jo — lihat §1 dan §6a.
