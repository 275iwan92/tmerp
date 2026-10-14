/*
 * REMARK
 * Tujuan       : Perekam video layar nyata (Chrome terpasang, CDP) dengan kursor mouse, indikator keyboard, dan caption Indonesia.
 * Sumber hulu  : Layar PPIC di mockup.test; sesi dari tests/e2e/make-session.php (cookie.json).
 * Input        : new Rec(nama); aksi: goto, caption, click, type, key, pause; lalu finish() menulis webm.
 * Output       : public/videos/ppic/<nama>.webm (disusun dari bingkai screencast, tanpa ffmpeg).
 * Dipakai oleh : tests/e2e/video/*.mjs; pemutar di Help F1 Production Planning.
 * Status       : Aktif; hanya situs dummy (ddt*).
 */
import {spawn} from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';

const CHROME = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe'].find(p => fs.existsSync(p));
const W = 1366, H = 768;
export const sleep = ms => new Promise(r => setTimeout(r, ms));

/* Lapisan peraga di dalam halaman: kursor, ring klik, lencana keyboard, caption. */
const OVERLAY = `(() => {
  if (window.__rec || window.top !== window) return; window.__rec = true;
  const mk = () => {
    if (document.getElementById('rec-cursor')) return;
    const s = document.createElement('style'); s.textContent = \`
      #rec-cursor{position:fixed;left:0;top:0;z-index:2147483647;pointer-events:none;width:28px;height:28px;transition:none}
      #rec-ring{position:fixed;z-index:2147483646;pointer-events:none;width:34px;height:34px;margin:-17px 0 0 -17px;border:4px solid #e11d48;border-radius:50%;opacity:0}
      #rec-ring.go{animation:recring .45s ease-out}
      @keyframes recring{0%{opacity:1;transform:scale(.4)}100%{opacity:0;transform:scale(1.6)}}
      #rec-key{position:fixed;right:18px;bottom:96px;z-index:2147483647;pointer-events:none;background:#111;color:#fff;font:700 20px/1 Consolas,monospace;padding:10px 14px;border-radius:8px;border:2px solid #fff;display:none;max-width:60vw;white-space:nowrap;overflow:hidden}
      #rec-cap{position:fixed;left:0;right:0;bottom:0;z-index:2147483647;pointer-events:none;background:#0b1220;color:#fff;font:600 24px/1.35 'Segoe UI',Arial,sans-serif;padding:16px 28px;border-top:4px solid #f59e0b;min-height:60px}
      .rec-hl{outline:4px solid #f59e0b !important;outline-offset:2px;border-radius:4px}\`;
    document.head.appendChild(s);
    const c = document.createElement('div'); c.id = 'rec-cursor';
    c.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24"><path d="M3 2l16 9-7 2 4 8-3 1-4-8-6 5z" fill="#fff" stroke="#000" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    c.style.transform = 'translate(' + (window.__rx || 40) + 'px,' + (window.__ry || 40) + 'px)';
    document.body.append(c);
    for (const id of ['rec-ring', 'rec-key', 'rec-cap']) { const d = document.createElement('div'); d.id = id; document.body.append(d); }
    document.getElementById('rec-cap').textContent = window.__cap || '';
  };
  const boot = () => document.body ? mk() : setTimeout(boot, 30); boot();
  window.__move = (x, y, ms) => new Promise(res => {
    const c = document.getElementById('rec-cursor'); const x0 = window.__rx || 40, y0 = window.__ry || 40, t0 = performance.now();
    const f = t => { const k = Math.min(1, (t - t0) / ms), e = k < .5 ? 2*k*k : 1 - Math.pow(-2*k + 2, 2) / 2;
      window.__rx = x0 + (x - x0) * e; window.__ry = y0 + (y - y0) * e; c.style.transform = 'translate(' + window.__rx + 'px,' + window.__ry + 'px)';
      k < 1 ? requestAnimationFrame(f) : res(); }; requestAnimationFrame(f); });
  window.__ring = () => { const r = document.getElementById('rec-ring'); r.style.left = (window.__rx + 4) + 'px'; r.style.top = (window.__ry + 4) + 'px'; r.classList.remove('go'); void r.offsetWidth; r.classList.add('go'); };
  window.__capSet = t => { window.__cap = t; const e = document.getElementById('rec-cap'); if (e) e.textContent = t; };
  window.__keySet = t => { const e = document.getElementById('rec-key'); if (!e) return; e.style.display = t ? 'block' : 'none'; e.textContent = t; };
})();`;

