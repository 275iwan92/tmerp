# Dokumentasi Lengkap Sistem Kalkulasi HPP (Product Costing) \- ERP Printing & Packaging

Dokumentasi Lengkap Sistem Kalkulasi HPP (Product Costing) \- ERP Printing & Packaging  
• Form: frm\_hpp\_parts (+ child hpp\_parts\_coating)  
• Header Form: frm\_hpp (tabel hpp)  
• Core Engine: calc\_cogs() (PHP Method frm\_hpp\_parts v18)  
• Target Environment: Scriptcase PHP Method / Ajax Events / MySQL Database  
• Tanggal Update: 2026-09-02  
---

## I. Arsitektur Event-Driven Form frm\_hpp\_parts

Sistem menggunakan pola Event-Driven Architecture di mana setiap perubahan field penting pada antarmuka memicu event Ajax untuk memuat master data/tarif default, melakukan pre-kalkulasi lokal, menyimpan variabel global session Scriptcase, lalu memicu engine kalkulasi inti calc\_cogs().

```
                        ┌──────────────────────────────────────────────┐
                        │              FORM FRM_HPP_PARTS              │
                        └──────────────────────┬───────────────────────┘
                                               │
       ┌───────────────────────────────────────┼────────────────────────────────────────┐
       │ Inisialisasi UI                       │ Perubahan Tipe / Template              │ Field Input Spesifik
       ▼                                       ▼                                        ▼
[onLoad / Form Init]                [onChange {part_type}]                  [onChange Parameter]
  • set_labels()                      • getdef_by_type()                      • gramatur_id_onChange
    (Standarisasi label field)        • toggle_fields()                         (Lookup DC% -> calc_cogs)
                                        (TIDAK auto calc_cogs)                • c_uk_cetak_lebar_cm_onChange
                                                                                (Set [g_areacetak] -> calc_cogs)
                                    [onChange {part_name}]                    • i_isi_per_lb_onChange
                                      • getdef_vals()                           (Set [g_isiperlb] -> calc_cogs)
                                        (Load template spesifik,              • supplier_sheet_code_onChange
                                         TIDAK auto calc_cogs)                  (Tier disc, Lam flat -> calc_cogs)
                                                                              • sheet_rate_id_onChange
                                                                                (Lam flat, compare -> calc_cogs)
                                                                              • join_type_onChange
                                                                                (Lookup rate join -> calc_cogs)
                                                                              • packing_type_id_onChange
                                                                                (Lookup rate packing -> calc_cogs)
                                                                              • sf_ef_rate_id_onChange
                                                                                (Lookup rate SF EF -> calc_cogs)
                                               │
                                               ▼
                                  ┌─────────────────────────┐
                                  │      calc_cogs()        │
                                  │  (Engine Inti 10 Sesi)  │
                                  └────────────┬────────────┘
                                               │
                                               ├─► Output detail HPP/PC ({c_*})
                                               ├─► Output subtotal order ({c_i_*})
                                               ├─► Log debug ({description_calc})
                                               └─► compare_sheet_suppliers() ({c_sheet_compare_info})
                                               │
                                               ▼
                                  [Tabel Database: hpp_parts]
                                               │
                                               ▼
                                 [Method: update_hpp_totals]
                                               │
                                               ▼
                                   [Tabel Database: hpp]
                                 (Header: Total Material, 
                                  Process, Finishing, HPP)
```

---

## II. Katalog Lengkap Ajax Events

### 1\. part\_type\_onChange (v3)

• Fungsi: Mengatur tampilan form saat tipe part diubah.  
• Aksi:

* Memanggil getdef\_by\_type() untuk memuat default template berdasarkan m\_part\_type.default\_template\_id.  
* Memanggil toggle\_fields() untuk menyembunyikan/menampilkan field (misal menyembunyikan plano jika tipe SHEET).  
* Aturan Desain: Tidak memicu auto calc\_cogs() agar user dapat memeriksa dan mengubah parameter terlebih dahulu.

