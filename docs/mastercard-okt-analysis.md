# Master Card Okt 2026: Inquiry sampai plan produksi (dummy ddt*)

> Jo 2026-10-14: *"buat data dummy dari folder mastercard; analisa dan simulasi dari inquiry product hingga plan produksi"*, *"jangan ada celah, jangan BOM kosong"*, *"sebelum APPLY"*.
> Dibangun oleh `database/seeders/UatMasterCardOktSeeder.php` (hanya ddt*, idempotent). Berhenti di plan **Draft**; tidak ada APPLY / Confirm plan.
> Penanda: **FAKTA** = terukur di ddt* 14 Okt 2026 (plan run 1140). **DUGAAN** = belum dibuktikan.

## 1. Rantai yang dijalankan (lewat aturan layar yang sama)

Inquiry (isi dari kartu) -> Sales Confirm -> ProDev (layout dari kartu, `saveLayout(.., confirm)`; BOP / BOM / tinta = `SpecEngine`) -> ProCost (`CostingEngine`, publish) -> Quotation -> WIN (Master Product) -> CO (pilih qty, due date, Confirm, approval) -> CPDEL **Post to Production** -> WO -> plan Draft (`AutoPlanRunner`).

Masukan yang **tidak ada di kartu** (dummy, tidak mengarang fakta kartu): qty tier, tanggal CO, harga ProCost (angka contoh CostingEngine), panjang set Buku 1-6 (salinan pola SET 7 / SET 8), tebal buku 3 mm (diperkirakan).

## 2. Hasil per produk

| CO | Produk (kartu) | Customer | Part / WO | Qty | Flat per part (mm) | UP | Plan Draft | Due |
|---|---|---|---|---|---|---|---|---|
| CO2600050 | R10K IV350 Naskot Ayam Goreng Balaikota DS002581 | Yukhsinu Food Solution | 1 / WO2600313 | 20.000 | 949 x 190,33 | 3 | 15 Okt 09:10 - 28 Okt 11:00 | 4 Nov |
| CO2600051 | Box Slider IV Rinal DS002904 | Rinal Asfurin | 1 / WO2600314 | 5.000 | 997 x 683 | 1 SET | 15 Okt 08:54 - 28 Okt 12:15 | 7 Nov |
| CO2600057 | Buku Pedoman Siswa SMKN 8 Malang DS002948 | Pak Riza | Body + Cover + SET 8..1 / WO2600331-340 | 3.000 | Cover 426 x 302; set 852 x 604 | 1 | 14 Okt 14:24 - 21 Okt 07:12 (Body tidak terjadwal) | 11 Nov |
| CO2600053 | JPC Gold N100 MF DS002722 | PT Selatan Jadi Jaya | Body + A + B / WO2600325-327 | 20.000 | 620 x 454 | 1 | 16 Okt 07:34 - 4 Nov 11:45 | 9 Nov |
| CO2600054 | Power X N70 MF DS002908 | PT Selatan Jadi Jaya | 1 / WO2600328 | 10.000 | 990 x 573 | 1 | 15 Okt 13:30 - 26 Okt 13:14 | 1 Nov |
| CO2600055 | R10B Gemmolin Bakpao DS003482 | Gemmolin | 1 / WO2600329 | 20.000 | 786 x 347,5 | 2 | 15 Okt 13:31 - 28 Okt 09:45 | 3 Nov |
| CO2600056 | OB Omegamoto Side Rack | Yogas Omegamoto | 1 / WO2600330 | 2.000 | 1981 x 571 | 1 | 15 Okt 08:51 - 19 Okt 14:35 | 28 Okt |
| CO2600021 | KS Power N120 MF DS003637 (kartu lama) | PT Selatan Jadi Jaya | Body + A + B / WO2600355-357 | 30.000 | 729 x 464 | 1 | 15 Okt - 11 Nov | 13 Nov |
| CO2600022 | Osima MS N100 CV DS003146 (lama) | PT Selatan Jadi Jaya | Body + A + B / WO2600358-360 | 20.000 | 623 x 452 | 1 | 15 Okt - 26 Okt | 30 Okt |
| CO2600023 | YAMA Din170 MF DS003026 (lama) | PT Selatan Jadi Jaya | **ditahan**, tanpa WO | 15.000 | 765 x 494 | 1/2 | - | 3 Nov |
| CO2600024 | Outer Alsamsi Cream Lock (lama) | Pak Mukri | Body + A + B / WO2600364-366 | 5.000 | 602 x 484,5 | 1 | 15 Okt - 28 Okt | 7 Nov |
| CO2600025 | Alsamsi Exclusive Top DS003047 (lama) | Pak Mukri | 1 / WO2600367 | 10.000 | 417 x 350,5 | 4 | 14 Okt - 20 Okt | 11 Nov |
| CO2600026 | Yahya 250g C22 DS002159 (lama) | PT Upaya Guna Sejahtera | 1 / WO2600368 | 48.000 | 250 x 161 | 8 | 15 Okt - 16 Okt | 1 Nov |

