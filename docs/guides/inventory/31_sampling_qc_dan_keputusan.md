# Sampling QC, keputusan mutu, First Article, dan eskalasi

*Panduan untuk Jo, tim QC, dan PPIC. Ditulis dari membaca kode dan layar, 16-Oct-2026. Rincian teknis: `docs/tg/inventory/batch-quality/12_card_qc-sampling-setup.md`, `13_card_qc-decision-and-first-article.md`, `22_engine_qc-sampling.md`, `23_engine_qc-decision.md`, `24_engine_qc-escalation.md`. Hal yang tidak bisa saya pastikan ada di bagian 8.*

## 1. Gambaran: sistem menyarankan, orang QC memutuskan

Sistem tidak pernah memutuskan lulus atau gagal. Ia hanya **menyarankan berapa sampel** yang diambil dari satu lot; hasil uji, jumlah cacat, dan putusan (Release, Reject, Block, Conditional) dicatat oleh orang QC. Jumlah cacat di sampel disimpan dan ditampilkan, tetapi tidak dibandingkan otomatis dengan AQL.

Yang boleh memutuskan: grup **QA**, **QA_HEAD** (Kepala QC; grup ini belum dibuat di Setup > Security), serta Ops Manager ke atas. Orang lain melihat pesan "Only Quality Assurance or an operations manager can record the decision." dan tidak melihat kartu Quality Decision.

## 2. Tiga tabel pengaturan sampling

Menu: Inventory > Stock > Batch & Quality > Setup. Menurut teks di layar, Kepala QC yang mengisinya.

**Risk Class** (kelas risiko A / B / C). Satu baris memilih **kategori material ATAU kode item**, tidak dua-duanya dan tidak kosong ("Choose a material category or type an item code, not both and not neither."). Kode item yang tidak ada di master bahan baku ditolak; satu kategori atau item hanya boleh punya satu kelas. Aturan pemilihan: baris item menang atas kategori; kategori yang lebih dalam menang atas induknya; tanpa baris sama sekali = kelas **B**. Panduan di layar: A = food grade, klaim FSC / PEFC, bahan berbahaya; B = kertas, board, film, kardus bergelombang, lem dari supplier yang dikenal; C = bahan penolong kecil. Saat ini tabel ini **kosong**, sehingga semua item berkelas B.

**Sampling Rules** (tiga baris tetap, per kelas). Nilai awal yang ada:

| Kelas | Intensitas awal | AQL % | Reduced setelah (lot lulus) | Pulih setelah reject (lot lulus) |
|---|---|---|---|---|
| A | Tightened | 1,00 | 10 | 5 |
| B | Normal | 2,50 | 10 | 5 |
| C | Skip (cukup sertifikat) | 4,00 | 10 | 5 |

**Sampling Plan** (ukuran sampel menurut intensitas dan jumlah lot). Satu baris berarti "mulai dari jumlah lot ini"; yang dipakai adalah baris dengan batas bawah terbesar yang tidak melebihi jumlah lot. Kolom batas atas hanya catatan. Dua baris dengan intensitas dan batas bawah sama ditolak ("This intensity already has a row that starts at this lot quantity."). Nilai awal adalah Z1.4 level II yang disederhanakan, mis. intensitas Normal: lot 1 sampai 8 diambil 2, 9 sampai 15 diambil 3, 16 sampai 25 diambil 5, 26 sampai 50 diambil 8, 501 sampai 1.200 diambil 80, mulai 500.001 diambil 1.250. Skip tidak butuh baris (0 sampel). Sampel tidak pernah melebihi jumlah lot.

## 3. Bagaimana intensitas dipilih

Urutannya (yang pertama cocok menang):
1. **Supplier baru**: kurang dari 5 lot yang pernah diputuskan di dok (pengaturan `INV_QC_NEW_SUPPLIER_LOTS`) = **Tightened**, apa pun kelas itemnya, termasuk kelas C.
2. **Pemulihan**: supplier pernah punya kejadian buruk (reject jenis apa pun, atau kekurangan kirim yang dikonfirmasi PPIC dan bukan sekadar varians pemakaian) dan belum lulus 5 lot berturut-turut sesudahnya = **Tightened**.
3. **Konsisten**: lulus 10 lot berturut-turut dan awalnya bukan Skip = **Reduced**.
4. Selain itu intensitas awal kelasnya.

Contoh: item kelas B, supplier dengan 12 lot dok yang bersih, lot 20 kg: Reduced, 2 sampel. Setelah satu reject First Article dan baru 2 lot lulus: Tightened, 8 sampel; setelah 5 lot lulus kembali Normal (5 sampel). Supplier yang tidak tercatat di batch (semua batch backfill) selalu dianggap baru.

Rencana ini tampil di halaman batch ("Sampling Plan for this Lot", misalnya "B · Normal · 80 pcs" atau "certificate only") dan di kartu "URGENT and Sampling" pada Purchase Receive. Satuan lot adalah satuan stok (bisa kg); layar menulis "pcs". Apakah lot 1.000 kg memang disampel 80 lembar belum ditetapkan siapa pun (bagian 8).

## 4. Langkah: memutuskan batch di Conditional Stock