### 2\. part\_name\_onChange / getdef\_vals (v6)

• Fungsi: Memuat master template spesifik saat nama part/template dipilih.  
• Aksi: Lookup m\_part\_template berdasarkan template\_code \= {part\_name} dan populate 40+ field (dimensi, gramatur, ongkos proses, tarif tooling, sheet rate). Tidak auto calc\_cogs().

### 3\. gramatur\_id\_onChange (v5)

• Fungsi: Lookup tipe kertas dan persentase DC (Duplex Cut).  
• Aksi:

* Lookup m\_paper\_grammage by id\_cons\_grammage \= {gramatur\_id}.  
* Jika paper\_type \=== 'DUPLEX', maka {i\_dc\_persen\_override} \= round(duplex\_dc\_percentage / 100, 2\) (karena di database tersimpan × 100, misal 530 menjadi 5.30%).  
* Memanggil calc\_cogs().

### 4\. c\_uk\_cetak\_panjang\_cm\_onChange & c\_uk\_cetak\_lebar\_cm\_onChange

• Fungsi: Menghitung luas cetak bidang plano dan mengupdate session global.  
• Aksi:

* {c\_area\_cetak\_cm2} \= (float)({c\_uk\_cetak\_panjang\_cm} \* {c\_uk\_cetak\_lebar\_cm});  
* Menyimpan ke variabel session Scriptcase: \[g\_areacetak\] \= {c\_area\_cetak\_cm2};  
* Memanggil calc\_cogs().

### 5\. i\_isi\_per\_lb\_onChange

• Fungsi: Mengupdate isi lembar cetak dan session global.  
• Aksi:

* Menyimpan ke session: \[g\_isiperlb\] \= {i\_isi\_per\_lb};  
* Memanggil calc\_cogs().

### 6\. supplier\_sheet\_code\_onChange (v3)

• Fungsi: Lookup diskon tier supplier corrugated dan pre-kalkulasi tarif laminasi flat.  
• Aksi:

* Menghitung meter/luas order.  
* Lookup diskon tier dari m\_sheet\_discount\_tier.  
* Jika tipe DC+SHEET atau DC+SF, menghitung {i\_lam\_sheet\_flat\_pc}: $$lam \= Index\_{substansi} \\times \\left(\\frac{L\_{trim}}{100}\\right) \\times \\left(\\frac{P}{100}\\right) \\times \\frac{1}{I\_{trim}} \\times (1 \- Discount)$$  
* Memanggil compare\_sheet\_suppliers() dan calc\_cogs().

### 7\. sheet\_rate\_id\_onChange (v3)

• Fungsi: Mengambil indeks substansi dari v1\_sheet\_rate saat substansi sheet diubah.  
• Aksi:

* Lookup v1\_sheet\_rate (cross-schema safe).  
* Menghitung nilai preview {i\_lam\_sheet\_flat\_pc} untuk tipe DC+SHEET dan DC+SF.  
* Memanggil compare\_sheet\_suppliers() dan calc\_cogs().

### 8\. join\_type\_onChange (v1)

• Fungsi: Auto-fill ongkos join (jahit/lem koli box) dari m\_join\_type.  
• Aksi:

* Lookup default\_rate dari m\_join\_type where code \= {join\_type}.  
* Mengisi {i\_ongkos\_join\_pc} (user tetap diizinkan meng-override).  
* Memanggil calc\_cogs().

### 9\. packing\_type\_id\_onChange

• Fungsi: Auto-fill ongkos packing dari m\_packing\_type.  
• Aksi:

* Lookup default\_rate dari m\_packing\_type where id\_packing \= {packing\_type\_id}.  
* Mengisi {i\_ongkos\_packing\_pc}.  
* Memanggil calc\_cogs().

### 10\. sf\_ef\_rate\_id\_onChange

• Fungsi: Auto-fill harga rate Single Face EF dari v1\_sf\_ef\_rate.  
• Aksi:

