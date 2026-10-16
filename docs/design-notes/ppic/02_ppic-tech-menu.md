# Technical Plan: PPIC Menu and Access

Letak layar di menu Production dan flag akses yang memisahkan officer penjadwalan dari officer pengendalian inventori.

## Isi catatan

1. Production Scheduling (baru, v2) menggantikan Scheduling Board lama sebagai layar harian. Board v1 tetap bisa dibuka sampai user menghentikannya.
2. Di dalam layar ada empat tab halaman: Schedule, Material Control (tautan ke Material Planning dan Material Booking), Incoming Purchases, Monitoring.
3. Layar master baru: Master Tooling, Tabel Setup dan Changeover, Toleransi Waste (per proses dan mesin), Usulan Pembelajaran. Tier Customer adalah field di master Customer, dirawat Sales.
4. Flag akses, bukan modul baru: PPIC_SCHEDULING mengubah antrean dan kunci; PPIC_INVENTORY mengubah booking, tindak lanjut kekurangan dan tanggal siap. Keduanya membaca semuanya. Satu user boleh memegang keduanya.
5. Baris menu ditambah lewat migration ke sys_app_menu (target v2:production-scheduling dan seterusnya), dijalankan dulu di database menu dummy, lalu di live saat rilis. Layar mulai NOT_BUILT; hanya user yang mengisi LOCKED.
