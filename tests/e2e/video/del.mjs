/*
 * REMARK
 * Tujuan       : Membatalkan WO DRAFT dummy yang Master Card-nya tidak lengkap, lewat tombol Delete + alasan di Form WO (jalur aplikasi, tercatat WO_DELETE).
 * Sumber hulu  : Layar production-wo di mockup.test (ddt*).
 * Input        : node tests/e2e/video/del.mjs <wo_id> [wo_id ...]
 * Output       : WO berstatus CANCELLED; baris pengiriman kembali ke picker.
 * Dipakai oleh : Perapian data dummy skrip video.
 * Status       : Aktif; hanya ddt*.
 */
import {Rec} from './rec.mjs';
const r = new Rec('del');
await r.start('/v2/s/production-wo?id=' + process.argv[2]);
for (const id of process.argv.slice(2)) {
  await r.goto('/v2/s/production-wo?id=' + id, 2500);
  const no = await r.ev(`document.body.innerText.match(/WO[0-9]{7}/)?.[0]`);
  await r.click({text: '^Delete'}, {after: 800});
  const ok = await r.ev(`(()=>{const f=document.querySelector('#wo-delete-form'); if(!f) return 'no-form'; const i=f.querySelector('textarea,input[name=reason]'); i.value='Master Card tidak lengkap (part/dieline/artwork) - dummy skrip video'; i.dispatchEvent(new Event('input',{bubbles:true})); f.requestSubmit(); return 'submitted';})()`);
  await r.pause(2500);
  console.log(id, no, ok, await r.ev('location.href'));
}
await r.finish();
