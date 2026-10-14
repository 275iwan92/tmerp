/* REMARK Tujuan: tangkapan layar nyata Help (F1) tab guide + pencarian; Output: <out>_guide.png, _search.png; Status: aktif (dummy). */
import {spawn} from 'node:child_process';
import fs from 'node:fs';
const out = process.argv[2];
const EDGE = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'].find(p => fs.existsSync(p));
const ck = JSON.parse(fs.readFileSync(new URL('./cookie.json', import.meta.url)));
const port = 12433 + Math.floor(Math.random() * 300);
const proc = spawn(EDGE, [`--remote-debugging-port=${port}`, '--headless=new', '--disable-gpu', `--user-data-dir=${process.env.TEMP}/edge-cdp-${port}`, '--window-size=1440,1100', '--host-resolver-rules=MAP mockup.test 127.0.0.1', 'about:blank'], {stdio: 'ignore'});
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ver; for (let i = 0; i < 50; i++) { try { ver = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); break; } catch { await sleep(200); } }
const ws = new WebSocket(ver.find(t => t.type === 'page').webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0; const pend = new Map(); const errors = [];
ws.onmessage = m => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } else if (d.method === 'Runtime.exceptionThrown') errors.push('EXC ' + (d.params.exceptionDetails.exception?.description || d.params.exceptionDetails.text)); };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
const ev = async expr => (await send('Runtime.evaluate', {expression: expr, returnByValue: true, awaitPromise: true})).result?.result?.value;
const shot = async n => fs.writeFileSync(`${out}_${n}.png`, Buffer.from((await send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));
await send('Runtime.enable'); await send('Page.enable');
await send('Network.setCookie', {name: ck.name, value: ck.value, url: 'http://mockup.test/'});
await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false});
await send('Page.navigate', {url: 'http://mockup.test/v2/help?tab=guide'}); await sleep(3500);
console.log('url:', await ev('location.href'), '| title:', await ev('document.title'));
console.log('has topic:', await ev(`document.body.innerText.includes('Live and Draft Jobs')`));
// buka panduan
console.log(await ev(`(() => { const e=[...document.querySelectorAll('a,button,summary,li,div')].filter(x=>x.children.length<4 && /Live and Draft Jobs/.test(x.textContent||'')).pop(); if(!e) return 'NOT FOUND'; e.click(); return 'clicked'; })()`));
await ev(`(() => { const e=[...document.querySelectorAll("*")].filter(x=>x.children.length<3 && /LIVE AND DRAFT JOBS/i.test(x.textContent||"")).pop(); if(e) e.scrollIntoView({block:"start"}); window.scrollBy(0,-10); })()`); await sleep(1200); await shot("guide");
console.log('steps visible:', await ev(`(document.body.innerText.match(/LIVE is already|Simulation shows the whole schedule|Planned, not built yet/g)||[]).length`));
console.log('search:', await ev(`(() => { const i=[...document.querySelectorAll('input[type=search],input[type=text],input:not([type])')].filter(x=>x.getClientRects().length)[0]; if(!i) return 'NO INPUT'; i.focus(); i.value='planning produksi'; i.dispatchEvent(new Event('input',{bubbles:true})); i.dispatchEvent(new KeyboardEvent('keyup',{bubbles:true})); return 'typed'; })()`));
await sleep(1500); await shot('search');
console.log('search hit:', await ev(`document.body.innerText.includes('Live and Draft Jobs')`));
if (errors.length) console.log('JS errors:', errors.slice(0, 5).join('\n'));
ws.close(); proc.kill();
