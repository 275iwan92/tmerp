# Technical Plan: Roles and User Journey

Siapa melakukan apa di Maintenance: apa yang dibuka, dilihat, diklik dan didapat tiap peran.

## Isi catatan

1. Operator: membuka Daily Check di awal shift, menandai tiap titik, dan melapor masalah sebagai Request. Mendapat nomor request dan, untuk mesin kritis berhenti, order perawatan langsung.
2. Kepala maintenance: membuka daftar Request menurut prioritas dan umur, lalu menekan Accept atau Reject. Mengatur jadwal dan teknisi saat Confirm, dan menekan Close saat pekerjaan Done.
3. Teknisi: membuka My Work, menekan Start, mengisi checklist, part, jam dan kode, lalu Done. Mesin kembali ready dan downtime tertutup.
4. Perencana di produksi: melihat slot terblokir di Scheduling Board dan hanya bisa mengusulkan menggesernya. Kepala maintenance yang mengonfirmasi.
5. Manajemen: membuka laporan, menyaring menurut mesin dan bulan, lalu memutuskan memperbaiki, memperbaiki lebih jauh atau mengganti.
6. Sistem: tiap pagi mengubah plan jatuh tempo menjadi order perawatan Draft dan memeriksa kerusakan berulang. Hanya membuat draft dan saran.

## Istilah dan rincian

- **MAINT_TECH**: Grup teknisi. Bisa melihat master data dan mengerjakan pekerjaan yang ditugaskan. Tidak bisa mengubah master data atau memutuskan.
- **MAINT_HEAD**: Grup kepala maintenance. Bisa mengubah master data dan punya hak approve untuk Accept, Confirm dan Close.
- **Production staff**: Siapa pun yang boleh masuk Production bisa membuat Request dan mengisi Daily Check. Mereka tidak bisa memutuskan.
- **Director**: Juga menyetujui improvement di atas batas biaya yang diatur di Constants.
- **Administrator**: Bisa melakukan semuanya, termasuk mengubah master data.
