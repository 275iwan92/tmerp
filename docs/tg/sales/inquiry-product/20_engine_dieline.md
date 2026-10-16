# Kartu ENGINE: Dieline Engine (TMDieline)

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Sumber: `resources/js/tmdieline.js` (176 baris) dan `app/Support/Dieline/TmDieline.php` (332 baris, port PHP) dibaca; tes `TmDielineTest` dijalankan (3 lulus, 8 assert). Kesamaan JS vs PHP diuji sendiri dengan node v24 terhadap 27 tipe produk aktif (hasil di bawah).

- **Nama kanonik**: Dieline Engine (TMDieline). **Berkas (registry key `dieline`, mode `calc`, grup Inquiry Product)**: `resources/js/tmdieline.js`. **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product.
- **Tiga salinan kode yang sama** (FAKTA): `resources/js/tmdieline.js`; `packages/mockup-parts/resources/assets/tmdieline.js` (isi identik, `diff` kosong); port PHP `App\Support\Dieline\TmDieline`. Registry hanya mendaftarkan yang pertama.
- **Status beku**: kemampuan render dieline = DIKUNCI Jo ("sudah ada & diverifikasi bagus, jangan diubah"; `docs/claude_vital_flows.md`). Jangan ubah geometri tanpa izin Jo.
- **Sinonim**: dieline, pisau, bentangan (flat), DSL `dieline_spec`, TMDieline.
- **BUKAN sama dengan**: `ImpositionEngine` (menyusun bentangan di plano); **BUKAN** tipe produk (`prod_type.type_code`, lihat `10_kartu_tipe-produk.md`): tipe produk adalah data resep, engine ini membacanya.

## Pemicu (tidak ada background / jadwal / command)
Tidak ada di `routes/console.php`, Jobs, Observers. Semua dipicu oleh tampilan di browser (JS) atau oleh kode server (PHP). FAKTA, dari grep:
| Pemicu | Method | Berkas:baris |
|---|---|---|
| Form Inquiry v2: dimensi/tipe diubah (komponen `x-mp::dieline-v3` di `x-mp::sales.inquiry-part`) | `flat`, `render`, `STROKE` | `packages/mockup-parts/resources/assets/js/components/dieline-x.js:116-119` (`redraw`); dipakai `Sales/InquiryPart.php:136`, `Sales/PartVisual.php:55` |
| ProDev: panel Part & Dieline | sama | `packages/mockup-parts/src/Components/Prodev/PartDieline.php:75`; simpan lewat `PartDieline::save` (`app/Services/ProDev/PartDieline.php`), rute `routes/v2.php` (`prodev.dieline`) |
| Master Card Collection | sama | `resources/views/v2/screens/master-card-collection.blade.php:144` |
| Form/Recalc/Nota/Hub Inquiry v1, cetak Quotation | `svg`, `render` | `inquiry/form.blade.php:272`, `recalc.blade.php:302`, `nota.blade.php:472`, `hub.blade.php:33`, `quotation/print.blade.php:80`, `components/inquiry/readonly-summary.blade.php:224` |
| ProDev (nesting menggambar blank) | `svg` | `production/prodev.blade.php:1593`, `:3695` |
| Kotak 3D | `STROKE` | `resources/js/tm-box3d.js:17` |
| Impor Master Card massal (server) | `TmDieline::flat`, `::svg` | `app/Services/MasterCard/MasterCardShape.php:98`, `:133`, `:142` (dipanggil `MasterCardService`, `MasterCardDummyEngine`) |
`resources/js/app.js:1` memuat engine untuk halaman v1. Jembatan Livewire: `resources/js/dieline-part.js:65,85`.