1. Buka Inventory > Stock > Batch & Quality > **Conditional Stock**. Daftar berisi batch Conditional dan Quarantine yang aktif, **yang paling lama menunggu di atas**. Kolom Deadline: On time, Escalate (lewat 4 jam), Overdue (lewat 8 jam); ada juga Hours Waiting, badge URGENT dan alasannya, serta sertifikasi.
2. Klik baris. Halaman batch memperlihatkan status, sejak kapan, jam menunggu, jumlah diterima, sertifikasi, kedaluwarsa, siapa menandai URGENT dan kenapa, dan rencana sampling.
3. Di kartu **Quality Decision** isi: *Inspection* (Sampling at dock, First article, Usage decision, atau Deviation; bawaannya Sampling at dock bila batch Conditional, selain itu Usage decision), *Lot Quantity* (bawaan = jumlah diterima), *Sample Size* (bawaan = saran sistem), *Defective in Sample*, *Test Result* (mis. "gsm 250.4, moisture 7.1%"), dan *Reason*.
4. Klik salah satu tombol:
   - **Release**: batch menjadi Released, hold work order dibebaskan.
   - **Conditional**: tetap boleh dipakai dengan tanda.
   - **Block**: batch Blocked, work order yang memakainya ditandai ditahan, tanpa klaim.
   - **Reject**: batch Rejected, work order yang memakainya ditandai ditahan, dan satu klaim supplier dibuka (panduan 32).
5. **Reject dan Block wajib beralasan** minimal 5 karakter; kalau kurang muncul "Give a reason (at least 5 characters) when a batch is rejected or blocked." dan tidak ada yang berubah. Sukses menampilkan "Batch :no is now :status."

Semua keputusan tercatat di *Decision History* (kapan, jenis, putusan, sampel/cacat, hasil uji, alasan, siapa). Keputusan yang sama bisa diambil dari tab Quality decisions di form Batch, tetapi hanya Release dan Reject yang tersedia di sana.

**Hal yang perlu diingat**
- Tidak ada aturan urutan: batch Rejected bisa di-Release lagi, dan batch Released bisa di-Reject. Kesalahan dikoreksi dengan keputusan baru; riwayat menyimpan semuanya.
- Release setelah Reject membebaskan hold tetapi **tidak menutup klaim**; klaim harus diubah ke Dropped sendiri.
- Keputusan yang bertipe *Sampling at dock* dan Released dihitung sebagai satu lot lulus di riwayat supplier, walaupun diklik berulang di batch yang sama.
- "Ditahan" pada work order hanya berupa **catatan**: tidak ada bagian produksi, picking, atau pengiriman yang diblokir oleh tanda itu. Lihat bagian 8.

## 5. First Article (barang pertama di mesin)

Menu: Batch & Quality > **First Article**. Daftar memperlihatkan hasil terdahulu. Untuk mencatat:
1. Klik + New Record. Hanya QA atau Ops Manager yang boleh; yang lain melihat "Only Quality Assurance or an operations manager can record a first article."
2. Pilih **Work Order** dan **Material Batch** (hanya batch aktif berstatus Conditional, Quarantine, atau Released; 300 terbaru), pilih **Result** Pass atau Fail, isi Notes.
3. Save. **Pass** melepas batch (Released). **Fail** menolak batch: Notes wajib (minimal 5 karakter), work order yang memakai batch ditahan, klaim dibuka. Pesan sukses: "First article passed — the batch is released." atau "First article failed — the batch is rejected and its work orders are held."

Peringatan: sistem tidak memeriksa bahwa batch yang dipilih memang yang dikeluarkan ke work order itu, dan Fail menolak **seluruh** batch, bukan hanya satu palet.

## 6. Eskalasi otomatis

Tiap jam sistem memeriksa batch Conditional dan Quarantine. Lewat **4 jam** tanpa keputusan: pemberitahuan lonceng "Quality decision escalated" ke Kepala QC dan Ops Manager. Lewat **8 jam**: "Quality decision overdue" ke Kepala QC, QA, Ops Manager, dan PPIC, dengan kalimat bahwa Usage Decision diperlukan sekarang. Pesan yang sama tidak dikirim dua kali ke orang yang sama. Admin tidak otomatis menerima. Dua pengaturan jamnya ada di Settings > Constants (`INV_QC_ESCALATE_HOURS` = 4, `INV_QC_DEADLINE_HOURS` = 8).

Karena grup Kepala QC belum ada, pemberitahuan 4 jam sekarang hanya sampai ke Ops Manager.

## 7. Siapa memutuskan apa (ringkas)

| Tindakan | Siapa |
|---|---|
| Tandai URGENT | PPIC, Manajer Produksi, Ops Manager ke atas |
| Setujui URGENT FSC / food grade | Kepala QC, Ops Manager ke atas |
| Keputusan mutu, First Article | QA, Kepala QC, Ops Manager ke atas |
| Ubah pengaturan sampling | siapa pun yang boleh membuka menu (tidak ada batas khusus yang saya temukan) |

## 8. Belum terverifikasi

- Tidak ada pengujian dan perintah penulis yang saya jalankan; semua dari kode dan data contoh. Data contoh hanya punya satu keputusan (dok, Released), nol First Article, nol hold.
- **Hold work order tidak menghentikan apa pun** menurut pencarian kode. Apakah Jo memang ingin hanya tanda, atau ingin production/picking/pengiriman benar-benar diblokir, perlu keputusan.
- Siapa yang boleh mengubah tiga tabel Setup sampling: tidak saya periksa di pembatas akses layar umum.
- Arti "jumlah sampel" untuk lot dalam kg belum jelas (lembar? gram?).
- Nilai awal Z1.4 yang disederhanakan: siapa yang menyetujuinya sebagai standar pabrik tidak tercatat di sumber yang saya baca.
- Kelas risiko saat ini kosong; apakah kelas A untuk bahan food grade/FSC akan diisi sebelum UAT belum diketahui.
- Grup `QA_HEAD`, `WH_HEAD`, `PROD_SPV` belum ada; pesan untuk "Kepala QC" sekarang hanya sampai ke Ops Manager ke atas.
- Jam batas 4 dan 8 dihitung jam kalender; teks pengaturan di database menyebut "working hours".
