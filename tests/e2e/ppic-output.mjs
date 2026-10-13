/*
 * REMARK
 * Tujuan       : Uji browser nyata layar Production Output: tampilan, angka target, daftar job, Master Card, dan (opsional) satu siklus Play / Pause pada job yang ditunjuk.
 * Sumber hulu  : Layar production-operator di mockup.test; sesi dari tests/e2e/make-session.php.
 * Input        : node tests/e2e/ppic-output.mjs <outPrefix> [query] [--act]
 * Output       : Isi tampilan (judul, target, jam, daftar job), tangkapan layar, galat JS.
 * Dipakai oleh : Dijalankan manual oleh agen. --act menulis log nyata di ddt*: bersihkan setelahnya.
 * Status       : Aktif; tanpa --act hanya membaca.
 */
import {spawn} from 'node:child_process';
import fs from 'node:fs';

const [out, opArg, flag] = process.argv.slice(2);
const act = flag === '--act' || opArg === '--act';
const query = opArg && opArg !== '--act' ? opArg : '';  // e.g. "machine=45&process=CETAK&op=1347"
const EDGE = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'].find(p => fs.existsSync(p));
const ck = JSON.parse(fs.readFileSync(new URL('./cookie.json', import.meta.url)));
const port = 12133 + Math.floor(Math.random() * 300);
const proc = spawn(EDGE, [`--remote-debugging-port=${port}`, '--headless=new', '--disable-gpu', `--user-data-dir=${process.env.TEMP}/edge-cdp-${port}`, '--window-size=1100,1000', '--host-resolver-rules=MAP mockup.test 127.0.0.1', 'about:blank'], {stdio: 'ignore'});
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.find(t => t.type === 'page').webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); const errors = [];
ws.onmessage = m => {
  const d = JSON.parse(m.data);
  if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); }
  else if (d.method === 'Runtime.exceptionThrown') errors.push('EXC ' + (d.params.exceptionDetails.exception?.description || d.params.exceptionDetails.text));
};
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
const ev = async expr => (await send('Runtime.evaluate', {expression: expr, returnByValue: true, awaitPromise: true})).result?.result?.value;
const shot = async name => fs.writeFileSync(`${out}_${name}.png`, Buffer.from((await send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));
const read = () => ev(`JSON.stringify({title: document.querySelector('.mp-od__head b')?.textContent, perhour: document.querySelector('[data-od=perhour]')?.textContent, qty: document.querySelector('[data-od=qty]')?.textContent,
  dur: document.querySelector('[data-od=duration]')?.textContent, info: [...document.querySelectorAll('.mp-od__info dt')].map((d, i) => d.textContent + '=' + document.querySelectorAll('.mp-od__info dd')[i].textContent),
  setup: document.querySelector('[data-od-timer=setup] [data-od=clock]')?.textContent + ' | ' + document.querySelector('[data-od-timer=setup] [data-od=times]')?.innerText,
  prod: document.querySelector('[data-od-timer=prod] [data-od=clock]')?.textContent + ' | ' + document.querySelector('[data-od-timer=prod] [data-od=times]')?.innerText.replace(/\s+/g, ' '),
  jobs: [...document.querySelectorAll('.mp-od__row')].map(r => r.innerText.replace(/\s+/g, ' ')), sum: document.querySelector('[data-od=sum]')?.textContent, img: document.querySelector('[data-od-zoom]')?.naturalWidth})`);

await send('Runtime.enable'); await send('Page.enable');
await send('Network.setCookie', {name: ck.name, value: ck.value, url: 'http://mockup.test/'});
await send('Emulation.setDeviceMetricsOverride', {width: 1100, height: 1000, deviceScaleFactor: 1, mobile: false});
await send('Page.navigate', {url: 'http://mockup.test/v2/s/production-operator' + (query ? '?' + query : '')});
await sleep(3500);
console.log('LOAD', await read());
await shot('desk');
if (act) {
  const click = sel => ev(`document.querySelector('${sel}').click()`);
  await click('[data-od-timer=setup] [data-od-act=play]'); await sleep(2500);
  console.log('SETUP PLAY', await read());
  await click('[data-od-timer=prod] [data-od-act=play]'); await sleep(2500);
  console.log('PROD PLAY (setup must pause)', await read());
  await ev(`document.querySelector('[data-od-in=good]').value = 120; document.querySelector('[data-od-in=bad]').value = 7; document.querySelector('[data-od-save]').click()`); await sleep(1500);
  console.log('SAVED', await read());
  await shot('running');
  await click('[data-od-timer=prod] [data-od-act=pause]'); await sleep(1500);
  console.log('PROD PAUSE', await read());
}
console.log('JS errors:', errors.length ? errors.join(', ') : 'none');
ws.close(); proc.kill();
