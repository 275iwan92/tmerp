/*
 * REMARK
 * Tujuan       : Video cara pakai: membuka Form WO, melihat Simulation, PROCESS, lalu APPLY TO LIVE SCHEDULE.
 * Sumber hulu  : Layar production-wo di mockup.test (ddt*); WO DRAFT dipilih lewat argumen (nomor WO).
 * Input        : node tests/e2e/video/02-susun-dan-apply.mjs <WOnomor>
 * Output       : public/videos/ppic/02-susun-dan-apply.webm
 * Dipakai oleh : Help F1 > Production Planning.
 * Status       : Aktif; mengubah WO dummy menjadi LIVE (tidak dapat diulang pada WO yang sama).
 */
import {Rec} from './rec.mjs';
import {openScreen} from './nav.mjs';
const wo = process.argv[2];
const r = new Rec('02-susun-dan-apply');
await r.start('/v2');
await openScreen(r, 'Work Order', 'Buka layar Work Order dari menu Manufacture > Planning.');
await r.caption('Cari Work Order lewat kotak pencarian.', 2500);
await r.focus({css: 'input[type=search], input[placeholder*="Search"]'});
await r.type(wo);
await r.pause(1800);
await r.caption('Klik ikon buka di sebelah kiri baris untuk membuka Form WO.', 3000);
await r.click({css: 'tbody tr a, tbody tr button'}, {after: 3500});
await r.caption('Form WO. Di atas: status, qty, jatuh tempo, perkiraan mulai dan selesai.', 4000);
await r.click({text: '^Simulation', inFrame: true}, {after: 2000});
await r.caption('Simulation: Gantt per mesin. LIVE sudah terkunci, DRAFT bebas diatur.', 4500);
await r.caption('Tombol APPLY mati sampai PROCESS dijalankan.', 3000);
await r.click({text: '^PROCESS', inFrame: true}, {after: 1000});
await r.caption('PROCESS membangun ulang BOP dan BOM, menghitung target, mengecek bahan dan tooling, lalu menyusun jadwal Draft.', 5000);
for (let i = 0; i < 40; i++) { if (!(await r.fev(`document.body?.innerText.includes('Processing')`))) break; await r.pause(1000); }
await r.pause(2500);
import('node:fs').then(()=>{});
await r.fev('window.scrollTo(0,0)'); await r.pause(800);
const fsx = await import('node:fs'); fsx.writeFileSync(process.env.TEMP + '/v2_end.png', Buffer.from((await r.send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));
console.log(await r.fev(`[...document.querySelectorAll('button')].filter(b=>/PROCESS|APPLY/.test(b.textContent)).map(b=>b.textContent.trim().replace(/ +/g,' ')+(b.disabled?'(off)':'(on)')).join(' ¦ ')`));
await r.caption('Hasil PROCESS ada di bagian atas: berapa operasi yang terjadwal, bentrok, dan terlambat.', 4500);
await r.caption('APPLY TO LIVE SCHEDULE menyala hanya bila PROCESS lulus dan bahan siap. Bila bahan kurang, tombol mati dan alasannya tertulis.', 6000);
const on = await r.fev(`[...document.querySelectorAll('button')].some(b=>/^APPLY TO LIVE/.test(b.textContent.trim())&&!b.disabled)`);
if (on) {
  await r.click({text: '^APPLY TO LIVE SCHEDULE', inFrame: true}, {after: 3500});
  await r.caption('APPLY mengunci job ke Live Schedule. Status WO menjadi SCHEDULED.', 4500);
} else {
  await r.caption('Di sini bahan belum siap, jadi APPLY mati. Selesaikan pembelian bahan, lalu PROCESS ulang.', 5000);
}
console.log(JSON.stringify({applied: on, ...(await r.finish())}));
