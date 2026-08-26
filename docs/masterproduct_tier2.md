# TIER 2 — MASTER PRODUCT · BUSINESS LOGIC

Basis: Tier 1. Struktur A (happy path) · B (special) · C (state-form-button).

## A. HAPPY PATH

### A1. Lahir dari Inquiry (jalur utama)
1. Inquiry NEW → desain + imposisi + proof warna dibuat.
2. Proof di-ACC (digital+cetak). 
3. Tombol **"Jadikan Master Card"** → transaksi salin snapshot ke master:
   `prod_product` → `prod_part[]` → `prod_part_material[]` → `prod_part_ink[]` → `prod_part_process[]` → `prod_part_artwork(v1)` → `prod_mastercard_signoff(5 role kosong)`.
4. `last_design_id` inquiry menaut ke part baku.
5. Sign-off berjalan → `card_status=APPROVED`.

### A2. Entry Manual (migrasi legacy)
Form master product langsung menulis ke tabel yang sama (1 transaksi). Untuk produk lama tanpa inquiry.

### A3. Rakit Kartu (baca)
`vw_master_card` merakit header; part-loop menampilkan tiap part (artwork+dieline+warna+proses). **Teruji**.

## B. SPECIAL CASES
| Kasus | Aturan |
|---|---|
| **Single-part** (`has_multi_part=0`) | 1 part implisit; UI sembunyikan rail; artwork+dieline di level produk. GENIALE = ini. |
| **Multi-part** | N part; tiap part artwork+dieline+ink+proses sendiri. |
| **Part polos** (SEKAT) | `prod_part_ink`=0, `prod_part_artwork`=0; dieline tetap ada. |
| **Single-pass** | `prod_part_ink` pass_seq=2 kosong (KS tak diisi). |
| **Revisi artwork** | INSERT versi baru; flag `is_active` pindah; versi lama tersimpan. |
| **Update spec produk confirmed** | Header `is_confirmed=1` → edit spec inti terkunci; **revisi via inquiry REVISE**, bukan edit master langsung. Artwork tetap boleh tambah versi. |
| **MTS** | `customer_id`=GENERAL. |

## C. STATE · FORM · BUTTON

### Card status
`DRAFT` → (5 signoff + artwork approved) → `APPROVED` → (perlu ubah spec) → **tidak edit di master**, buat inquiry REVISE → hasil promosi = versi produk baru.

### Tombol per kondisi
| Kondisi | Tombol aktif |
|---|---|
| DRAFT, belum lengkap | Simpan Draft · Upload Artwork · +Part |
| DRAFT, lengkap | Ajukan Sign-off |
| Menunggu sign-off | (per role) Tanda Tangani |
| APPROVED | Cetak Kartu · Jadikan Baku · RECALC · REVISE |

### Invariant (enforce Laravel + trigger)
- Tepat 1 `prod_part_artwork.is_active` per part.
- `card_status=APPROVED` ⇒ `signed_count=5` ∧ artwork aktif `is_approved=1`.
- `prod_part_ink` unik per (part, pass, unit).
