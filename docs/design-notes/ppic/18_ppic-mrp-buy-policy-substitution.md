# MRP di ProCost: Buy, Kebijakan Beli, dan Substitusi Bahan

Hasil diskusi Jo dan agen 2026-10-16: siapa memutuskan pembelian dan penggantian bahan, kapan PR dan PO lahir, dan mengapa substitusi tidak ada di Form WO.

## Isi catatan

## Pembagian peran (diagram besar Jo)
- **ProDev**: ENGINE menyusun BOP dan BOM awal dan memastikan sesuai produk yang diminta. Tidak berubah oleh substitusi.
- **ProCost**: biaya BOP dan BOM. Sisi BOM = modul **MRP** (Engine + ML mengecek ketersediaan otomatis dan mengusulkan pengganti).
- **PPIC / Form WO**: hanya mewarisi. **Tidak ada substitusi di Form WO** (Jo 2026-10-16).

## Sudah dibangun (commit 0a50ff36)
- Tombol **Buy (PR + PO)** di rinci bahan Short, Material Planning: PR Draft + PO Draft untuk supplier usulan (`mat_raw.suggest_supplier_id`), harga = `last_unitprice`, PQ dilewati.
- Approval tetap biasa: PR oleh OPM, PO oleh Product Costing lalu OPM. PO yang berasal dari PR belum Approved tidak bisa diajukan.
- Fondasi substitusi (OTP satu permintaan, dampak biaya, notifikasi GM dan Direksi, bahan cadangan) tetap ada di `app/Services/Production/SpecChange/*` untuk dipakai MRP.

## Keputusan Jo
1. ML hanya mengusulkan dan meranking; officer memutuskan (A.7).
2. Kandidat pengganti: kategori sama, **gramatur tidak berubah**, ukuran lembar boleh lebih besar (dipotong di proses potong bahan). Prioritas: kertas dan sheet.
3. Otomatisasi PR/PO: **setengah otomatis**. MRP berjalan terjadwal, menyiapkan PR Draft + PO Draft, officer melihat antrean "Draft ready" lalu menekan Release ke approval. Bahan tanpa supplier atau harga tetap Short dengan tombol Set supplier.
4. **MRP membeli hanya bila ketersediaan bahan tidak cukup**, dan jumlahnya mengikuti **kebijakan beli** (saat ini ditetapkan Direksi manual; ke depan ML + officer, lihat bagian Arah ke depan). Contoh: kurang 1 rim, tetapi beli 10 rim jauh lebih murah per rim dan bahan sering dipakai, maka PR/PO diperbesar menjadi 10 rim. Sisa masuk stok.
5. MRP **selalu mengecek gudang bahan baku dan booking** (stok yang boleh dipakai dikurangi booking order lain ditambah PO/PR berjalan). Bila stok banyak, bahan di-booking dan tidak ada PR.
6. Usulan substitusi muncul di **rincian bahan**, terutama kertas dan sheet.
7. ProCost Direct = **Mode Instant**: kartu read-only "Material availability (MRP)" di bawah Hasil Kalkulasi. Tidak ada CO, jadi hanya pre-booking lunak, PR/PO tidak dibuat dari sini.
8. PR yang sudah terbit saat substitusi disetujui: dibahas per kasus nanti.

## Arah ke depan: kebijakan beli dari Direksi menjadi ML + officer (Jo 2026-10-16)
- **Sekarang**: kebijakan beli (kelipatan, MOQ, harga bertingkat, batas stok lebih) ditetapkan Direksi secara manual. Ini juga jadi data awal dan bahan belajar ML.
- **Nanti**: ML membaca **riwayat pembelian** (harga per jumlah, frekuensi, pemakaian) dan **situasi pasar** untuk mengusulkan jumlah dan waktu beli, sehingga **ML dan officer bekerja tanpa Direksi**. Direksi tidak lagi di jalur keputusan harian; ia hanya menetapkan batas luar (mis. nilai maksimal) dan membaca laporannya.
- Rancang sekarang agar peralihan tanpa ubah layar: setiap usulan jumlah menyimpan **sumber** (Policy atau ML), alasan, dan keputusan officer. Hasil keputusan officer dicatat sebagai bahan belajar (pola sama dengan CostingAdvisor: statistik dari 5 data, ML dari 30).
- Terbuka: **sumber data "situasi pasar"** (penawaran supplier yang diinput, indeks harga, atau lainnya) belum ada di sistem; ML hanya sebaik data yang masuk. Perlu layar input (aturan: setiap nilai yang dibaca aplikasi wajib punya layar input).

## Yang sudah ada di kode (fakta)
- `mat_raw` hanya punya `min_stock`, `safety_stock`, `reorder_point`, `max_stock`. **Tidak ada** MOQ, kelipatan beli, atau harga bertingkat.
- PR dari MRP sekarang hanya dibulatkan ke satuan beli (`MaterialPlanning::createPr`).
- Data dummy: tidak ada bahan dengan supplier usulan atau harga terakhir (antrean Draft ready akan kosong sampai diisi).

## Usulan kebijakan beli (menunggu keputusan Jo)
Per bahan atau kategori, disetujui Direksi: kelipatan beli dan MOQ; harga bertingkat menurut jumlah; batas stok lebih dalam bulan pemakaian rata-rata; tanda "sering dipakai" dari riwayat pemakaian (ML). Tampilan ke officer: butuh 1 rim, usulan 10 rim, harga satuan dan total, alasan satu kalimat; officer boleh menurunkan ke jumlah minimum.

## Pertanyaan terbuka
- a. Kebijakan Direksi per bahan, atau per kategori dengan pengecualian per bahan?
- b. Jumlah melebihi kebutuhan: langsung diusulkan, atau perlu persetujuan Direksi di atas batas nilai tertentu?
- c. Sumber harga bertingkat: penawaran supplier yang diinput sendiri, atau riwayat PO?
- d. Sisa pembelian lebih masuk stok bebas, atau ditahan untuk order berikutnya yang sudah terprediksi?
- e. Booking order lama menahan stok yang dibutuhkan order baru yang lebih mendesak: siapa yang boleh melepasnya?
- f. PR yang sudah terbit saat substitusi disetujui: disarankan dikurangi, dibatalkan, atau diajukan pembatalan? Dan cakupan substitusi: per baris CO atau per WO, boleh dipecah?

## Urutan kerja yang diusulkan
1. Kebijakan beli (master + layar input) dan hitung jumlah PR dari kebijakan itu.
2. Antrean Draft ready + Release, memakai jumlah dari langkah 1 (konstanta jam jalan dan batas nilai di layar Constants).
3. Usulan substitusi di rincian bahan kertas dan sheet (OTP, dampak biaya, MRP hitung ulang, WO mewarisi).
4. Kartu ketersediaan di ProCost Instant.

## Sesi lain yang terkait
- ENGINE penyusun BOP/BOM dari data Inquiry Product: `docs/handoff-prodev-session.md` (sesi terpisah).
