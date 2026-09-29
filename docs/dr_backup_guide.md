# Developer Guide: Backup & Disaster Recovery (database + file pendukung)

> Pertanyaan user 2026-09-29: artwork disimpan sebagai file biasa? di folder apa? Dokumen ini jawaban + pedoman
> backup/restore. Update tiap ada jenis file baru atau folder baru.

## 1. Prinsip

- **File (artwork, lampiran, bukti ACC, dokumen) disimpan sebagai FILE BIASA di disk, BUKAN BLOB di database.**
  Database hanya menyimpan **path relatif** + nama file asli. Alasan: database tetap kecil & cepat di-dump,
  file bisa di-backup inkremental (rsync/robocopy hanya file yang berubah), gambar dilayani langsung oleh web server.
- Konsekuensi: **backup = 2 bagian yang harus diambil bersamaan** — (a) dump semua database `tm2*`,
  (b) folder file. Restore salah satunya saja = path di database menunjuk file yang tidak ada (atau sebaliknya).

## 2. Lokasi file (disk Laravel `public` = `storage/app/public`, dilayani lewat `public/storage`)

| Folder | Isi | Ditulis oleh | Kolom DB yang menunjuk |
|---|---|---|---|
| `inquiry/{id_iqp_part}/artwork/` | Artwork sisi luar per part Inquiry | `InquiryWriter::storeArtwork()` | `tm2sdi.iqp_part.artwork_path` |
| `inquiry/{id_iqp_part}/artwork-inner/` | Artwork sisi dalam | idem | `tm2sdi.iqp_part.artwork_inner_path` |
| `product/{id_prod_part}/artwork-outer-vN.*`, `artwork-inner-vN.*` | Artwork Master Product berversi (salinan saat Quotation WIN) | `ProductMasterSync::artwork()` | `tm2mst.prod_part_artwork.artwork_path` |
| `product/{id_product}/acc/` | Bukti ACC customer (screenshot WA/e-mail, PDF) | route `v2.product.acc` | `tm2mst.prod_product_acc.evidence_path` |
| `hrm/{id}/…` | Dokumen karyawan | modul HRM | tabel HRM terkait |
| `recruitment/portal-documents/` | Dokumen pelamar dari portal | `PortalSyncService` | `tm2hrr.*` |

Di luar disk `public`:
- `storage/app/private/` — file privat (HR, upload sementara Livewire `livewire-tmp` = **tidak perlu** di-backup).
- `storage/app/ddt-baseline/` — baseline data DUMMY (mockup.test); bukan data produksi, opsional.
- `docs/attachments/` — acuan form & dokumen lapangan; sudah ikut **git** (backup lewat GitHub).

## 3. Yang wajib di-backup (produksi, tmerp.test / live)

1. **Database**: semua `tm2mst tm2sdi tm2pur tm2inv tm2prd tm2acc tm2hrm tm2hrr tm2pyr tm2sys`
   (+ `tm2old` sekali saja, data migrasi lama). Contoh:
   `mysqldump --single-transaction --routines --triggers --databases tm2mst tm2sdi ... > tm2_YYYYMMDD_HHMM.sql`
   — `--single-transaction` = snapshot konsisten tanpa mengunci tabel InnoDB.
2. **File**: `storage/app/public/` seluruhnya, dan `storage/app/private/` kecuali `livewire-tmp/`.
3. **Konfigurasi**: `.env` (berisi kunci `APP_KEY` — tanpa kunci yang sama, data terenkripsi seperti payroll
   TIDAK bisa dibaca setelah restore). Simpan terpisah & aman, jangan di git.
4. Kode = GitHub (`origin`), tidak perlu backup terpisah.

Urutan backup: dump database dulu, lalu salin folder file segera sesudahnya (jarak sesingkat mungkin supaya
path & file konsisten). Simpan minimal di 2 tempat: disk lain di server + luar kantor (cloud/NAS).

## 4. Restore (disaster recovery)

1. Pasang kode dari GitHub (commit yang sama dengan saat backup bila bisa), `composer install`, `npm run build`.
2. Kembalikan `.env` (APP_KEY sama).
3. Import dump database → `php artisan migrate --force` (menjalankan migration yang lebih baru dari dump, bila ada).
4. Salin kembali `storage/app/public` dan `storage/app/private`; jalankan `php artisan storage:link` bila `public/storage` hilang.
5. Cek: buka satu Master Product yang punya artwork + satu bukti ACC; gambar harus tampil.

## 5. Catatan

- Jangan mengganti file langsung di folder (misal menimpa artwork lama) — aplikasi membuat versi/folder baru
  supaya dokumen lama (quotation, CO, proof) tetap menunjuk file yang benar.
- Dummy mockup.test memakai database `ddt*` + baseline sendiri (`php artisan ddt:baseline save|restore --env=mockup`);
  terpisah dari backup produksi di atas.