export class Rec {
  constructor(name, {size = [W, H]} = {}) { this.name = name; this.size = size; this.frames = []; this.errors = []; this.pend = new Map(); this.id = 0; }

  async start(url) {
    const ck = JSON.parse(fs.readFileSync(new URL('../cookie.json', import.meta.url)));
    this.port = 13100 + Math.floor(Math.random() * 400);
    this.proc = spawn(CHROME, [`--remote-debugging-port=${this.port}`, '--headless=new', '--disable-gpu', '--hide-scrollbars', `--user-data-dir=${process.env.TEMP}/rec-cdp-${this.port}`, `--window-size=${this.size[0]},${this.size[1]}`, '--host-resolver-rules=MAP mockup.test 127.0.0.1', 'about:blank'], {stdio: 'ignore'});
    let ver; for (let i = 0; i < 60; i++) { try { ver = await (await fetch(`http://127.0.0.1:${this.port}/json`)).json(); break; } catch { await sleep(200); } }
    this.ws = new WebSocket(ver.find(t => t.type === 'page').webSocketDebuggerUrl); await new Promise(r => this.ws.onopen = r);
    this.ws.onmessage = m => {
      const d = JSON.parse(m.data);
      if (d.id && this.pend.has(d.id)) { this.pend.get(d.id)(d); this.pend.delete(d.id); }
      else if (d.method === 'Page.screencastFrame') {
        this.frames.push({t: Date.now(), data: d.params.data});
        this.send('Page.screencastFrameAck', {sessionId: d.params.sessionId});
      } else if (d.method === 'Runtime.exceptionThrown') this.errors.push(d.params.exceptionDetails.exception?.description || d.params.exceptionDetails.text);
    };
    await this.send('Runtime.enable'); await this.send('Page.enable');
    await this.send('Network.setCookie', {name: ck.name, value: ck.value, url: 'http://mockup.test/'});
    await this.send('Emulation.setDeviceMetricsOverride', {width: this.size[0], height: this.size[1], deviceScaleFactor: 1, mobile: false});
    await this.send('Page.addScriptToEvaluateOnNewDocument', {source: OVERLAY});
    if (url) await this.goto(url);
    await this.send('Page.startScreencast', {format: 'jpeg', quality: 70, everyNthFrame: 1});
    this.t0 = Date.now();
  }

  send(method, params = {}) { return new Promise(r => { const i = ++this.id; this.pend.set(i, r); this.ws.send(JSON.stringify({id: i, method, params})); }); }
  async ev(expr) { const r = (await this.send('Runtime.evaluate', {expression: expr, returnByValue: true, awaitPromise: true})).result; if (r?.exceptionDetails) console.log('EV-ERR', r.exceptionDetails.exception?.description?.slice(0,200)); return r?.result?.value; }
  async goto(url, wait = 3500) { await this.send('Page.navigate', {url: 'http://mockup.test' + url}); await sleep(wait); await this.ev(`window.__capSet && window.__capSet(window.__cap||'')`); }
  /* Caption tetap sampai diganti; jeda cukup untuk dibaca (~dua huruf per 55 ms, minimal 2,2 dtk). */
  async caption(text, hold) { await this.ev(`window.__capSet(${JSON.stringify(text)})`); await sleep(hold ?? Math.max(2200, text.length * 55)); }
  async pause(ms) { await sleep(ms); }

