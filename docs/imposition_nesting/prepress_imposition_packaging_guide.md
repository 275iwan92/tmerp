# Arsitektur & Logika Optimasi Imposition & Nesting Packaging untuk ERP

Dokumen ini berisi dokumentasi teknis, algoritma matematika, serta referensi industri (mengadopsi logika standar dari **Esko ArtiosCAD**, **Esko/Tilia Phoenix**, **Insoft Imp**, dan **Heidelberg Prinect Signa Station**) yang dapat diintegrasikan sebagai modul bawaan (*embedded prepress tools*) ke dalam software ERP Percetakan Offset & Paper Packaging.

---

## 1. Referensi Konseptual Standar Industri

### A. Esko Phoenix (Tilia Labs Algorithm)
* **Inti Logika:** *Multi-Job Ganging & Dynamic Nesting Engine*.
* **Fitur Kunci:**
  * Optimasi gabungan (mencampur beberapa pesanan karton berbeda ukuran/oplah dalam 1 lembar plano).
  * Menghitung efisiensi lembaran (*Sheet Utilization %*) berdasarkan *Bounding Box* vs *True Polygon Contour*.
  * Menghitung biaya komparatif: Kertas vs Pembuatan Pisau Pond vs Waktu Ganti Mesin (Make-ready cost).

### B. Esko ArtiosCAD & EngView (Structural CAD & Die-Nesting)
* **Inti Logika:** *Interlocking Step & Repeat*.
* **Fitur Kunci:**
  * Pembalikan posisi dus (*Nested Tumble / 180° Rotation*) untuk saling mengunci lidah penutup (*tuck-in flap* dan *glue flap*).
  * Penyatuan garis potong (*Common Knife / Double Cut Elimination*) agar pisau tidak memotong area yang sama dua kali.

### C. Heidelberg Signa Station (Offset Production Constraints)
* **Inti Logika:** *Sheet Layout & Machine Gripper Margin*.
* **Fitur Kunci:**
  * Pengurangan area efektif plano berdasarkan:
    * **Gripper Edge (Margin Penjepit Mesin Offset):** umumnya 10–15 mm pada satu sisi panjang.
    * **Side Guide / Color Bar Margin:** 5–10 mm untuk kontrol densitas tinta (*spectrophotometer target*).
    * **Tail Margin:** 5–8 mm.
  * Arah serat kertas (*Grain Direction*) paralel terhadap silinder mesin cetak untuk mencegah kertas melengkung (*curling*).

---

## 2. Rumus Matematika Algoritma (Core Calculations)

### Modul A: Imposition Standar (Box Bounding / Regular Shapes)

Misalkan:
* Ukuran Lembar Plano Bersih: $W_{sheet} = W_{plano} - (	ext{Margin Kiri} + 	ext{Margin Kanan})$
* Panjang Lembar Plano Bersih: $H_{sheet} = H_{plano} - (	ext{Gripper} + 	ext{Tail Margin})$
* Ukuran Bentangan Dus + Bleed: $w_{box} = w + 2 	imes 	ext{bleed}$, $h_{box} = h + 2 	imes 	ext{bleed}$

#### 1. Orientasi Sejajar (Same Grain / Straight):
$$	ext{Up}_{X1} = \lfloor rac{W_{sheet}}{w_{box} + 	ext{gap}_x} floor, \quad 	ext{Up}_{Y1} = \lfloor rac{H_{sheet}}{h_{box} + 	ext{gap}_y} floor \implies 	ext{Total Up}_1 = 	ext{Up}_{X1} 	imes 	ext{Up}_{Y1}$$

#### 2. Orientasi Rotasi 90°:
$$	ext{Up}_{X2} = \lfloor rac{W_{sheet}}{h_{box} + 	ext{gap}_x} floor, \quad 	ext{Up}_{Y2} = \lfloor rac{H_{sheet}}{w_{box} + 	ext{gap}_y} floor \implies 	ext{Total Up}_2 = 	ext{Up}_{X2} 	imes 	ext{Up}_{Y2}$$

#### 3. Orientasi Kombinasi (*Combination / Guillotine Cut*):
Sisa ruang di sisi $X$ atau $Y$ dipartisi kembali untuk memuat unit dengan rotasi berbeda:
$$	ext{Sisa Lebar} = W_{sheet} - (	ext{Up}_{X1} 	imes (w_{box} + 	ext{gap}_x))$$
$$	ext{Up Tambahan} = \lfloor rac{	ext{Sisa Lebar}}{h_{box} + 	ext{gap}_x} floor 	imes \lfloor rac{H_{sheet}}{w_{box} + 	ext{gap}_y} floor$$

---

### Modul B: Kalkulasi Kebutuhan Kertas & Estimasi Biaya

1. **Total Lembar Cetak (Gross Sheets):**
   $$	ext{Gross Sheets} = \lceil rac{	ext{Target Oplah Quantity}}{	ext{Total Up Per Plano}} ceil + 	ext{Wastage Sheet (Inschiet/Insheet)}$$

2. **Perhitungan Berat Kertas (Kilogram) untuk ERP:**
   $$	ext{Berat (kg)} = rac{W_{plano}(	ext{m}) 	imes H_{plano}(	ext{m}) 	imes 	ext{GSM} 	imes 	ext{Gross Sheets}}{1000}$$

3. **Efisiensi Kertas (% Yield):**
   $$	ext{Efficiency \%} = rac{	ext{Total Up} 	imes (w_{box} 	imes h_{box})}{W_{plano} 	imes H_{plano}} 	imes 100\%$$

---

## 3. Fitur yang Disarankan untuk ERP Packaging Prep-Tools

1. **Pcs to Plano (Best Sheet Finder):**
   * Input: Dimensi bentangan dus ($L 	imes W$), Target Oplah.
   * Output: Rekomendasi ukuran kertas plano standar supplier (misal: 61x86, 65x100, 79x109 cm) yang menghasilkan *waste* terendah dan biaya termurah.

2. **Plano to Pcs (Up & Cut Optimizer):**
   * Input: Ukuran kertas plano yang tersedia di stok gudang, Ukuran bentangan dus.
   * Output: Jumlah *up* maksimum per lembar, skema potong plano sebelum masuk mesin cetak (misal: Plano 1/2, Plano 1/4).

3. **Packaging Nesting & Interlock Simulator:**
   * Fitur kalkulasi pitch dan interlocking flap untuk menghemat area bentangan hingga 8–20% pada karton model *auto-lock bottom* atau *tuck-end*.

4. **Kalkulator Pisau Pond (Die-line Rule Length Estimator):**
   * Menghitung total panjang pisau potong (*cutting rule*), pisau rel/lipat (*creasing rule*), dan pisau perforasi dalam satuan meter lari untuk perhitungan HPP pembuatan pisau pond.

5. **Grain Direction Checker:**
   * Validasi otomatis arah serat kertas terhadap garis lipatan utama dus agar tidak retak (*cracking*) saat proses *folding/gluing*.
