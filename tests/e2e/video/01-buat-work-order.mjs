/*
 * REMARK
 * Tujuan       : Video cara pakai: membuat Work Order dari baris Customer Order (Planning > Work Order > Create new).
 * Sumber hulu  : Layar production-wo di mockup.test (ddt*).
 * Input        : node tests/e2e/video/01-buat-work-order.mjs
 * Output       : public/videos/ppic/01-buat-work-order.webm
 * Dipakai oleh : Help F1 > Production Planning.
 * Status       : Aktif; menulis satu WO di database dummy.
 */
import {Rec} from './rec.mjs';
import {openScreen} from './nav.mjs';
import fs from 'node:fs';
const r = new Rec('01-buat-work-order');
await r.start('/v2');
await r.caption('Cara membuat Work Order. Semua dilakukan dari menu sebelah kiri dan satu layar Work Order.', 3500);
await openScreen(r, 'Work Order');
await r.caption('Ini daftar Work Order. Angka di tab menunjukkan jumlah per status: Planning, In Progress, On Hold, Completed.', 4500);
await r.click({text: '^Planning', inFrame: true}, {after: 1500});
await r.caption('Klik "+ Create new Work Order" untuk membuat WO baru.', 3000);
await r.click({text: 'Create new Work Order', inFrame: true}, {after: 2500});
await r.caption('Daftar baris Customer Order yang Product Development-nya sudah lengkap. Master Card sudah terbit di Product Development.', 4500);
await r.caption('Cari baris dengan kotak pencarian: ketik nama produk atau nomor CO. Pilih hanya baris yang Master Card-nya lengkap: part, dieline, artwork.', 3000);
await r.focus({css: 'input[type=search], input[placeholder*="Search"]', inFrame: true});
await r.type('Alsamsi');
await r.pause(1500);
await r.caption('Centang baris yang akan diproses. Satu atau banyak baris sekaligus.', 3000);
await r.click({css: 'tbody input[aria-label="Select row"]'}, {after: 1500});
await r.caption('Klik "Create Work Order". WO dibangun otomatis dari BOP, rute, dan BOM milik Product Development.', 4000);
await r.click({text: 'Create Work Order', inFrame: true}, {after: 500});
await r.caption('Sistem membangun WO dan seluruh BOP serta BOM-nya. Tunggu sebentar.', 1500);
for (let i = 0; i < 40; i++) { const u = await r.fev('location.href'); if (u && !/new=1/.test(u)) break; await r.pause(1000); }
await r.pause(2000);
await r.caption('Form WO terbuka. Bila ada proses yang belum punya mesin atau bahannya menunggu, sistem memberi tahu di sini.', 4500);
await r.click({text: '^Close', inFrame: true}, {after: 800}).catch(() => {});
await r.caption('Master Card di bagian atas: dieline, artwork, ukuran, bahan, warna. Ini acuan officer, sudah terbit di Product Development.', 5000);
await r.click({text: 'Bill of Process', inFrame: true}, {after: 1500});
await r.caption('Bill of Process: urutan proses, mesin, target, dan waste.', 3500);
await r.click({text: 'Bill of Material', inFrame: true}, {after: 1500});
await r.caption('Bill of Material: bahan yang dibutuhkan, yang bisa di-Release dari gudang, dan yang harus dibeli.', 4000);
await r.click({text: '^Simulation', inFrame: true}, {after: 1500});
await r.caption('Simulation: tempat menyusun jadwal, lalu PROCESS dan APPLY TO LIVE SCHEDULE. Lihat video berikutnya.', 4500);
console.log(JSON.stringify(await r.finish()));
