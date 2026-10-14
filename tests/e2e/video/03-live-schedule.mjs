/*
 * REMARK
 * Tujuan       : Video cara pakai: memantau Live Schedule (Gantt per mesin dan proses, tabel rincian).
 * Sumber hulu  : Layar production-live di mockup.test (ddt*).
 * Input        : node tests/e2e/video/03-live-schedule.mjs
 * Output       : public/videos/ppic/03-live-schedule.webm
 * Dipakai oleh : Help F1 > Production Planning.
 * Status       : Aktif; hanya membaca.
 */
import {Rec} from './rec.mjs';
import {openScreen} from './nav.mjs';
import fs from 'node:fs';
const r = new Rec('03-live-schedule');
await r.start('/v2');
await r.caption('Memantau jadwal yang sudah berjalan: layar Live Schedule.', 3000);
await openScreen(r, 'Live Schedule');
await r.caption('Lima kotak di atas meringkas: jumlah operasi, sedang jalan, dijeda, terlambat, dan terjadwal.', 5000);
await r.caption('Gantt menampilkan semua job yang sudah di-APPLY. Satu baris satu mesin, waktu mengalir ke kanan.', 5000);
await r.caption('Garis merah tegak adalah waktu sekarang. Job di kirinya sudah lewat atau berjalan.', 4500);
await r.fev('window.scrollTo(0, 400)'); await r.pause(800);
await r.caption('Filter proses dan mesin ada di atas Gantt, tersaring dari data yang sama.', 4500);
await r.fev('window.scrollTo(0, 99999)'); await r.pause(1000);
await r.caption('Di bawahnya tabel rincian: WO, proses, mesin, mulai, selesai, dan status tiap job.', 5000);
await r.fev('window.scrollTo(0, 0)'); await r.pause(1000);
await r.caption('Layar ini hanya untuk memantau. Mengubah susunan dilakukan di Simulation pada Form WO.', 4500);
//, Buffer.from((await r.send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));
console.log(JSON.stringify(await r.finish()));