  /* Titik tengah elemen (selector CSS, atau {text, tag}); gulir ke tengah layar dahulu. */
  async where(sel) {
    const o = typeof sel === 'string' ? {css: sel} : sel;
    return await this.ev(`((o) => {
      const re = o.text ? new RegExp(o.text) : null;
      const search = (doc, ox, oy, top) => {
        let el = null;
        if (!(o.inFrame && top)) el = o.css ? doc.querySelector(o.css)
          : [...doc.querySelectorAll(o.tag || 'button,a,summary,[role=tab],label,li,span')].filter(e => e.getClientRects().length && re.test((e.textContent || '').trim()) && !e.disabled).sort((a, b) => a.textContent.length - b.textContent.length)[o.nth || 0];
        if (el && el.getClientRects().length) return {el, ox, oy};
        for (const f of doc.querySelectorAll('iframe')) { try { const d = f.contentDocument; if (!d) continue; const b = f.getBoundingClientRect(); const r = search(d, ox + b.left, oy + b.top, false); if (r) return r; } catch {} }
        return null; };
      const h = search(document, 0, 0, true); if (!h) return null;
      h.el.scrollIntoView({block: 'center', inline: 'center', behavior: 'instant'});
      const r = h.el.getBoundingClientRect();
      return {x: Math.round(h.ox + r.left + Math.min(r.width / 2, 60)), y: Math.round(h.oy + r.top + r.height / 2)};
    })(${JSON.stringify(o)})`);
  }
  /* Jalankan ekspresi di dokumen iframe layar (bukan shell). */
  async fev(expr) { return await this.ev(`(() => { const f = [...document.querySelectorAll('iframe')].filter(f => f.getBoundingClientRect().width > 300).pop(); const w = f ? f.contentWindow : window; return w.eval(${JSON.stringify(expr)}); })()`); }
  async moveTo(sel, ms = 900) {
    let p = null; for (let i = 0; i < 16 && !p; i++) { p = await this.where(sel); if (!p) await sleep(500); }
    if (!p) { try { fs.writeFileSync(process.env.TEMP + '/rec-fail.png', Buffer.from((await this.send('Page.captureScreenshot', {format: 'png'})).result.data, 'base64')); } catch {} this.proc.kill(); throw new Error('Tidak ada elemen: ' + JSON.stringify(sel)); }
    await this.ev(`window.__move(${p.x},${p.y},${ms})`);
    await this.send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: p.x, y: p.y});
    return p;
  }
  async click(sel, {hold = 500, after = 1800} = {}) {
    const p = await this.moveTo(sel); await sleep(250);
    await this.ev('window.__ring()');
    await this.send('Input.dispatchMouseEvent', {type: 'mousePressed', x: p.x, y: p.y, button: 'left', buttons: 1, clickCount: 1});
    await sleep(80);
    await this.send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: p.x, y: p.y, button: 'left', buttons: 0, clickCount: 1});
    await sleep(hold + after);
  }
  async drag(sel, dx, dy = 0) {
    const p = await this.moveTo(sel); await sleep(250);
    await this.ev('window.__ring()');
    await this.send('Input.dispatchMouseEvent', {type: 'mousePressed', x: p.x, y: p.y, button: 'left', buttons: 1, clickCount: 1});
    for (let s = 1; s <= 12; s++) { await this.ev(`window.__move(${p.x + dx * s / 12},${p.y + dy * s / 12},60)`); await this.send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: p.x + dx * s / 12, y: p.y + dy * s / 12, button: 'left', buttons: 1}); }
    await this.send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: p.x + dx, y: p.y + dy, button: 'left', buttons: 0, clickCount: 1});
    await sleep(1800);
  }
  /* Ketik di kolom terfokus; lencana keyboard di kanan bawah menampilkan tombol yang ditekan. */
  async type(text, delay = 90) {
    let shown = '';
    for (const ch of text) {
      await this.send('Input.insertText', {text: ch}); shown += ch;
      await this.ev(`window.__keySet(${JSON.stringify('⌨ ' + shown)})`); await sleep(delay);
    }
    await sleep(500); await this.ev(`window.__keySet('')`);
  }
  async key(name, label) {
    const map = {Enter: [13, 'Enter'], Tab: [9, 'Tab'], Escape: [27, 'Escape'], F1: [112, 'F1'], ArrowDown: [40, 'ArrowDown']};
    const [code, key] = map[name];
    await this.ev(`window.__keySet(${JSON.stringify('⌨ ' + (label || name))})`);
    await this.send('Input.dispatchKeyEvent', {type: 'rawKeyDown', windowsVirtualKeyCode: code, key});
    await this.send('Input.dispatchKeyEvent', {type: 'keyUp', windowsVirtualKeyCode: code, key});
    await sleep(900); await this.ev(`window.__keySet('')`);
  }
  /* Klik tiga kali pada kolom: memilih seluruh isinya, supaya ketikan berikutnya menggantikan. */
  async select(sel) {
    const p = await this.moveTo(sel); await sleep(250); await this.ev('window.__ring()');
    for (let c = 1; c <= 3; c++) { await this.send('Input.dispatchMouseEvent', {type: 'mousePressed', x: p.x, y: p.y, button: 'left', buttons: 1, clickCount: c}); await this.send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: p.x, y: p.y, button: 'left', buttons: 0, clickCount: c}); }
    await sleep(300);
  }
  async highlight(sel, on = true) { await this.ev(`(()=>{const e=document.querySelector(${JSON.stringify(sel)}); if(e) e.classList.toggle('rec-hl', ${on});})()`); }
  async focus(sel) { const p = await this.moveTo(sel); await this.send('Input.dispatchMouseEvent', {type: 'mousePressed', x: p.x, y: p.y, button: 'left', buttons: 1, clickCount: 1}); await this.send('Input.dispatchMouseEvent', {type: 'mouseReleased', x: p.x, y: p.y, button: 'left', buttons: 0, clickCount: 1}); await this.ev('window.__ring()'); await sleep(400); }

  /* Hentikan, simpan bingkai ke berkas sementara, lalu susun webm lewat halaman pengode (MediaRecorder). */
  async finish() {
    await this.send('Page.stopScreencast'); const end = Date.now();
    this.ws.close(); this.proc.kill();
    if (process.env.NOENC) return {seconds: Math.round((end - this.t0) / 1000), frames: this.frames.length, jsErrors: this.errors};
    const dir = path.join(process.env.TEMP, 'rec-frames-' + this.name); fs.rmSync(dir, {recursive: true, force: true}); fs.mkdirSync(dir, {recursive: true});
    const meta = this.frames.map((f, i) => { fs.writeFileSync(path.join(dir, i + '.jpg'), Buffer.from(f.data, 'base64')); return {i, ms: this.frames[i + 1] ? this.frames[i + 1].t - f.t : 1500}; });
    const out = new URL('../../../public/videos/ppic/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
    fs.mkdirSync(out, {recursive: true});
    const target = path.join(out, this.name + '.webm');
    await encode(dir, meta, this.size, target);
    return {file: target, seconds: Math.round((end - this.t0) / 1000), frames: meta.length, jsErrors: this.errors};
  }
}

