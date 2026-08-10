# TIER 4 — MASTER PRODUCT · UI/UX

Basis: Tier 2. Cakupan: layar Master Card (baca/cetak) · form edit · mode single/multi.

## 1. Tiga layar
| Layar | Fungsi | Mockup |
|---|---|---|
| **Master Card** (baca/cetak) | rakit `vw_master_card` + part loop | `master_card_geniale.html` |
| **Product View** (browse) | header + viewer artwork/dieline per part, mode single/multi | `master_product_v2.html` |
| **Product Edit** (input) | header+owner, part editor, upload artwork, ink, proses | `master_product_edit.html` |

## 2. Mode render (driver `has_multi_part`)
- **Single (0)**: viewer artwork+dieline langsung di produk, tanpa rail part. (GENIALE)
- **Multi (1)**: rail part kiri → tiap part punya artwork+dieline+ink+proses.

Satu sumber data, dua render — tak ada logika duplikat.

## 3. Panel per part (3 lapis)
1. **Artwork** — thumbnail + dropdown versi (v1/v2/v3) + badge APPROVED, drop-zone upload (=versi baru).
2. **Dieline (SVG)** — dari library `prod_dieline_template`, render TMDieline.
3. **Layout Cetak** — lembar imposisi + blok warna CS/KS per unit + urutan pekerjaan (nomor). Ref `layout_cetak_sheet.html`.

## 4. Blok warna (input)
Grid pass × unit: baris Pass 1 (CS) unit 1–4, Pass 2 (KS) unit 1–4. Tiap sel = dropdown tinta + swatch. Kosong = tak dipakai.

## 5. Sign-off
Footer "Mengetahui": 5 kotak (Desainer/Marketing/PPIC/QA/Plant Manager). Tiap role tombol Tanda Tangani → isi `signed_by/at`. Semua terisi → `card_status=APPROVED`, watermark DRAFT hilang.

## 6. Cetak
Tombol Cetak Kartu → render A4 landscape sesuai layout asli + catatan "ACC PROOF PRINT wajib dilampirkan".

## 7. BINDING TABEL (kontrak field → kolom)
| Field kartu | Kolom |
|---|---|
| Kode Item | `prod_product.product_code` |
| No. Desain | `prod_product.design_no` |
| Nama Barang | `prod_product.product_name` |
| Nama Pelanggan | `cust_customer.customer_name` (via `customer_id`; MTS=GENERAL) |
| Dimensi P/L/T | `prod_product.cm_length/width/height` |
| Kode Pisau | `prod_part.dieline_ref` |
| Ukuran Cetak | `prod_part.printsize_p/l` |
| Ukuran Bahan | `prod_part_material.size_p/l` (per layer) |
| Material/Kualitas | `mat_raw` via `prod_part_material.material_id` + `gram_weight` |
| Bentuk Sambungan | `prod_part.joint_type` |
| Jenis Finishing | `prod_part.finishing` |
| Isi/Cetakan · Pack | `prod_part.up_per_print` · `pack_qty` |
| Warna CS/KS unit | `prod_part_ink(pass_seq,unit_no,ink_name)` |
| Urutan Pekerjaan | `prod_part_process(step_seq,process_name,is_merge)` |
| Artwork/Proof | `prod_part_artwork(version_no,is_approved)` |
| Sign-off | `prod_mastercard_signoff(role_name,signed_at)` |
| Status | `prod_product.card_status` |

**Kontrak**: frontend minta `vw_master_card` + child arrays; tak ada layar simpan aturan sendiri.