* Lookup rate dari v1\_sf\_ef\_rate where id\_sf \= {sf\_ef\_rate\_id}.  
* Mengisi {i\_sf\_ef\_rate\_override}.  
* Memanggil calc\_cogs().

---

## III. Katalog Method PHP (Scriptcase Programming Methods)

* set\_labels() (v9): Standarisasi label UI semua field input, proses, tooling, dan output.  
* getdef\_by\_type() (v3): Mengambil relasi default template dari m\_part\_type lalu mengisi 40 parameter proses/tarif dari m\_part\_template.  
* getdef\_vals() (v6): Mengambil seluruh konfigurasi template dari m\_part\_template berdasarkan kode template {part\_name}.  
* compare\_sheet\_suppliers() (v3/v4): Membandingkan harga semua supplier sheet aktif berdasarkan running meter order dan menampilkannya di {c\_sheet\_compare\_info}.  
* calc\_cogs() / frm\_hpp\_parts (v18): Mesin kalkulasi detail 10 sesi untuk HPP/PC dan Total Order.  
* update\_hpp\_totals(): Agregasi total biaya material, proses, finishing, dan HPP dari detail parts ke master header hpp.

---

## IV. Formulasi Matematis Terpadu (Sesi 0 s/d 10\)

### 1\. Guard Pembagi & Area Cetak

$$Q\_{part} \= \\max(1, Q\_{part}), \\quad C\_{plano} \= \\max(1, C\_{plano}), \\quad I\_{lb} \= \\max(1, I\_{lb})$$  
$$A\_{cetak} \= P\_{cetak} \\times L\_{cetak} \\quad (cm^2)$$  
$$is\\\_sheet \= \\begin{cases} 1, & \\text{jika } T\_{part} \\in \\{\\text{'SHEET'}, \\text{'SHEET\\\_DIGITAL'}\\} \\\\ 0, & \\text{lainnya} \\end{cases}$$

### 2\. Material Cost

#### A. Duplex Paper

• Price List Plano Duplex:  
$$F\_{dc} \= \\begin{cases} 1 \+ O\_{dc\\\_override}, & \\text{jika override} \> 0 \\\\ 1 \+ \\left(\\frac{\\%\_{dc}}{100}\\right), & \\text{lainnya} \\end{cases}$$  
$$Cost\_{paper/pc} \= \\left(\\frac{P\_{plano} \\times L\_{plano}}{8611}\\right) \\times P\_{duplex} \\times F\_{dc} \\times \\frac{1}{500 \\times C\_{plano} \\times I\_{lb}}$$  
• Ivory / Art Paper (Harga/Kg):  
$$Cost\_{paper/pc} \= \\left(\\frac{P\_{plano} \\times L\_{plano}}{10.000}\\right) \\times H\_{kg} \\times \\left(\\frac{G}{2}\\right) \\times \\frac{1}{500 \\times C\_{plano} \\times I\_{lb}}$$

#### B. Kraft Paper

$$Cost\_{paper/pc} \= \\left(\\frac{P\_{plano} \\times L\_{plano}}{10.000}\\right) \\times H\_{kg} \\times \\left(\\frac{G}{2}\\right) \\times \\frac{1}{C\_{plano} \\times I\_{lb}}$$

#### C. Derivasi Geometri SHEET\_DIGITAL

$$P\_{sheet} \= \\frac{2(P\_{box} \+ L\_{box}) \+ t\_p \+ L\_{lidah}}{10}, \\quad L\_{sheet} \= \\frac{(L\_{box} \+ T\_{box}) \+ t\_l}{10} \\quad (cm)$$  
$$I\_{trim} \= \\max\\left(1, \\left\\lfloor \\frac{180 \- 2}{L\_{sheet}} \\right\\rfloor\\right), \\quad W\_{trim} \= \\left\\lceil \\frac{(L\_{sheet} \\times I\_{trim}) \+ 2}{5} \\right\\rceil \\times 5 \\quad (cm)$$  
$$RM \= \\left(\\frac{Q\_{part}}{I\_{lb}}\\right) \\times \\frac{1}{I\_{trim}} \\times \\left(\\frac{P\_{sheet}}{100}\\right) \\quad (\\text{meter lari})$$  
$$Cost\_{sheet/pc} \= \\left(\\frac{W\_{trim} \\times P\_{sheet}}{10.000}\\right) \\times \\frac{Index\_{substansi}}{I\_{trim}} \\times (1 \- Disc\_{factor})$$