async function encode(dir, meta, [w, h], target) {
  const page = `<!doctype html><canvas id=c width=${w} height=${h}></canvas><script>
    (async()=>{const meta=${JSON.stringify(meta)};const c=document.getElementById('c'),x=c.getContext('2d');
      const rec=new MediaRecorder(c.captureStream(30),{mimeType:'video/webm;codecs=vp8',videoBitsPerSecond:2500000});const parts=[];
      rec.ondataavailable=e=>parts.push(e.data);
      const done=new Promise(r=>rec.onstop=r);
      const draw=async i=>{const im=new Image();im.src='/'+i+'.jpg';await im.decode();x.drawImage(im,0,0,${w},${h});};
      await draw(0);rec.start(500);
      const T0=performance.now();let cum=0;for(const m of meta){await draw(m.i);cum+=m.ms;while(performance.now()-T0<cum){x.drawImage(x.canvas,0,0);await new Promise(r=>setTimeout(r,Math.min(33,Math.max(1,cum-(performance.now()-T0)))));}}
      rec.stop();await done;await fetch('/out',{method:'POST',body:new Blob(parts,{type:'video/webm'})});document.title='done';})();
  <\/script>`;
  let resolve; const fin = new Promise(r => resolve = r);
  const srv = http.createServer((req, res) => {
    if (req.url === '/') { res.setHeader('content-type', 'text/html'); return res.end(page); }
    if (req.url === '/out') { const ch = []; req.on('data', d => ch.push(d)); req.on('end', () => { fs.writeFileSync(target, Buffer.concat(ch)); res.end('ok'); resolve(); }); return; }
    const f = path.join(dir, req.url.slice(1)); if (fs.existsSync(f)) { res.setHeader('content-type', 'image/jpeg'); return res.end(fs.readFileSync(f)); }
    res.statusCode = 404; res.end();
  });
  await new Promise(r => srv.listen(0, '127.0.0.1', r));
  const port = 13600 + Math.floor(Math.random() * 300);
  const proc = spawn(CHROME, [`--remote-debugging-port=${port}`, '--headless=new', '--disable-gpu', `--user-data-dir=${process.env.TEMP}/enc-cdp-${port}`, `http://127.0.0.1:${srv.address().port}/`], {stdio: 'ignore'});
  await fin; proc.kill(); srv.close();
}
