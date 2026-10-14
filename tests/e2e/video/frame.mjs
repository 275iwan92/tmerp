/* REMARK Tujuan: ambil bingkai dari video hasil rekam untuk diperiksa mata. Input: node frame.mjs <webm> <out> <detik...>. Status: aktif. */
import {spawn} from 'node:child_process';
import http from 'node:http';
import fs from 'node:fs';
const [f, out, ...ts] = process.argv.slice(2);
const srv = http.createServer((q, s) => {
  if (q.url === '/') { s.setHeader('content-type', 'text/html'); return s.end(`<body style="margin:0"><video id=v src="/f.webm" style="width:100vw;height:100vh" muted></video>`); }
  s.setHeader('content-type', 'video/webm'); fs.createReadStream(f).pipe(s);
});
await new Promise(r => srv.listen(0, '127.0.0.1', r));
const port = 14000 + Math.floor(Math.random() * 40);
const p = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [`--remote-debugging-port=${port}`, '--headless=new', `--user-data-dir=${process.env.TEMP}/fr-${port}`, '--window-size=1366,768', `http://127.0.0.1:${srv.address().port}/`], {stdio: 'ignore'});
await new Promise(r => setTimeout(r, 3500));
const tab = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find(t => t.type === 'page');
const ws = new WebSocket(tab.webSocketDebuggerUrl); await new Promise(r => ws.onopen = r);
let id = 0, pend = new Map(); ws.onmessage = m => { const d = JSON.parse(m.data); if (pend.has(d.id)) pend.get(d.id)(d); };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({id: i, method, params})); });
await send('Runtime.evaluate', {expression: 'v.muted=true;v.play()', awaitPromise: false});
const t0 = Date.now();
for (const t of ts) {
  const wait = t * 1000 - (Date.now() - t0); if (wait > 0) await new Promise(r => setTimeout(r, wait));
  fs.writeFileSync(`${out}_${t}.png`, Buffer.from((await send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64'));
}
p.kill(); srv.close(); process.exit(0);
