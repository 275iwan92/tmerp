# Technical Plan: References

Produk dan dokumen yang menjadi dasar rencana ini, dan di mana rencana ini sengaja berbeda dari mereka.

## Isi catatan

1. Model stok dan pergerakan: stock move Odoo, stock ledger dan stock entry ERPNext, tipe pergerakan dan quant SAP, inventory detail NetSuite, inventory transaction Dynamics 365.
2. Alur kerja: receipt satu, dua, tiga langkah Odoo, task dan warehouse order SAP EWM, work dan status lokasi Dynamics 365, putaway rule ERPNext.
3. Aliran data dan valuasi: jurnal perpetual inventory ERPNext, GR/IR SAP, update fisik dan finansial serta weighted average Dynamics 365.
4. Kasus khusus: subkon Odoo dan SAP, special stock SAP untuk konsinyasi, inventory status NetSuite.
5. Halaman resmi diringkas oleh alat baca dan tidak diverifikasi di dalam produknya. Halaman pihak ketiga hanya pendukung.
6. Daftar lengkap dengan tautan dan penanda kualitas ada di dokumen rujukan pada docs proyek.

## Istilah dan rincian

- **Where we differ**: Tanpa warehouse order berlapis; petugas mengonfirmasi usulan. Mutu tidak menahan jalur URGENT.
- **Average style**: Average berjalan per item, bukan average periodik dengan penutupan seperti Dynamics 365.
- **Our own patterns**: Status Conditional, dock to line, dan deteksi kurang kirim adalah adaptasi kita, bukan fitur baku.
- **Document**: docs/md_for_systems/inventorywarehouse_v201_references.md
