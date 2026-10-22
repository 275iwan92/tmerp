---
name: tmerp-ui-mockup
description: Buat mockup layar TMERP (form/grid/nota/dll) sebagai HTML yang mengikuti standar UI proyek, lalu render ke file JPEG dan kirim ke user untuk persetujuan. Pakai saat user minta mockup, desain layar baru, atau saat rencana kerja menyimpang dari mockup/layar lama (deviasi WAJIB ditunjukkan sebagai JPEG dulu sebelum coding).
---

# TMERP UI Mockup → JPEG

Tujuan: user melihat gambar (JPEG) SEBELUM layar dibangun sebagai layar v2 (`x-dl::*` / `x-mp::*`). Mockup adalah replika struktur + data dummy, bukan desain bebas (`docs/globalrules.yaml → mockup_arti`).

## Langkah

0. Ambil nama objek, props, dan tampilannya dari katalog devlib.test, `../devlib/src/Components`, dan `packages/mockup-parts` agar mockup memakai elemen yang sama dengan kode nanti (lanjutkan ke skill `devlib-oo` setelah JPEG disetujui).

1. **Tentukan jenis layar, baca standarnya** (jangan baca semua, lihat `docs/uistandardindex.md`):
   - Form/entry → `docs/FORM_UI_STANDARD.md` + `docs/uistandard/uistandard_component_form.md`
   - Grid/worklist/master → `docs/griduistandard.md` (tanpa header halaman, **tanpa kotak statistik**: satu baris saringan teks dengan garis bawah warna yang bergeser (disetujui user 2026-10-02), kolom identitas avatar+nama+kode, aksi Buka/Edit). **Wajib digambar sejak mockup:** ikon kolom Aksi Font Awesome BERBINGKAI (`fa-pen` Edit, `fa-ban`/`fa-circle-check` toggle, `fa-up-right-from-square` Buka, `fa-check` Confirm) — bukan `✎`/`⏻`.
   - Cetak/nota → `docs/printnotastandard.md`; Gantt → `ganttboardstandard.md`; Kanban → `kanbanboardstandard.md`
2. **Cek sumber acuan lebih dulu**: `docs/mockups/*.html`, `docs/attachments_index.md` (form kertas/Excel nyata), `docs/screens/screen_*.md`, dan layar v2 yang sudah ada (`resources/views/v2/screens`, devlib.test). Daftar SEMUA field/blok acuan; bangun persis seperti itu. Jangan mengarang field atau memakai ulang layar lama "karena lebih cepat".
3. **Salin `template.html`** (folder skill ini) ke `docs/mockups/<nama_layar>.html`, isi konten. Nama file ASCII, tanpa emoji/spasi.
4. **Render ke JPEG**:
   ```powershell
   powershell -File .claude/skills/tmerp-ui-mockup/render-jpeg.ps1 -Html docs/mockups/<nama>.html -Out docs/mockups/<nama>.jpg -Width 1440 -Height 900
   ```
   Butuh internet (`template.html` memuat CDN Tailwind + Font Awesome; hanya untuk gambar mockup, bukan kode aplikasi). Tinggi layar panjang → naikkan `-Height` agar seluruh halaman masuk.
5. **Lihat hasilnya sendiri** (Read file .jpg) dan periksa: teks terpotong, kolom tidak sejajar, warna/label salah. Perbaiki lalu render ulang. Jangan kirim gambar yang belum diperiksa.
6. **Kirim ke user** dengan `SendUserFile` (`display: render`), sebutkan asumsi dan hal yang perlu diputuskan. **Tunggu persetujuan** sebelum menulis kode layar v2.

## Deviasi dari mockup/layar lama

Bila perlu menyimpang dari acuan: render JPEG versi usulan, beri **lingkaran merah bernomor** pada tiap bagian yang berubah + legenda alasan di bawah/samping gambar (simpan sebagai `docs/mockups/<nama>_deviasi.jpg`). Tanpa persetujuan user, jangan bangun deviasinya.

## Aturan standar yang wajib terlihat di mockup

- Warna dan font mengikuti token DevLib (`--dl-*`) seperti tampilan `x-dl::*` di devlib.test; teks HITAM (latar terang) atau TERANG (latar gelap), tanpa abu-abu kecuali yang disabled (`--dl-disabled`).
- Header form dan tombol **Back** (label selalu "Back", ikon `fa-arrow-left`) meniru `x-dl::form`; aksi di panel atas form, tab Riwayat selalu ada.
- Tombol aksi satu kata baku, **selalu English**: New / Save / Undo / Delete / Confirm / Cancel / Duplicate / Back to Draft / Back. Hindari "Kembali ke Grid", "Submit", dsb. Pengecualian: "+ Add [objek]" untuk baris di dalam form.
- Status pakai badge (tone: success/warn/danger/info/abu); Confirm/Cancel/Close mengikuti `stat_confirm`/`stat_cancel`/`stat_close`.
- Grid: tanpa header halaman, tombol "+ New Record" di toolbar, kolom Aksi: Master = Edit + toggle aktif; non-Master = Buka + Confirm kondisional (tidak ada toggle aktif).
- **Input teks string** (LOCKED, user 2026-09-21): gambarkan field teks dengan **badge huruf `aa`/`Aa`/`AA`** di pojok kanan dalam kotak — itu bawaan field teks DevLib (`x-dl::field.text`), bukan hiasan opsional. Mockup TIDAK boleh menggambar input teks polos tanpa badge, kecuali field itu read-only/disabled, kotak CARI, atau bukan tipe teks. Bila field nilainya sudah ada di master, gambarkan sebagai **lookup** (dropdown/picker), bukan ketik bebas.
- **Warna field ikut token DevLib**: jangan hex abu-abu/biru literal — mockup yang datang dari AI lain sering membawa warna sendiri, samakan dulu ke token DevLib sebelum dirender. Detail: `docs/FORM_UI_STANDARD.md` §1b.
- Data dummy realistis (nama customer, No. dokumen berformat nyata), bukan "Lorem ipsum".
- Teks dan prosa UI bahasa Indonesia; nama field/kolom di DB Inggris snake_case bila ditampilkan.
- Semua komponen input sesuai katalog `<x-dl::field.*>` (date-picker kalender, bukan `<input type=date>` polos).

## Bersih-bersih

File `.html` dan `.jpg` mockup boleh tetap di `docs/mockups/` sebagai acuan (sesuai aturan proyek: jangan hapus dummy/acuan). File sementara render sudah dihapus otomatis oleh skrip.
