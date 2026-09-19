---
name: tmerp-ui-mockup
description: Buat mockup layar TMERP (form/grid/nota/dll) sebagai HTML yang mengikuti standar UI proyek, lalu render ke file JPEG dan kirim ke user untuk persetujuan. Pakai saat user minta mockup, desain layar baru, atau saat rencana kerja menyimpang dari mockup/layar lama (deviasi WAJIB ditunjukkan sebagai JPEG dulu sebelum coding).
---

# TMERP UI Mockup → JPEG

Tujuan: user melihat gambar (JPEG) SEBELUM layar dibangun di Livewire. Mockup adalah replika struktur + data dummy, bukan desain bebas (`docs/globalrules.yaml → mockup_arti`).

## Langkah

0. Ambil nama komponen, props, dan tone dari `resources/views/components/_manifest.yaml` agar mockup memakai elemen yang sama dengan kode nanti (lanjutkan ke skill `tmerp-screen` setelah JPEG disetujui).

1. **Tentukan jenis layar, baca standarnya** (jangan baca semua, lihat `docs/uistandardindex.md`):
   - Form/entry → `docs/FORM_UI_STANDARD.md` + `docs/uistandard/uistandard_component_form.md`
   - Grid/worklist/master → `docs/griduistandard.md` (tanpa header halaman, stat box pastel, kolom identitas avatar+nama+kode, aksi Buka/Edit)
   - Cetak/nota → `docs/printnotastandard.md`; Gantt → `ganttboardstandard.md`; Kanban → `kanbanboardstandard.md`
2. **Cek sumber acuan lebih dulu**: `docs/mockups/*.html`, `docs/attachments_index.md` (form kertas/Excel nyata), `docs/screens/screen_*.md`, dan layar Livewire yang sudah ada. Daftar SEMUA field/blok acuan; bangun persis seperti itu. Jangan mengarang field atau memakai ulang layar lama "karena lebih cepat".
3. **Salin `template.html`** (folder skill ini) ke `docs/mockups/<nama_layar>.html`, isi konten. Nama file ASCII, tanpa emoji/spasi.
4. **Render ke JPEG**:
   ```powershell
   powershell -File .claude/skills/tmerp-ui-mockup/render-jpeg.ps1 -Html docs/mockups/<nama>.html -Out docs/mockups/<nama>.jpg -Width 1440 -Height 900
   ```
   Butuh internet (Tailwind CDN + Font Awesome CDN). Tinggi layar panjang → naikkan `-Height` agar seluruh halaman masuk.
5. **Lihat hasilnya sendiri** (Read file .jpg) dan periksa: teks terpotong, kolom tidak sejajar, warna/label salah. Perbaiki lalu render ulang. Jangan kirim gambar yang belum diperiksa.
6. **Kirim ke user** dengan `SendUserFile` (`display: render`), sebutkan asumsi dan hal yang perlu diputuskan. **Tunggu persetujuan** sebelum menulis kode Livewire.

## Deviasi dari mockup/layar lama

Bila perlu menyimpang dari acuan: render JPEG versi usulan, beri **lingkaran merah bernomor** pada tiap bagian yang berubah + legenda alasan di bawah/samping gambar (simpan sebagai `docs/mockups/<nama>_deviasi.jpg`). Tanpa persetujuan user, jangan bangun deviasinya.

## Aturan standar yang wajib terlihat di mockup

- Token: navy `#2c3e69`, accent `#2c7be5`, muted `#8a94a6`; font Instrument Sans.
- Header form: navy sticky, tombol **Back** pill `#43508a` (label selalu "Back", ikon `fa-arrow-left`, lingkaran putih).
- Tombol aksi satu kata baku: **Simpan / Batal / Tutup** (UI berbahasa Indonesia). Hindari "Kembali ke Grid", "Submit", dsb. Pengecualian: "+ Tambah [objek]" untuk baris di dalam form.
- Status pakai badge (tone: success/warn/danger/info/abu); Confirm/Cancel/Close mengikuti `stat_confirm`/`stat_cancel`/`stat_close`.
- Grid: tanpa header halaman, tombol "+ New Record" di toolbar, kolom Aksi: Master = Edit + toggle aktif; non-Master = Buka + Confirm kondisional (tidak ada toggle aktif).
- Data dummy realistis (nama customer, No. dokumen berformat nyata), bukan "Lorem ipsum".
- Teks dan prosa UI bahasa Indonesia; nama field/kolom di DB Inggris snake_case bila ditampilkan.
- Semua komponen input sesuai katalog `<x-form.*>` (date-picker kalender, bukan `<input type=date>` polos).

## Bersih-bersih

File `.html` dan `.jpg` mockup boleh tetap di `docs/mockups/` sebagai acuan (sesuai aturan proyek: jangan hapus dummy/acuan). File sementara render sudah dihapus otomatis oleh skrip.