DS002590 (Box BTP Omegamoto Spotlight) sudah ada dari sesi lain (IQ2690068, CO2600049, WO2600312) dan tidak diubah.

## 3. Verifikasi "tanpa celah"

- **BOM tidak kosong (FAKTA):** 29 WO aktif, semuanya punya baris BOM (7 sampai 11 baris per part). Berkat patch SpecEngine (ATTENTION-ENGINE-01): bahan yang baru berupa kategori bernama "Paper > Ivory > 350gr" dan tetap tampil; kolom Process terisi lewat `processOfBom`.
- **Master Card lengkap (FAKTA, `Ppic::masterCard`):** semua WO part berstatus Complete. Pengecualian yang dicatat: lihat §4 butir 5 dan 8.
- **Plan (FAKTA):** 169 operasi, 4 belum terjadwal (semua di WO Body Buku), tidak ada WO yang selesai setelah due date. Gate: 140 READY, 29 WAIT (operasi pertama tiap WO menunggu kertas).

## 4. Celah engine yang ditemukan (tanpa ditambal; menunggu keputusan Jo)

1. **BOP engine mengganti urutan kerja kartu (FAKTA).** `SpecEngine` menyusun BOP dari aturan (`prod_bop_rule`), bukan dari urutan kartu. Langkah kartu yang hilang di WO: Lem Samping & Bottom Lock + Pasang Tali (Slider), Lem Samping (Power X), Strapping (Bakpao), Slitter Slotter + Stitching (OB; engine malah menambah "Plong Depan" 1.324 menit di IECHO BK4), Potong untuk sebagian part, dan di set Buku engine menambah Plong / Kupas / Pack Wrapping yang tidak ada di kartu. Join type (Single / Double) di Inquiry tidak menurunkan langkah lem.
2. **Proses tidak ada di master proses = bukan SUBCON, tetapi NO_MACHINE (FAKTA).** "SET (KOMPLIT)" (kumpul set Buku) menghentikan Body Buku: `NO_MACHINE` + 3 operasi `CHAIN_BLOCKED`, tidak terjadwal. Aturan "proses tanpa mesin = SUBCON" hanya berlaku bila proses ada di master. Proses kartu yang belum ada di master: Set (Komplit), Lem Punggung (dipetakan ke LEM MESIN), Pasang Tali, Slitter Slotter, Strapping, Shrink (dipetakan ke WRAPPING). Master proses = data real, tidak diubah.
3. **Library dieline tidak menghasilkan bentangan pisau (FAKTA, 5 dari 7 kartu baru).** Bentangan hasil tipe vs pisau per potong: R10K 830 x 190,3 vs 949 x 190,3; Slider 990 x 250 vs 997 x 683; Power X sebelum tuck disesuaikan 990 x 409 vs 990 x 573; Bakpao 888 x 245 vs 786 x 347,5; OB 1981 x 356 vs 1981 x 571. Hanya JPC (RSC_2PC) cocok. Ukuran datar yang dipakai ProDev = ukuran pisau kartu (`iqp_part.flat_*`), gambar dieline = saran tipe terdekat. Tidak ada tipe RSC satu bagian (slitter slotter) dan tipe sleeve + tray.
4. **Nama pisau otomatis melebihi kolom WO (FAKTA, sempat menggagalkan seluruh plan).** `knife_code` "PISAU <NAMA PRODUK> ISI n MATA" (sampai 80) vs `prd_wo.dieline_ref` varchar(60): `WoPlanningEnricher` melempar error, `PlanEvents::replan` hanya menulis log WARNING, dan **semua** WO setelahnya tidak diperkaya / tidak terjadwal. Terbukti dengan nama produk 73 karakter; dihindari dengan nama kartu yang lebih pendek. Perlu keputusan: melebarkan kolom atau memotong nama.
5. **Body (rakitan) tampil Incomplete di Master Card WO (FAKTA).** `Ppic::masterCard` menandai BODY tanpa artwork / flat / bahan, sedangkan `partsMissing` melewati ASSEMBLY. BOM Body Buku berisi baris "(kertas belum dipilih di Inquiry)" 3.000 LBR tanpa proses: rakitan tidak seharusnya meminta kertas; kebutuhan sebenarnya = hasil WO Cover dan SET 8..1.
6. **Urutan set Buku 8 -> 1 tidak diturunkan engine (FAKTA).** Urutan mundur hanya tersimpan sebagai `part_seq` Inquiry (Cover, SET 8..SET 1) dan catatan langkah "SET (KOMPLIT)". Engine tidak memahami urutan kumpul; WO Set dijadwalkan terpisah tanpa urutan antar set (tanggal mulai SET 8 .. SET 1 berselisih beberapa hari hanya karena kapasitas mesin).
7. **Kebutuhan tinta 0 (FAKTA).** Semua baris TINTA di BOM `qty_required = 0,0 KG` (gate tidak pernah menahan; ATTENTION-ENGINE-06). Tali pack / plastik pack 0 untuk part non-terakhir.
8. **Artwork YAMA Part A tidak ada (FAKTA).** Kartu aslinya hanya Part B. Sesuai aturan "tanpa Master Card lengkap tidak ada WO", WO YAMA dihapus dan baris kirim CO2600023 dibiarkan belum di-Post sampai artwork Part A tersedia.
9. **HVS 70 gsm tidak ada di master bahan (FAKTA).** Kategori Paper > HVS hanya punya 58 / 60 / 85 / 90 gr; Buku memakai kategori level 2 (HVS) dengan gsm 70. Baris "PLASTIK LAMINASI (belum ada di Master Bahan)" muncul di BOM Slider dan Power X: item plastik laminasi untuk lebar yang dibutuhkan tidak ada di master.
10. **Komori GL440 BREAKDOWN di ddt* (FAKTA).** `prd_wo_operation_machine.reason = "Machine status: BREAKDOWN"`; seluruh CETAK offset (sheet 970 x 595) jatuh ke mesin digital (Xenons, Taimes T5S, Sunthink) karena `prod_process_machine` tidak punya batas sheet / gsm (semua null) dan saringan hanya memakai batas mesin. Mesin cetak yang dipilih officer di layout (printMachineId = Komori) diabaikan solver. DUGAAN: status BREAKDOWN berasal dari dummy maintenance.
11. **Master Card Collection tidak otomatis PRODUCT saat WIN (FAKTA).** `pendingDeliveries` (picker Create WO) hanya menawarkan produk yang kartu Collection-nya berstatus PRODUCT; WIN lewat Quotation tidak mengubahnya. Seeder menautkan kartu secara eksplisit (kartu #19-#25).
12. **Customer Bakpao (FAKTA).** Kartu menulis "PAK PURNA (BLITAR)"; master tidak punya customer itu. Dipakai GEMMOLIN (id 648, merek di kode pisau); Pak Purna dicatat sebagai attn.

## 5. Catatan data yang saya tambahkan

Tidak ada baris master diubah. Customer: semua sudah ada (712, 790, 651, 67, 648, 718). Tidak ada customer baru yang perlu dibuat.
