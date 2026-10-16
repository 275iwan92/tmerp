# Kekurangan kirim dan klaim ke supplier

*Panduan untuk Jo, staf gudang, PPIC, dan Purchasing. Ditulis dari membaca kode dan layar, 16-Oct-2026. Rincian teknis: `docs/tg/inventory/batch-quality/14_card_supplier-claim-and-trace.md`, `15_card_shortage-request.md`, `25_engine_qc-trace.md`, `29_engine_shortage-auto-detector.md`. Hal yang tidak bisa saya pastikan ada di bagian 7.*

Ada dua hal yang berbeda dan sering tertukar:

- **Klaim ke supplier** lahir otomatis ketika batch **ditolak** QC (mutunya jelek).
- **Permintaan Kirim Kekurangan (Shortage Request)** lahir ketika jumlah bahan ternyata **kurang sejak awal**: bahan yang dikeluarkan ke work order lebih banyak daripada hasil produksi ditambah waste standar, dan selisihnya terbukti lewat hitung ulang.

## 1. Klaim supplier dari batch yang ditolak

**Kapan lahir.** Saat QC menekan **Reject** pada batch (bukan Block). Sistem: (1) menelusuri maju semua dokumen keluar bahan yang memakai batch itu sampai work order, nomor CO, dan nomor pengiriman; (2) menandai tiap work order itu "Held"; (3) membuka **satu** klaim per batch (nomor `CL` + tahun + 5 digit, misalnya `CL2600009`) dengan jumlah = total jumlah diterima batch itu pada penerimaan barang, supplier = supplier batch, alasan = alasan Reject. Bila klaim OPEN atau SENT sudah ada, Reject berikutnya memakai klaim itu.

**Melihat.** Inventory > Stock > Batch & Quality > Report > **Supplier Claims**: nomor klaim, status, supplier, batch, item, jumlah, satuan, alasan, kapan dibuka. Kotak statistik: All, Open, Sent, Settled. Klik baris untuk membuka halaman batch di Conditional Stock; di sana ada bagian **Where This Batch Was Used** (dokumen keluar, work order, CO, pengiriman), **Held Work Order**, dan **Supplier Claim**.

**Mengubah status.** Di bagian Supplier Claim pilih status dan isi Notes lalu **Save**. Status: **Open** (baru), **Sent** (sudah dikirim ke supplier), **Settled** (selesai), **Dropped** (dibatalkan). Yang boleh mengubah: QA, Purchasing (grup `MGR_PUR` atau siapa pun yang boleh membuka modul Procurement), dan Ops Manager ke atas; orang lain melihat kolom tanpa tombol Save. Bila tidak berhak: "You are not allowed to change a supplier claim." Urutan status tidak dipaksa.

**Yang tidak dilakukan sistem.** Klaim tidak punya nilai rupiah, tidak terhubung ke debit note atau retur pembelian, tidak punya cetakan, dan tidak membuat jurnal. Mengubah ke Settled tidak menggerakkan stok atau uang. Bila batch kemudian di-Release, hold dibebaskan tetapi klaim tetap Open sampai diubah tangan. Klaim yang dibuka untuk batch tanpa supplier atau tanpa baris penerimaan akan berisi supplier kosong dan jumlah 0 (itulah isi delapan klaim di data contoh).

## 2. Permintaan Kirim Kekurangan: alurnya

Aturan Jo (8-Oct-2026): bila (hasil baik + waste standar) masih kurang dibanding bahan yang dikeluarkan, bahan itu kurang sejak awal, jadi sistem **meminta kekurangannya dikirim** oleh supplier, subkon, atau customer, setelah dihitung ulang sebagai bukti. Waste di atas standar adalah **varians pemakaian**, bukan kurang kirim.

Status: **Draft** → **Confirmed** → **Sent** → **Received** atau **Refused** → **Closed**. Draft juga bisa langsung Closed lewat Dismiss (varians pemakaian). Tidak ada Cancel.

