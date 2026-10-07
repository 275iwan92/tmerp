---
name: devlib-svg-cad
description: Membuat SVG bergaya CAD / blueprint / gambar teknik (dieline, denah, skema, garis dimensi, grid, zoom-pan, HUD koordinat). Pakai saat diminta menggambar SVG teknis atau membangun kanvas CAD interaktif. Tidak terikat tmerp (berlaku untuk DevLib umum).
---

# DevLib SVG CAD

Objek siap pakai: `x-dl::blueprint` (`Devlib\Components\Blueprint`, JS `blueprint.js`). **Perluas objek itu dulu**, jangan gambar dari nol. Proyek tmerp: renderer dieline `TMDieline` beku, jangan disentuh.

## 1. Alur kerja (spek dulu, bukan menebak)
1. Tetapkan **satuan dunia** (mm) dan skala; semua koordinat = satuan dunia, bukan piksel.
2. Tulis `viewBox="x y w h"` dari spek; `width/height="100%"`, `preserveAspectRatio="xMidYMid meet"`. Jangan ukuran piksel tetap.
3. Gambar dalam layer `<g>` bernama: `grid`, `cut`, `crease`, `perf`, `bleed`, `dim`, `hud`.
4. Validasi (bagian 5), render PNG, dan **lihat hasilnya** sebelum menyerahkan.

## 2. Viewport, zoom, pan
- Zoom/pan = ubah 4 angka `viewBox` (zoom: kecilkan w/h di sekitar kursor; pan: geser x/y). Jangan pindahkan objek satu per satu.
- Konversi layar -> dunia: `svg.createSVGPoint()` + `getScreenCTM().inverse()`. Dipakai untuk HUD dan zoom ke kursor.

## 3. Garis dan grid
- **`vector-effect="non-scaling-stroke"`** pada semua garis (grid, dimensi, potong/lipat) supaya tebal tetap dalam piksel layar saat zoom.
- Grid = **dua `<pattern>` terpisah** (minor 10, mayor 50/100 satuan dunia) ditumpuk di dua `<rect>`. JANGAN taruh path 100x100 di dalam pattern 20x20 (terpotong, garis mayor tidak muncul).
- Stroke tipis 1-1.5px. Warna dari token CSS (`--bp-*`), bukan hex di markup.
- Konvensi garis: potong = solid; lipat = putus-putus (`stroke-dasharray`); perforasi = titik-titik; bleed = tipis, warna lain.
- Snap garis piksel bila dirender kecil: koordinat bilangan bulat + 0,5.

## 4. Komponen CAD wajib
- **Garis dimensi**: `<line>` dengan `marker-start/end` (panah di `<defs>`, `orient="auto-start-reverse"`), garis bantu, label di tengah (`text-anchor="middle"`) dengan latar (`paint-order:stroke`). Nilai label dihitung dari koordinat (`hypot`), bukan diketik.
- **Crosshair** mengikuti kursor + **HUD** pojok (monospace): X, Y, zoom.
- Tombol: Fit (reset viewBox), zoom +/-.

## 5. Validasi (mekanis)
- `xmlns="http://www.w3.org/2000/svg"` ada; path tertutup; semua id di `<defs>` dipakai; tidak ada elemen di luar viewBox kecuali disengaja.
- Teks: font di elemen root; **jangan** simbol Unicode (kotak kosong); cek teks tidak meluap.
- Interaktif: bungkus SVG dalam komponen dengan JS ringan (`pointermove` untuk HUD).

## 6. Estetika blueprint (default)
Latar `#121824`, grid minor `#232e44`, mayor `#2c3a56`; garis biru blueprint / hijau listrik / amber; HUD monospace. Semuanya custom property di CSS komponen (`.dl-bp`), mudah diganti tema.