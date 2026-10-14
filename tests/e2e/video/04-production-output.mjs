/*
 * REMARK
 * Tujuan       : Video cara pakai: layar Production Output (pilih mesin, baca Master Card, timer, isi hasil Good/Bad, simpan).
 * Sumber hulu  : Layar production-operator di mockup.test (ddt*).
 * Input        : node tests/e2e/video/04-production-output.mjs
 * Output       : public/videos/ppic/04-production-output.webm
 * Dipakai oleh : Help F1 > Production Planning.
 * Status       : Aktif; menyimpan satu hasil di database dummy.
 */
import {Rec} from './rec.mjs';
const r = new Rec('04-production-output');
await r.start('/v2');
await r.caption('Mencatat hasil produksi: layar Production Output, dipakai di lini mesin.', 3500);
await r.caption('Buka menu Manufacture.', 1500);
await r.click({text: '^Manufacture'}, {after: 900});
await r.caption('Pilih Production, lalu Production Output.', 2000);
await r.click({text: '^Production'}, {after: 900});
await r.click({text: '^Production Output'}, {after: 3000});
await r.caption('Pilih proses dan mesin tempat Anda bekerja. Anggota crew langsung masuk ke formulir crew-nya.', 4500);
await r.click({text: '^POTONG . CUT-01'}, {after: 3500});
await r.caption('Master Card tampil di atas: gambar artwork dan dieline, ukuran P x L x T, target per jam, target qty, dan target durasi.', 6000);
await r.caption('Setup Time dan Production Output punya timer sendiri. Tombol hijau mulai, kuning jeda, merah berhenti.', 5500);
await r.fev('window.scrollTo(0, 99999)'); await r.pause(1200);
await r.caption('Di bawah: daftar job di mesin ini. Job yang sedang jalan ditandai biru.', 4000);
await r.caption('Isi hasil QC: Good dan Bad. Pakai tombol + dan -, atau ketik angkanya dengan keyboard.', 4500);
for (let i = 0; i < 3; i++) await r.click({text: '^[+]$', inFrame: true}, {after: 500, hold: 200});
await r.caption('Atau ketik langsung: klik kolom Good, lalu ketik jumlahnya dengan keyboard.', 3500);
await r.select({css: 'input[type=number], input[inputmode=numeric], input', inFrame: true});
await r.type('250');
await r.caption('Total dan persentase Good serta Bad dihitung otomatis.', 4000);
console.log(JSON.stringify(await r.finish()));