| Langkah | Siapa | Tombol |
|---|---|---|
| Membuat (otomatis tiap hari pukul 05:15 atau manual) | sistem / siapa pun yang membuka layar | New Record |
| Hitung ulang | Kepala Gudang / Manajer Inventory (`MGR_INV`, `WH_HEAD`) atau Ops Manager ke atas | Save di kartu Recount |
| Putusan | PPIC, Manajer Produksi, atau Ops Manager ke atas | Confirm / Dismiss |
| Minta ke sumber | Purchasing | Send |
| Jawaban sumber | Purchasing | Receive / Refuse |
| Tutup | Purchasing | Close |

### Langkah 1: permintaan lahir
- **Otomatis**: tiap hari pukul 05:15 sistem membandingkan bahan keluar dengan hasil produksi untuk work order yang selesai dan membuat permintaan Draft bila selisih melewati toleransi. Pasangan yang tidak bisa dinilai (work order belum selesai, belum ada hasil, satuan beda, bahan keluar tanpa nomor batch, dst.) dilewati dan tetap bisa diinput manual.
- **Manual**: Inventory > Stock > Batch & Quality > Shortage Request (atau Procurement > Receive > Shortage Request) → **+ New Record**: pilih *Work Order / Material Issued*, pilih *Receipt Line* (penerimaan PO, subkon, atau bahan customer yang sudah Confirmed), isi *Good Output* (dalam satuan bahan), *Standard Waste* (kosong = standar work order), *Needed By*, *Notes* → Save.
- Penolakan: "Output plus standard waste already cover the material issued. A difference in waste is a usage variance, not a shortage." atau "The difference is within the PO tolerance, so it is not a shortage." Toleransi = toleransi baris PO, kalau kosong 5% (pengaturan `RECEIVE_TOLERANCE_PCT`), dihitung dari bahan keluar bersih.
- Pemberitahuan "Recount needed" dikirim ke Kepala Gudang / Manajer Inventory.

Contoh: bahan keluar bersih 1.000 kg, hasil baik dalam satuan bahan 855 kg, waste standar 18 kg. Dugaan kekurangan 1.000 − 873 = 127 kg. Toleransi 5% = 50 kg. 127 melebihi 50, jadi permintaan dibuat untuk dugaan 127 kg. Bila harga penerimaan IDR 15.000 per kg dan seluruh 127 kg terbukti, nilai tagihan debit note kelak 127 × 15.000 = IDR 1.905.000.

### Langkah 2: hitung ulang (Kepala Gudang)
Buka permintaan Draft → kartu **Recount**: *Counted Quantity*, *Weighed (kg)* bila ditimbang, *Proven Shortage* (lebih dari 0 dan tidak lebih dari dugaan; bila lebih muncul "The proven shortage must be more than 0 and not more than the suspected :qty."), dan *How It Was Counted* (minimal 5 karakter; kalau tidak "Describe how the material was recounted or weighed."). Setelah Save, PPIC mendapat pemberitahuan "Shortage to confirm". Hanya yang berhak: "Only the head of the warehouse can record the recount."

### Langkah 3: putusan PPIC
Kartu **PPIC Decision**:
- **Dismiss**: isi Dismiss Note minimal 5 karakter ("Give the reason for dismissing (at least 5 characters)."). Permintaan ditutup, hasil *Usage variance*. Tanpa bukti hitung ulang selisih tetap varians pemakaian.
- **Confirm** (butuh hitung ulang dulu: "The head of the warehouse must record the recount first. Without proof the difference stays a usage variance."). Layar bertanya "Confirm the shortage? The receipt, PO, journal and stock will be corrected." Akibatnya: jumlah terima di penerimaan asal dikurangi, baris PO dibuka kembali untuk sisanya (backorder), untuk penerimaan PO dibuat jurnal koreksi GR/IR dan Stock Out `PV_SHORT`, penerimaan subkon dikoreksi jumlah jasanya, dan bila sumbernya supplier dibuat **Purchase Requisition tambahan berstatus Draft** (Purchasing memilih memakai PR ini ATAU baris PO yang dibuka, jangan keduanya). Purchasing dan PPIC mendapat pemberitahuan "Shortage to follow up". Bila stok atau jurnal tidak bisa dikoreksi (mis. bahan sudah terpakai, akun belum lengkap), muncul **peringatan kuning** di permintaan dan koreksinya dilakukan lewat Stock Adjustment.

