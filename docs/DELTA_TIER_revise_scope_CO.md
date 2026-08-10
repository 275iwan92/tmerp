# DELTA TIER — REVISE SCOPE & RELASI CO SNAPSHOT

Sisipan untuk Tier 1/2/3 Inquiry. Menyusul pemahaman: REVISE punya *scope*, RECALC reuse layout, item CO membawa snapshot.

## TIER 1 (requirement) — tambah aturan
- **R-REV-1**: REVISE wajib punya **scope manual** (≥1 dari: MATERIAL, DIMENSION, ARTWORK, COLOR, PROCESS) + `revise_reason`. Sistem tak auto-menebak.
- **R-REV-2**: Scope menentukan pemicu: DIMENSION→imposisi ulang; ARTWORK→proof ulang; MATERIAL/COLOR/PROCESS/DIMENSION→costing ulang; ARTWORK→due-date reset.
- **R-REV-3**: Contoh COLOR = tambah spot (mis. logo Halal) → +plate → HPP naik walau ukuran/bahan tetap.
- **R-RC-1**: RECALC **tak menyentuh** dimensi/dieline/imposisi/artwork/warna — semua reuse dari baku; hanya harga bahan di-refresh.
- **R-CO-1**: Item `co_line` membawa **snapshot** (harga, up, ukuran cetak, dieline_ref, warna) + **referensi** (`inquiry_id`, `imposition_id`, `product_id`). Revisi inquiry tak mengubah CO lama.

## TIER 2 (logic) — matriks pemicu
| Scope | Imposisi ulang | Proof ulang | Costing ulang | Due reset |
|---|:--:|:--:|:--:|:--:|
| MATERIAL | – | – | ✔ | – |
| DIMENSION | ✔ | – | ✔ | – |
| ARTWORK | – | ✔ | (jika +warna) | ✔ |
| COLOR | – | – | ✔ (+plate) | – |
| PROCESS | – | – | ✔ | – |

Versi lama → `CANCELLED · SUPERSEDED`. Versi baru → butuh `PRICE_APPROVED` sebelum masuk quotation/CO.

## TIER 3 (DDL) — delta kolom
```sql
-- pada header inquiry (iqp_inquiry / i_inquiry)
ALTER TABLE iqp_inquiry
  ADD COLUMN revise_scope SET('MATERIAL','DIMENSION','ARTWORK','COLOR','PROCESS') NULL
      COMMENT 'Scope REVISE (multi). NULL untuk NEW/RECALC',
  ADD COLUMN revise_reason VARCHAR(255) NULL COMMENT 'Alasan revisi (mis. tambah logo Halal)';

-- pada co_line (snapshot + referensi)
ALTER TABLE co_line
  ADD COLUMN ref_inquiry_id    BIGINT UNSIGNED NULL COMMENT 'FK logis asal inquiry (telusur)',
  ADD COLUMN ref_imposition_id BIGINT UNSIGNED NULL COMMENT 'FK logis layout asal',
  ADD COLUMN ref_product_id    BIGINT UNSIGNED NULL COMMENT 'FK logis master baku',
  ADD COLUMN snap_price        DECIMAL(18,2) NULL COMMENT 'Harga BEKU per line (imun revisi)',
  ADD COLUMN snap_up           INT UNSIGNED  NULL COMMENT 'UP beku',
  ADD COLUMN snap_dieline_ref  VARCHAR(80)   NULL,
  ADD COLUMN snap_artwork_ver  INT UNSIGNED  NULL COMMENT 'Versi artwork beku (mis. v1 tanpa Halal)';
```
Catatan: `SET(...)` didukung MySQL 8.0. Referensi = FK logis (Eloquent), snapshot = kolom beku.

## Mockup yang mencerminkan ini
- `inquiry_revise_v2.html` — 5-scope selector + matriks konsekuensi + contoh Halal (+spot ink, HPP impact) + jejak CO.
- `inquiry_recalc_v2.html` — reuse layout dari baku + refresh harga bahan + jejak CO.
