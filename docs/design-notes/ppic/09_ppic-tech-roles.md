# Technical Plan: Roles, Permissions and Audit

Siapa boleh mengubah apa, apa yang boleh dibaca semua orang, dan bagaimana keputusan dicatat.

## Isi catatan

1. Officer penjadwalan (PPIC_SCHEDULING): meninjau rencana Draft dan pengecualian, memberi prioritas (Hot atau naikkan, dengan alasan) dan mengonfirmasi rencana. Mengatur kunci dalam shift dan penimpaan bottleneck.
2. Officer pengendalian inventori (PPIC_INVENTORY): memantau daftar kekurangan dan Waiting dan menindaklanjuti pembelian dan tanggal siap. Booking dikonfirmasi otomatis bila stok mencukupi kebutuhan.
3. Keduanya membaca seluruh layar dan Monitoring. Tidak ada yang mengubah data yang lain.
4. Master (tooling, changeover, toleransi waste, tarif) diubah user yang berhak master. Engine pembelajar boleh menyesuaikan nilai asumsi dalam batasnya; setiap perubahan dicatat dan dapat dikembalikan atau dibekukan.
5. Tier customer hanya diubah di master Customer oleh Sales. Status tooling diubah oleh penjaga tooling yang ditunjuk user.
6. Setiap auto-plan, Confirm, pembukaan kunci, penimpaan prioritas dan perubahan nilai yang diterapkan dicatat di log keputusan dengan siapa (user atau ENGINE), waktu, nilai lama, nilai baru dan alasan.
