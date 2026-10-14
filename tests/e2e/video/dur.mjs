/* REMARK Tujuan: cek durasi dan ukuran video hasil rekam. Input: node dur.mjs <nama.webm>. Status: aktif. */
import {spawn} from 'node:child_process';
import http from 'node:http';
import fs from 'node:fs';
const f = process.argv[2];
const srv = http.createServer((q, s) => {
  if (q.url === '/') { s.setHeader('content-type', 'text/html'); return s.end(`<video id=v src="/f.webm" preload=auto></video><script>v.onloadeddata=()=>{v.currentTime=1e9};v.onseeked=()=>{document.title='D'+v.duration};v.onerror=()=>document.title='ERR'</script>`); }
  s.setHeader('content-type', 'video/webm'); fs.createReadStream(f).pipe(s);
});
await new Promise(r => srv.listen(0, '127.0.0.1', r));
const port = 13950 + Math.floor(Math.random() * 40);
const p = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [`--remote-debugging-port=${port}`, '--headless=new', `--user-data-dir=${process.env.TEMP}/dur-${port}`, `http://127.0.0.1:${srv.address().port}/`], {stdio: 'ignore'});
await new Promise(r => setTimeout(r, 5000));
const tabs = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
console.log(f, (fs.statSync(f).size / 1e6).toFixed(1) + 'MB', tabs.find(t => t.type === 'page')?.title);
p.kill(); srv.close();