## Alur (kembaran teks, tanpa diagram: satu jalur lurus)
1. Layar mengirim `spec` (resep tipe produk, JSON `prod_type.dieline_spec`) dan `dims` (ukuran yang diketik/pilih).
2. `buildScope`: nilai `dims` menjadi variabel; singkatan A/B/C/D/LH/G/TT/DF/BT/P dipetakan ke nama panjangnya; lalu variabel turunan `spec.vars` dievaluasi berurutan.
3. `flat`: evaluasi `spec.flat.w` dan `spec.flat.h`, bulatkan 2 desimal, hitung `area`.
4. `svg`: evaluasi `spec.canvas.w/h`; bila bukan > 0, tidak menggambar. Tiap elemen `spec.elements` digambar berurutan (line, rect, circle, tuck, dust, glue, bleed, foldlines, holes, text). Elemen yang gagal dilewati diam-diam.
5. `render` menaruh SVG di elemen, atau pesan "Tipe ini belum punya spesifikasi dieline" / "Pilih ukuran / isi dimensi untuk melihat dieline".
6. Bentangan (flat) w/h/area dipakai layar untuk mengisi kotak ukuran datar; officer boleh mengisi manual (centang `manual`, `dieline-x.js:51,116`).
7. Saat Inquiry disimpan, `InquiryWriter::persistParts` membekukan resep + dims + `type_code` ke `iqp_part.dieline_spec` (`InquiryWriter.php:249-258`, snapshot A.13); engine tidak menyimpan.

## Input / Output
- **Input**: `prod_type.dieline_spec` (JSON, 27 dari 28 tipe aktif punya `elements`; FAKTA, query), pilihan sah dibaca `Sales::dielineTypes()` (`app/Support/V2/Data/Sales.php:112`, hanya tipe aktif dengan `elements`); parameter `prod_type_param`, ukuran pasaran `prod_type_size`. Tidak membaca `sys_constant`.
- **Output**: tidak menulis DB. `flat` -> `{w, h, area}` (mm, mm2); `svg` -> teks SVG atau `null`; `render` -> mengisi elemen. Yang menyimpan: `InquiryWriter` (`iqp_part.dieline_spec`, `flat_w`, `flat_h`, `flat_area`) dan `PartDieline::save` (`iqp_part` + `prod_part`, `cm_length/width/height` = dims/10).
- **Siapa boleh menimpa**: officer lewat form (Inquiry; ProDev berhak mengubah dieline part, A.17).

## FORMULA
Satuan mm. Tanpa pembulatan kecuali `nf`/`flat` (2 desimal). Rumus ditulis di DATA (`dieline_spec`), bukan di kode; engine hanya mengevaluasinya.
1. Evaluator ekspresi (`evalExpr`): operator `+ - * /`, minus tunggal, tanda kurung, fungsi `max min abs sqrt round floor ceil`, konstanta `PI`. Variabel tak dikenal atau tak berhingga = 0. **Pembagian dengan 0 = 0** (baris 88). Hasil non-angka = 0.
2. `flat.w`, `flat.h` = hasil ekspresi `spec.flat.w/h` (mis. RSC_2PC: `G+A+B+2*P`, `C+B+2*flap_extra+2*P`), dibulatkan `round(x*100)/100`; `area = round(w*h*100)/100`.
3. Warna garis tetap: CUT `#ff3b3b`, CREASE `#00e676`, BLEED `#888` putus-putus `5 3`, HOLE `#4da3ff`; margin viewBox `max(24, (W+H)/16)`.
4. Ukuran bleed (`draw_bleed`): persegi dari `-p` selebar `W+2p` x `H+2p`; tidak digambar bila `p <= 0`.
5. `tuck`: tutup lengkung dengan radius `min(8, (b-a)/6)`; `dust`: trapesium dengan inset `min(h, (b-a)/3)`; `glue`: lidah lem trapesium sepanjang `g`.
6. Port PHP sama: `flat` memakai `round(...,2)`, evaluator sama (`TmDieline::eval`).

