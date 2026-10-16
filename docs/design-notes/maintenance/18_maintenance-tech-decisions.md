# Technical Plan: Decisions and Open Items

Keputusan yang diambil untuk pembangunan ulang Maintenance, dan hal yang masih menunggu user atau tim lain.

## Isi catatan

1. Komponen berupa kode, bukan pohon. Daftar 45 mesin tidak butuh tingkat mesin, unit dan komponen.
2. Request dan Maintenance Order tetap dua dokumen. Daily Check dan kerusakan berulang masuk lewat Request supaya penilaian ada di kepala maintenance.
3. Ambang dan tarif adalah pengaturan di Constants sehingga user bisa mengubahnya tanpa kode. Nilai di rencana ini adalah usulan awal.
4. Master data hanya diubah orang. Sistem mengusulkan, orang menyetujui.
5. Stok tidak pernah disalin ke Maintenance. Layar membacanya dari Inventory.
6. Item menu dipindah lewat migration dengan kodenya tidak berubah, sehingga hak akses dan Help tetap menempel.
7. Dua item lama yang masih terbuka tidak menghambat Fase 1: spare part lama di kategori Others, dan periode inspeksi wajib forklift dan kompresor.

## Istilah dan rincian

- **Approved by user**: Menu empat kelompok dan mockup Asset Maintenance Profile.
- **Not approved yet**: Mockup Improvement, Daily Check dan PM Calendar, dan rencana teknis itu sendiri selama masih di-review.
- **Proposed values**: Tiga kali, sembilan puluh hari, review setelah enam puluh hari, dan persetujuan direksi di atas sepuluh juta IDR. User menetapkan nilai akhir.
- **Waiting for others**: Aturan inspeksi wajib forklift dan kompresor perlu dicek ke HR atau K3.