### 3\. Proses, Finishing, & Tooling

• Cetak Offset: Cost\_{cetak/pc} \= \\frac{Ongkos\_{cetak/rim}}{500 \\times C\_{plano} \\times I\_{lb}}  
• Plong Offset: Cost\_{plong/pc} \= \\frac{Ongkos\_{plong/lb}}{I\_{lb}}  
• Coating: Cost\_{total\\\_coating/pc} \= \\sum \\frac{A\_{cetak} \\times Index\_{coating}}{I\_{lb}}  
• SF EF ($J\_{37}$): J\_{37} \= \\left(\\frac{A\_{cetak}}{10.000}\\right) \\times Rate\_{sf} \\times \\frac{1}{I\_{lb}} (Otomatis untuk DC+SF & DC+SHEET)  
• Laminasi ($J\_{36} \+ J\_{38}$):  
$$J\_{36} \= F\_{sheet} \\times Ongkos\_{lam\\\_flat/pc}$$  
$$J\_{38} \= \\frac{P \\times L \\times (F\_{sheet} \\times R\_{lam\\\_sh} \+ F\_{sf} \\times R\_{lam\\\_sf})}{I\_{lb}}$$  
• Tooling:  
$$Cost\_{tooling/pc} \= \\frac{F\_{film}^2 \\cdot A\_{cetak} \\cdot Rate\_{film} \+ Rate\_{plate} \\cdot \\Sigma\_{warna} \+ F\_{pisau} \\cdot Rate\_{pisau}}{Q\_{part}}$$

### 4\. Akumulasi Total

• HPP per PC Sheet:  
$$Cost\_{process/pc} \= (2 \\times Print) \+ Diecut \+ Slitter \+ Slotter \+ Join \+ Partisi \+ Layer \+ Pisau \+ QC \+ Kirim \+ Packing$$  
$$\\mathbf{HPP\_{pc} \= Cost\_{sheet/pc} \+ Cost\_{process/pc}}$$  
• HPP per PC Offset:  
$$Cost\_{process/pc} \= Cetak \+ Plong \+ Lem \+ Hotprint \+ Kupas \+ QC \+ Kirim \+ Packing \+ Lam \+ SFEF \+ Coating$$  
$$\\mathbf{HPP\_{pc} \= Cost\_{paper/pc} \+ Cost\_{process/pc} \+ Cost\_{tooling/pc}}$$  
• Total Order: Total\_{X} \= round(X\_{pc} \\times Q\_{order}, 2\)  
---

## V. Audit & Temuan Teknis Kritis

* Inkonsistensi Rumus Running Meter ($RM$) pada 3 Titik:  
  * Pada calc\_cogs() (v18): RM \= \\frac{Q}{I\_{lb} \\times I\_{trim}} \\times \\frac{P\_{sheet}}{100} → Paling akurat (panjang tarikan corrugator).  
  * Pada compare\_sheet\_suppliers() (v3): RM \= \\left(\\frac{W\_{trim}}{100}\\right) \\times \\left(\\frac{P}{100}\\right) \\times \\left(\\frac{Q}{I\_{trim}}\\right) → Menghitung Luas ($m^2$), bukan meter lari.  
  * Pada supplier\_sheet\_code\_onChange (v3): RM \= \\left(\\frac{W\_{trim}}{100}\\right) \\times \\left(\\frac{P}{100}\\right) \\times (Q \\times I\_{trim}) → Nilai membengkak karena menggunakan pengali (\\times I\_{trim}).  
  * Rekomendasi: Samakan seluruh rumus RM menggunakan rumus meter lari corrugator:

