# Technical Plan: Work Flow by Type

Alur kerja langkah demi langkah dari tiga jenis maintenance, dengan status dokumen pada tiap langkah.

## Isi catatan

1. Preventive (PMO): Plan, Generate, Confirm, Start, Done, Close. Perintah terjadwal mengubah plan jatuh tempo menjadi order Draft. Kepala mengatur jadwal dan teknisi lalu menekan Confirm.
2. Preventive: teknisi menekan Start, mengisi checklist, memakai part dan mencatat jam, lalu Done. Kepala memeriksa biaya dan temuan lalu menekan Close. Jatuh tempo berikutnya dihitung ulang.
3. Breakdown (BMO): Request, Accept, Start, Done, Close. Siapa pun membuat Request. Kepala menekan Accept sehingga order Confirmed dibuat, atau Reject dengan alasan.
4. Breakdown: prioritas P1 pada mesin berhenti langsung menjadi order perawatan. Teknisi bisa menekan Hold saat part kurang dan Resume saat part tiba.
5. Breakdown: Done menutup downtime dan mengembalikan mesin ke ready. Kepala menekan Close setelah memeriksa biaya serta kode masalah, penyebab dan tindakan.
6. Improvement (IMO): Proposal, Confirm, Implement, Done, Review, Apply. Kepala mengonfirmasi, order jenis IMPROVEMENT dibuat, dan setelah review orang yang menerapkan perubahan.

## Istilah dan rincian

- **Draft**: Dokumen yang sedang disiapkan. Bisa diubah dan dihapus.
- **Confirmed**: Sudah diputuskan dan dijadwalkan. Di dalam Confirmed, status kerja berjalan Scheduled, In Progress, Waiting Part dan Done.
- **Closed**: Sudah diverifikasi kepala. Biaya final dan riwayat disimpan.
- **Cancelled**: Dokumen Confirmed yang dibatalkan, dengan alasan.
- **Request statuses**: Open, Accepted (order perawatan dibuat) dan Rejected (dengan alasan).
- **P1 Emergency**: Mesin kritis berhenti. Langsung menjadi order perawatan.
- **P2 High**: Ditangani dalam 24 jam.
- **P3 Normal**: Ditangani dalam seminggu.
- **P4 Low**: Ditangani bila ada waktu, misalnya improvement.
