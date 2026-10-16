# Technical Plan: Why Maintenance Has Its Own Database

Alasan Maintenance berada di tm2mnt dan bukan tm2prd, apa konsekuensinya, dan apa yang tersisa untuk dibersihkan di database live.

## Isi catatan

1. Aturan proyek adalah satu database per modul fungsional. Maintenance kini modul sendiri (MAINT) dengan menu dan grup akses sendiri, seperti HRM, Recruitment dan Payroll.
2. Production menyimpan order perawatan, scheduling dan output. Maintenance punya siklus sendiri berupa request, order perawatan, plan dan improvement, jadi keduanya tidak berbagi tabel.
3. Tidak ada foreign key lintas database, jadi pemisahan tidak mengorbankan integritas. Relasi tetap logis dan dicek di Laravel.
4. Downtime dan status mesin tetap di database Master karena Master Mesin pemiliknya dan scheduling serta OEE membacanya. Maintenance order menulis ke sana lewat satu service.
5. Server terpisah tidak diperlukan. Maintenance memakai server MySQL yang sama dengan satu database tambahan.
6. Saat rilis sebuah migration membuang tabel mnt_ lama yang kosong di tm2prd serta modul MAINT, menu, grup dan pengaturan lama di tm2sys, lalu struktur baru dibuat di tm2mnt.

## Istilah dan rincian

- **Connection**: tm2mnt, diatur oleh DB_MNT. Di situs mockup menunjuk ke ddtmnt.
- **Copy to dummy**: ddt:copy dengan opsi db=mnt menyalin tm2mnt ke ddtmnt, termasuk view.
- **Left in tm2 today**: Sebelas tabel mnt_ kosong di tm2prd (tabel kode berisi data awal), modul MAINT, menunya, dua grup dan satu pengaturan di tm2sys.
- **Removed on 2026-10-08**: Kode, layar, tabel dan menu Maintenance sebelumnya di database mockup dihapus tanpa arsip atas perintah user.