```php
$rm = ($qty / $isi_trim) * ($panjang / 100);
```

* Agregasi Material Sheet di update\_hpp\_totals():  
  * Query master header saat ini hanya menjumlahkan c\_paper\_cost\_pc.  
  * Perbaikan:

```sql
SELECT COALESCE(SUM((COALESCE(c_paper_cost_pc,0) + COALESCE(c_sheet_cost_pc,0)) * COALESCE(qty_part,0)),0) AS total_material_cost
```

* Format Diskon / Surcharge:  
  * Jika diskon disimpan sebagai angka persen (misal 5 untuk 5% atau \-5 untuk surcharge), pastikan selalu dibagi 100 sebelum diaplikasikan ke rumus (1 \- Disc) agar harga tidak membengkak menjadi 600%.  
* Kepatuhan Arsitektur Scriptcase:  
  * Hindari operator ternary (? :) pada event Ajax karena dapat memutus serialisasi JSON response Scriptcase.  
  * Selalu gunakan is\_array() guard pada hasil sc\_lookup().

* 

## VI. Matriks Komparasi Deep-Dive Berdasarkan Tipe Part

Berdasarkan dropdown sistem, terdapat 6 tipe part utama dengan karakteristik komponen biaya sebagai berikut:

| Komponen Biaya | DC | DC+SHEET | DC+SF | KRAFT | SHEET / DIGITAL |
| ----- | :---: | :---: | :---: | :---: | :---: |
| Material Paper | Ya | Ya | Ya | Ya | Tidak |
| Material Sheet | Tidak | Ya (Laminasi) | Ya (Laminasi) | Tidak | Ya |
| Proses Offset | Ya | Ya | Ya | Tidak | Tidak |
| Proses Corrugated | Tidak | Ya | Ya | Ya | Ya |
| Coating & Tooling | Ya | Ya | Ya | Tidak | Tidak |

### 1\. Formula Eksplisit Tiap Tipe

**• Tipe DC (Duplex Carton):**  
$$HPP \= Cost\_{paper/pc} \+ Cost\_{cetak} \+ Cost\_{plong} \+ Cost\_{coating} \+ Cost\_{tooling} \+ Cost\_{others}$$  
**• Tipe DC \+ SHEET & DC \+ SF:**  
$$HPP \= Cost\_{paper/pc} \+ Cost\_{cetak} \+ J\_{36} (Laminasi\\ Flat) \+ J\_{37} (SF\\ EF) \+ Cost\_{plong} \+ Cost\_{tooling}$$  
**• Tipe KRAFT:**  
$$HPP \= Cost\_{paper/pc} \+ Cost\_{process/pc} (Corrugated)$$  
**• Tipe SHEET & SHEET\_DIGITAL:**  
$$HPP \= Cost\_{sheet/pc} \+ Cost\_{process/pc}$$

### 2\. Studi Kasus Audit: DC \+ SHEET

Berdasarkan data audit sistem, ditemukan struktur biaya sebagai berikut:

* Kertas: Rp 1.608,11 (Masuk ke *c\_paper\_cost\_pc*)  
* Laminasi Sheet: Rp 1.790,50  
* Coating: Rp 473,55  
* Proses: Rp 3.645,50  
* Tooling: Rp 595,76  
* **Total HPP: Rp 6.322,92**

**Analisis Komponen Sheet:**  
Pada tipe DC+SHEET, nilai *{c\_sheet\_cost\_pc}* bernilai 0 karena secara arsitektur, harga lembar karton gelombang (corrugated sheet) yang ditempelkan ke duplex dianggap sebagai biaya **Laminasi Sheet Flat** ({i\_lam\_sheet\_flat\_pc} \-\> *c\_ongkos\_lam\_pc*). Hal ini disebabkan karena sheet tersebut berfungsi sebagai material tambahan dalam proses finishing tempel (laminasi), bukan sebagai material utama pembentuk body box seperti pada tipe SHEET.