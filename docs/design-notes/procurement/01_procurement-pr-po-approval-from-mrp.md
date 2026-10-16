# Procurement: PR dan PO dari MRP, approval, instruksi Direksi

Dicatat 2026-10-16 dari diskusi sesi ProCost. Bukan bagian ENGINE MRP; milik modul Purchasing / Procurement. Sesi ProCost hanya menyerahkan PR Draft + PO Draft ke sini.

## Sudah ada (commit 0a50ff36)
- Tombol Buy di Material Planning membuat PR Draft + PO Draft untuk supplier usulan (`mat_raw.suggest_supplier_id`), harga = `last_unitprice`, PQ dilewati.
- Approval biasa: PR oleh OPM; PO oleh Product Costing lalu OPM. PO dari PR yang belum Approved tidak bisa diajukan.

## Keputusan Jo
1. **Batas nilai PR**: bebas, ditetapkan Direksi (konstanta di layar Constants, tanpa nilai awal dari agen). PR di atas batas diteruskan ke approval Direksi.
2. **Instruksi verbal Direksi**: untuk pembelian bahan baku yang Direksi sampaikan verbal ke purchasing, PO di-approve oleh **Direksi atau wakil yang ditunjuk (officer)**.

## Usulan agen (belum disetujui Jo)
- Purchasing mencentang "Director instruction" di PO, mengisi tanggal dan catatan; tersimpan di PO dan Riwayat sebagai bukti.
- Wakil memakai fitur Acting Officer yang sudah ada (HRM > Organization); tanpa tabel baru.
- Approval Direksi/wakil **menggantikan** jalur biasa (Product Costing lalu OPM) untuk PO bertanda ini; batas nilai tidak berlaku.
- PR otomatis dari MRP tetap disetujui OPM.

## Terbuka
- Apakah menggantikan atau menambah jalur biasa (menunggu Jo).
- Cara kerja Acting Officer di kode belum dicek; belum dicek apakah jalur approval PO bisa berbeda per PO.
