/*
 * REMARK
 * Tujuan       : Tangkapan layar nyata ProCost (ENGINE+ML) di mockup.test: buka layar, simpan gambar sebelum dan sesudah klik Hitung.
 * Sumber hulu  : pages::inquiry.costing; sesi dari tests/e2e/make-session.php (cookie.json, jangan di-commit).
 * Input        : node tests/e2e/procost-advisor-shot.mjs <outPrefix> <inquiryId> [tinggiPx]
 * Output       : <outPrefix>_1_buka.png, _2_hitung.png + teks ringkas (banner, penanda, Laporan Hitung, galat JS).
 * Dipakai oleh : Agen, untuk menilai layar dari tampilan nyata. Hanya membaca, kecuali klik Hitung (tulis ke costing dummy).
 * Status       : Aktif (situs dummy).
 */
import {spawn} from 'node:child_process';
import fs from 'node:fs';

const [out, inquiry, tall = '2600'] = process.argv.slice(2);
const EDGE = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'].find(p => fs.existsSync(p));
const ck = JSON.parse(fs.readFileSync(new URL('./cookie.json', import.meta.url)));
const port = 12433 + Math.floor(Math.random() * 300);
const proc = spawn(EDGE, [`--remote-debugging-port=${port}`, '--headless=new', '--disable-gpu', `--user-data-dir=${process.env.TEMP}/edge-cdp-${port}`, '--window-size=1440,900', '--host-resolver-rules=MAP mockup.test 127.0.0.1', 'about:blank'], {stdio: 'ignore'});
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.find(t => t.type === 'page').webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); const errors = [];
ws.onmessage = m => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } else if (d.method === 'Runtime.exceptionThrown') errors.push('EXC ' + (d.params.exceptionDetails.exception?.description || d.params.exceptionDetails.text)); };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
const ev = async expr => (await send('Runtime.evaluate', {expression: expr, returnByValue: true, awaitPromise: true})).result?.result?.value;
const shot = async name => fs.writeFileSync(`${out}_${name}.png`, Buffer.from((await send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));

await send('Runtime.enable'); await send('Page.enable');
await send('Network.setCookie', {name: ck.name, value: ck.value, url: 'http://mockup.test/'});
await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: +tall, deviceScaleFactor: 1, mobile: false});
await send('Page.navigate', {url: `http://mockup.test/inquiry/${inquiry}/costing`}); await sleep(5000);
console.log('url:', await ev('location.href'), '| title:', await ev('document.title'));
console.log('banner:', await ev(`[...document.querySelectorAll('div')].find(d => d.textContent.trim().startsWith('✦ Engine mengisi'))?.textContent.trim().slice(0, 260) ?? 'TIDAK ADA'`));
console.log('penanda ✦/~/✎:', await ev(`document.querySelectorAll('span[title^="Saran engine"]').length`));
await shot('1_buka');
await ev(`[...document.querySelectorAll('button')].find(b => b.textContent.includes('Hitung'))?.click()`); await sleep(5000);
console.log('laporan:', await ev(`document.querySelector('[wire\\\\:key="laporan-hitung"]')?.innerText.slice(0, 700) ?? 'TIDAK ADA'`));
console.log('galat engine:', await ev(`[...document.querySelectorAll('div')].find(d => d.textContent.trim().startsWith('⚠'))?.textContent.trim().slice(0, 200) ?? 'tidak ada'`));
await shot('2_hitung');
if (errors.length) console.log('JS errors:', errors.slice(0, 5).join('\n'));
ws.close(); proc.kill();