**Contoh angka (dihitung sendiri; dims A=200, B=150, C=100, G=20, P=3, lainnya dari parameter tipe, `flap_extra` tidak diisi = 0)**
| Tipe | Bentangan | Cek tangan |
|---|---|---|
| RSC_2PC | 376 x 256 mm, area 96.256 | `G+A+B+2P` = 20+200+150+6 = 376; `C+B+0+2P` = 100+150+6 = 256 |
| CARD_BOX | 726 x 146 mm, area 105.996 | `G+2A+2B+2P` = 20+400+300+6 = 726; `max(TT,DF)+C+max(BT,DF)+2P` dengan TT=20, DF=15, BT=20 = 20+100+20+6 = 146 |
**Uji kesamaan JS vs PHP (dijalankan sendiri)**: 27 tipe aktif ber-spec, dims di atas; `flat` dan teks `svg` hasil `tmdieline.js` (node) dan `TmDieline` (PHP) **identik byte untuk byte untuk 27 dari 27**, tidak ada SVG null. Skrip uji berada di folder sementara sesi (bukan bagian repo).

## Kasus tepi
- Tipe tanpa spec / `elements`: `svg` = null, layar menampilkan pesan; satu tipe aktif tidak punya spec (28 aktif, 27 ber-spec). `persistParts` tidak menyimpan `type_code` bila spec kosong (lihat `10_kartu_tipe-produk.md`).
- Ekspresi salah sintaks pada `vars` atau elemen: nilai 0 / elemen dilewati tanpa pesan (`catch` kosong, baris 98, 164). Salah ketik di resep tidak terlihat oleh officer.
- Dims bukan angka (`parseFloat` NaN) = 0.
- `text` elemen memakai warna isi `#9aa` tetap (baris 139); `esc()` dipakai untuk isi teks.

## Pemakai (relasi)
- **sumber**: master `prod_type` (+ `_param`, `_size`). **dipakai_oleh**: Inquiry Product (New/Revise/Recalc), ProDev Part & Dieline, Master Card Collection, cetak Quotation, kotak 3D. **hilir**: `iqp_part.dieline_spec`/`flat_*` -> ImpositionEngine (ukuran awal), SpecEngine (`blankArea`: `flat_w*flat_h/100` bila `die_*_cm` kosong), Master Card (SVG dieline wajib diisi saat impor).

## Butir terbuka
Tidak ada butir ATTENTION-ENGINE khusus engine ini (dibaca penuh).

## Temuan (dicatat, tidak diperbaiki)
1. Registry (`EngineRegistry.php:79-105`) hanya mendaftarkan berkas JS; tidak menyebut port PHP `TmDieline` (dipakai impor Master Card massal) maupun salinan di `packages/mockup-parts`. Perubahan di satu salinan harus diulang manual di dua lainnya (komentar `TmDieline.php:11` memerintahkan "ubah salah satu = ubah keduanya"; `TmDielineTest` menjaga hanya 3 kasus). Hasil uji sesi ini 27/27 identik saat ini.
2. Registry menulis `flat` mengembalikan "width and height"; kode mengembalikan juga `area`. Ekspor `eval`, `scope`, `STROKE` (baris 175) tidak tercatat di registry (`STROKE` dipakai tm-box3d dan dieline-x).
3. Kegagalan evaluasi ekspresi disembunyikan (nilai 0, `catch` kosong); tidak ada log.
4. Warna garis dan `#9aa` di renderer adalah hex literal di JS/PHP, bukan token (aturan UI: hitam/terang). Ini warna semantik garis dieline (potong/lipat/bleed), keputusan desain ada pada Jo.
5. Tes PHP (`TmDielineTest`) menyebut kesamaan 27 tipe x 2 ukuran sudah dicek (2026-10-01): DARI DOKUMEN/komentar; sesi ini mengujinya ulang 27 tipe x 1 ukuran (lihat atas).

## Tes
`tests/Unit/TmDielineTest.php` (3 tes: bentangan sama dengan pisau Master Card nyata untuk 3 kartu, SVG PHP sama dengan JS untuk satu kasus, evaluator ekspresi). Tidak ada tes JS.