### Langkah 4: Purchasing
- **Send** (kartu "Ask the Supplier"): memberi tahu sumber. Jendela kirim mulai hari itu, lamanya 7 hari (pengaturan `INV_SHORTAGE_WINDOW_DAYS`); tanggalnya tampil sebagai *Send Until*, mis. dikirim 16-Oct-2026 berarti sampai 23-Oct-2026.
- **Receive** ("Supplier Answer"): kekurangan sudah datang. Barangnya sendiri diterima lewat penerimaan barang biasa dari baris PO yang dibuka kembali; tombol Receive hanya mencatat, tidak memeriksa penerimaan itu.
- **Refuse**: sumber menolak. Baris PO ditutup pada jumlah yang diterima, permintaan ditandai butuh **debit note** (kecuali sumber customer), dan sebuah debit note berstatus Draft dibuat (nilai = jumlah terbukti × harga penerimaan; belum ada jurnalnya). Layar mengingatkan: "The PO line is closed at the received quantity. Ask the supplier for a debit note or deduct the shortage from the invoice."
- **Close Lapsed** (tombol di daftar): menutup semua permintaan Sent yang jendelanya lewat, sebagai Refused / Window lapsed, dengan konfirmasi "Close every request whose sending window has passed? Their PO lines close at the received quantity." Permintaan dianggap lewat pada hari **sesudah** *Send Until*.
- **Close**: menutup permintaan yang Received atau Refused.

Penerimaan supplier yang sudah dikoreksi permintaan kekurangan tidak bisa di-Cancel utuh: "A shortage request has already corrected this receipt — it cannot be cancelled. Use Purchase Return / Stock Adjustment."

### Laporan
**Shortage by Supplier**: per supplier atau subkon, permintaan yang bukan Draft dan bukan varians pemakaian: jumlah permintaan, jumlah terbukti kurang, yang datang kemudian, ditolak atau lewat jendela, masih terbuka (Confirmed + Sent), dan butuh debit note. Kekurangan yang dikonfirmasi juga membuat supplier masuk pengawasan sampling **Tightened** sampai beberapa lot lulus (panduan 31).

## 3. Catatan yang perlu dipahami
- Permintaan manual tidak memeriksa duplikat; pasangan work order + bahan yang sama bisa dibuat dua kali dan tiap Confirm akan mengoreksi penerimaan lagi. Hanya deteksi otomatis yang melewati pasangan yang sudah ada.
- Layar hanya menampilkan tombol Refuse saat status Sent, padahal sistem mengizinkan dari Confirmed; supplier yang menolak sebelum Send belum bisa dicatat dari layar.
- Kekurangan dari **customer**: jumlah di penerimaan dikurangi, tetapi kartu stok bahan titipan tidak dikoreksi (bagian 5).

## 4. Daftar menu ringkas
Inventory > Stock > Batch & Quality > Shortage Request; Report > Shortage by Supplier; Report > Supplier Claims. Procurement > Receive > Shortage Request membuka layar yang sama.

## 5. Belum terverifikasi
- Tidak ada pengujian dan perintah yang menulis yang saya jalankan. Data contoh: **nol** permintaan kekurangan, nol debit note, jadi seluruh alur di atas belum pernah saya lihat berjalan di layar.
- Data contoh punya **delapan klaim Open yang menunjuk batch yang sudah tidak ada** (jumlah 0, tanpa supplier), sisa uji otomatis; layar Supplier Claims di mockup sekarang menampilkannya. Perlu dibersihkan per baris atas persetujuan Jo.
- Apakah Confirm berjalan atomik lintas database (penerimaan, jurnal, stok) tidak saya periksa; bila gagal di tengah, penerimaan bisa sudah terkoreksi sedangkan permintaan masih Draft.
- Stok Out koreksi kekurangan tidak membawa nomor batch menurut kode, sehingga On Hand batch tidak turun; belum saya uji.
- Jurnal debit note belum ada (tertulis di kode); penanganan akunting menunggu keputusan.
- Siapa pemilik tindak lanjut klaim mutu (QA atau Purchasing) tidak dibedakan dalam kode: keduanya boleh.
- Pembatasan menu Procurement > Receive > Shortage Request vs menu Inventory tidak saya bandingkan hak aksesnya.
- Perhitungan "hasil baik dalam satuan bahan" otomatis hanya jalan bila satuan work order = satuan gudang; sisanya manual.
