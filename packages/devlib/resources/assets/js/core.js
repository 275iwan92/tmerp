/* REMARK
 * Tujuan       : Inti DevLib: kelas induk komponen, registry, helper bersama, dan perilaku global (dirty-guard, breadcrumb mode menu, data-dl-open-item).
 * Sumber hulu  : Markup objek x-dl::* (atribut data-dl-*).
 * Input        : Klik, input, pesan postMessage; atribut data-dl-guard, data-dl-crumb-menu, data-dl-crumb-url, data-dl-open-item.
 * Output       : Dialog Save / Don't Save / Cancel, perpindahan menu kiri + halaman kanan, pesan ke Shell.
 * Dipakai oleh : Semua halaman yang memuat devlib.js.
 * Status       : Aktif.
 */
/* DevLib core — kelas induk DlComponent, registry, helper bersama & perilaku global.
 * Komponen = kelas turunan DlComponent (satu file per komponen di js/components). Override:
 *   class KanbanProduksi extends Devlib.classes.Kanban { ... }  lalu  Devlib.define('Kanban', KanbanProduksi)
 * (semua elemen Kanban memakai turunan), atau per elemen: data-dl-js="KanbanProduksi" + Devlib.register(KanbanProduksi). */
window.Devlib = window.Devlib || {};
(function () {
  'use strict';
  const Devlib = window.Devlib;

  class DlComponent {
    static selector = null;
    constructor(el) {
      this.el = el; el.dlComponent = this;
      // Autobind: method boleh dioper sebagai callback. Yang terikat = versi turunan paling akhir (override dihormati).
      const seen = new Set();
      for (let p = Object.getPrototypeOf(this); p && p !== DlComponent.prototype; p = Object.getPrototypeOf(p)) {
        for (const k of Object.getOwnPropertyNames(p)) {
          if (k === 'constructor' || seen.has(k)) continue;
          seen.add(k);
          const d = Object.getOwnPropertyDescriptor(p, k);
          if (typeof d.value === 'function') this[k] = d.value.bind(this);
        }
      }
    }
    $(sel) { return this.el.querySelector(sel); }
    $$(sel) { return Array.from(this.el.querySelectorAll(sel)); }
    // Event keluar komponen. Kembali false bila listener memanggil preventDefault (cancelable).
    emit(name, detail = {}, cancelable = false) { return this.el.dispatchEvent(new CustomEvent(name, {bubbles: true, cancelable, detail})); }
    // Delegasi event: on('click', '[data-x]', (e, target) => ...)
    on(type, sel, fn, opts) { this.el.addEventListener(type, e => { const t = sel ? e.target.closest && e.target.closest(sel) : this.el; if (t && this.el.contains(t)) fn.call(this, e, t); }, opts); return this; }
    init() {}
  }

  Devlib.Component = DlComponent;
  Devlib.classes = {};     // nama kelas -> kelas (untuk data-dl-js)
  Devlib.registry = [];    // [{name, selector}] berurutan = urutan mount
  // Daftarkan / timpa kelas komponen. Nama sama = ganti kelas untuk semua elemennya.
  Devlib.define = (name, cls, selector = cls.selector) => {
    Devlib.classes[name] = cls;
    const r = Devlib.registry.find(x => x.name === name);
    if (r) { r.cls = cls; r.selector = selector || r.selector; } else Devlib.registry.push({name, cls, selector});
    return cls;
  };
  // Daftarkan turunan tanpa mengganti induk (dipilih per elemen lewat data-dl-js="Nama").
  Devlib.register = cls => { Devlib.classes[cls.name] = cls; return cls; };
  Devlib.mountOne = (name, root = document) => {
    const r = Devlib.registry.find(x => x.name === name); if (!r || !r.selector) return;
    const els = [...(root.matches && root.matches(r.selector) ? [root] : []), ...root.querySelectorAll(r.selector)];
    els.forEach(el => {
      if (el.dlMounted && el.dlMounted[name]) return;
      (el.dlMounted ||= {})[name] = true;
      const C = (el.dataset.dlJs && Devlib.classes[el.dataset.dlJs]) || r.cls;
      new C(el).init();
    });
  };
  // Pasang semua komponen di dalam root (dipanggil saat halaman siap; panggil lagi untuk isi dinamis/Livewire).
  Devlib.mount = (root = document, order = null) => (order || Devlib.registry.map(r => r.name)).forEach(n => Devlib.mountOne(n, root));

  let currentLang = new URLSearchParams(location.search).get('lang') || window.DL_LANG_ACTIVE || 'id';
  Object.defineProperty(Devlib, 'lang', {get: () => currentLang, set: v => { currentLang = v; }, configurable: true});

  const $ = (root, sel) => root.querySelector(sel);
  const $$ = (root, sel) => Array.from(root.querySelectorAll(sel));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  /* ========================= PRIMITIVE ========================= */





  function showQr(text) {
    let ov = document.querySelector('.dl-qr');
    if (!ov) {
      ov = document.createElement('div'); ov.className = 'dl-mov dl-qr';
      ov.innerHTML = '<div class="dl-modal" style="max-width:300px"><div class="dl-modal__head"><h3>' + esc(T('qr_title')) + '</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div><div class="dl-modal__body" style="display:flex;justify-content:center"><div data-qr></div></div><div class="dl-modal__foot"><button type="button" class="dl-mbtn dl-mbtn--c" data-x>' + esc(T('close')) + '</button><button type="button" class="dl-mbtn dl-mbtn--p" data-dl-qr-dl>Download PNG</button></div></div>';
      document.body.appendChild(ov);
      ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('[data-x]')) ov.classList.remove('is-on'); });
      ov.querySelector('[data-dl-qr-dl]').addEventListener('click', () => { const img = ov.querySelector('[data-qr] img, [data-qr] canvas'); if (!img) return; const a = document.createElement('a'); a.href = img.src || img.toDataURL(); a.download = 'form-qrcode.png'; a.click(); });
    }
    const draw = () => { const box = ov.querySelector('[data-qr]'); box.innerHTML = ''; new window.QRCode(box, {text, width: 180, height: 180, colorDark: '#111827', colorLight: '#ffffff'}); ov.classList.add('is-on'); };
    if (window.QRCode) return draw();
    const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'; s.onload = draw; document.head.appendChild(s);
  }

  /* ============================ FIELD ============================ */
  const CASES = ['none', 'capitalize', 'uppercase'], CASE_LABEL = {none: 'aa', capitalize: 'Aa', uppercase: 'AA'};
  const applyCase = (t, m) => m === 'uppercase' ? t.toUpperCase() : m === 'capitalize' ? t.replace(/\b\w/g, c => c.toUpperCase()) : t;
  // Angka ikut bahasa tampilan (LOCKED user 2026-10-01): id 1.234,56 · en 1,234.56; aturannya dari server (window.DL_NUM =
  // Devlib\Locale::jsSpec(), sumber sys_languages + kamus). Dibekukan saat halaman dimuat: ganti bendera bahasa tanpa muat ulang
  // TIDAK mengubah cara membaca isian yang sudah tampil. Server selalu menerima angka kanonik 1234.5 (lihat 'formdata' di bawah).
  const NUM = Object.assign({lang: 'id', intl: 'id-ID', dec: ',', grp: '.', months: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']}, window.DL_NUM || {});
  const MON_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  // Nama hari 2 huruf untuk kepala kalender (Minggu dulu). Intl memberi "Se Se" untuk Senin/Selasa di Indonesia, jadi daftar sendiri.
  const dow2 = () => String(NUM.intl).startsWith('id') ? ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'] : Array.from({length: 7}, (_, i) => new Date(2000, 0, 2 + i).toLocaleString(NUM.intl, {weekday: 'short'}).replace('.', '').slice(0, 2));
  // min..max digit desimal; pemisah dari NUM (bukan Intl) supaya sama persis dengan Locale::number di PHP.
  const numFmt = (n, min = 0, max = min) => { n = +n; if (!isFinite(n)) return ''; return n.toLocaleString('en-US', {minimumFractionDigits: min, maximumFractionDigits: Math.max(min, max)}).replace(/[,.]/g, c => c === ',' ? NUM.grp : NUM.dec); };
  // Ketikan/tampilan -> string kanonik "1234.5" ('' = kosong/bukan angka). SAMA PERSIS dengan Devlib\Locale::parse (PHP).
  // Titik DAN koma -> yang terakhir desimal (bebas bahasa, mis. tempelan Excel). Satu jenis -> aturan bahasa: pemisah desimal
  // bahasa = desimal; pemisah ribuan berpola 1.234.567 = ribuan; satu pemisah lain = desimal. "12," (sedang diketik) -> "12".
  const numStr = v => {
    if (typeof v === 'number') return isFinite(v) ? String(v) : '';
    let s = String(v ?? '').replace(/\s|Rp|IDR|USD|EUR|[$€¥%]/gi, '');
    const dots = (s.match(/\./g) || []).length, commas = (s.match(/,/g) || []).length;
    if (dots && commas) { const dec = s.lastIndexOf('.') > s.lastIndexOf(',') ? '.' : ','; s = s.split(dec === '.' ? ',' : '.').join('').replace(dec, '.'); }
    else if (dots || commas) {
      const ch = dots ? '.' : ',', n = dots || commas;
      if (ch === NUM.dec) { if (n > 1) return ''; s = s.replace(ch, '.'); }
      else if (new RegExp('^-?\\d{1,3}(\\' + ch + '\\d{3})+$').test(s)) s = s.split(ch).join('');
      else if (n === 1) s = s.replace(ch, '.');
      else return '';
    }
    if (!/^-?\d*\.?\d*$/.test(s) || !/\d/.test(s)) return '';
    const neg = s.startsWith('-'); let [i, f = ''] = s.replace('-', '').split('.'); i = i.replace(/^0+(?=\d)/, '') || '0';
    const out = i + (f ? '.' + f : ''); return (neg && +out ? '-' : '') + out;
  };
  // Nilai dari DB/server (kanonik "1234.5") dipakai apa adanya; selain itu dibaca sebagai ketikan (numStr). Sama dgn Locale::input.
  const numIn = v => typeof v === 'number' ? numStr(v) : /^-?\d+(\.\d+)?$/.test(String(v ?? '').trim()) ? numStr(String(v).trim().replace('.', NUM.dec)) : numStr(v);
  const idNum = n => numFmt(n, 0, 3);  // nama lama; format ikut bahasa tampilan

  // TANGGAL = satu objek untuk input & tampilan (user 2026-10-01: "Object oriented adalah KEY"). Sama dengan Devlib\DateValue (PHP).
  // Simpan/kirim: ISO yyyy-mm-dd. Tampil: SATU format dd-Mmm-yyyy, bulan 3 huruf ikut bahasa tampilan (10-Okt-2026 / 10-Oct-2026).
  // Ketik (ala sel Excel): 10-10-2026, 10/10/26, 10.10, 10-Oct-2026, 10 okt 2026, 2026-10-10; tanpa tahun = tahun ini.
  // Tanggal mustahil (31-02) ditolak. 'yyyy-mm-dd hh:mm' dibaca bagian tanggalnya apa adanya (tanpa geser zona waktu).
  class DlDate {
    constructor(y, m, d) { this.y = y; this.m = m; this.d = d; Object.freeze(this); }
    static make(y, m, d) { const t = new Date(y, m - 1, d); return m && t.getFullYear() === y && t.getMonth() === m - 1 && t.getDate() === d ? new DlDate(y, m, d) : null; }
    static today() { return DlDate.of(new Date()); }
    // Nama/singkatan bulan dalam bahasa tampilan, English, atau ejaan umum Indonesia -> 1..12 (0 = tidak dikenal).
    static month(t) {
      const k = String(t).toLowerCase().slice(0, 3), alias = {mei: 5, agt: 8, agu: 8, okt: 10, des: 12, peb: 2};
      const i = NUM.months.findIndex(x => x.toLowerCase().slice(0, 3) === k), j = MON_EN.findIndex(x => x.toLowerCase() === k);
      return i >= 0 ? i + 1 : j >= 0 ? j + 1 : alias[k] || 0;
    }
    static of(v) {
      if (v instanceof DlDate) return v;
      if (v == null || v === '') return null;
      if (v instanceof Date) { if (isNaN(v)) return null; const [y, m, d] = v.toLocaleDateString('en-CA', {timeZone: TZ()}).split('-').map(Number); return new DlDate(y, m, d); }
      const s = String(v).trim(); let x;
      if ((x = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ].*)?$/))) return DlDate.make(+x[1], +x[2], +x[3]);
      if ((x = s.match(/^(\d{1,2})[\s/.-]+(\d{1,2}|[^\s\d/.,-]{3,})\.?(?:[\s/.,-]+(\d{2}|\d{4}))?$/u))) {
        const m = /^\d+$/.test(x[2]) ? +x[2] : DlDate.month(x[2]), y = x[3] ? (x[3].length === 2 ? 2000 + +x[3] : +x[3]) : new Date().getFullYear();
        return DlDate.make(y, m, +x[1]);
      }
      return null;
    }
    iso() { return `${this.y}-${String(this.m).padStart(2, '0')}-${String(this.d).padStart(2, '0')}`; }
    display() { return `${String(this.d).padStart(2, '0')}-${NUM.months[this.m - 1]}-${this.y}`; }
    toDate() { return new Date(this.y, this.m - 1, this.d); }
    toString() { return this.display(); }
  }

  // ANGKA (desimal & integer) = satu objek untuk input & tampilan. Kembaran PHP: Devlib\NumberValue. Kanonik "1234.5" disimpan/dikirim.
  class DlNumber {
    constructor(c) { this.c = c; Object.freeze(this); }
    static of(v) { if (v instanceof DlNumber) return v; const c = numIn(v); return c === '' ? null : new DlNumber(c); }     // nilai DB/server
    static parse(v) { if (v instanceof DlNumber) return v; const c = numStr(v); return c === '' ? null : new DlNumber(c); } // ketikan manusia
    get canonical() { return this.c; }
    value() { return +this.c; }
    display(d = 0) { return numFmt(+this.c, d); }                   // tepat d digit: 1.234,50
    trimmed(max = 2) { return numFmt(+this.c, 0, max); }            // secukupnya: 12,5
    raw() { return (this.c.includes('.') ? this.c.replace(/\.?0+$/, '') : this.c).replace('.', NUM.dec); }  // diedit: 1234,5
    toString() { return this.c; }
  }

  // JAM = satu objek. Kembaran PHP: Devlib\TimeValue. Ketik ala Excel: 8 / 830 / 0830 / 8:30 / 08.30 -> 08:30 (24 jam).
  class DlTime {
    constructor(h, m, s = 0) { this.h = h; this.m = m; this.s = s; Object.freeze(this); }
    static make(h, m, s = 0) { return h >= 0 && h <= 23 && m >= 0 && m <= 59 && s >= 0 && s <= 59 ? new DlTime(h, m, s) : null; }
    static of(v) {
      if (v instanceof DlTime) return v;
      if (v == null || v === '') return null;
      if (v instanceof Date) { if (isNaN(v)) return null; const [h, m, s] = v.toLocaleTimeString('en-GB', {timeZone: TZ(), hour12: false}).split(':').map(Number); return new DlTime(h % 24, m, s); }
      const t = String(v).trim(); let x;
      if ((x = t.match(/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/))) return DlTime.make(+x[1], +x[2], +(x[3] || 0));
      if (/^\d{1,4}$/.test(t)) return t.length <= 2 ? DlTime.make(+t, 0) : DlTime.make(+t.slice(0, -2), +t.slice(-2));
      return null;
    }
    iso() { const p = n => String(n).padStart(2, '0'); return `${p(this.h)}:${p(this.m)}:${p(this.s)}`; }
    display() { return this.iso().slice(0, 5); }
    toString() { return this.display(); }
  }

  // TANGGAL + JAM = DlDate + DlTime. Kembaran PHP: Devlib\DateTimeValue. Simpan 'yyyy-mm-dd HH:mm:ss', tampil 'dd-Mmm-yyyy HH:mm'.
  class DlDateTime {
    constructor(date, time) { this.date = date; this.time = time; Object.freeze(this); }
    static now() { return DlDateTime.of(new Date()); }
    static of(v) {
      if (v instanceof DlDateTime) return v;
      if (v == null || v === '') return null;
      const s = v instanceof Date ? null : String(v).trim(); let x;
      if (v instanceof Date || /\d{2}:\d{2}(:\d{2})?(\.\d+)?([+-]\d{2}:?\d{2}|Z)$/.test(s)) { const d = v instanceof Date ? v : new Date(s); return isNaN(d) ? null : new DlDateTime(DlDate.of(d), DlTime.of(d)); }
      if ((x = s.match(/^(\d{4}-\d{1,2}-\d{1,2})[T ](\d{1,2}:\d{2}(?::\d{2})?)/))) { const d = DlDate.of(x[1]), t = DlTime.of(x[2]); return d && t ? new DlDateTime(d, t) : null; }
      const d0 = DlDate.of(s); if (d0) return new DlDateTime(d0, DlTime.make(0, 0));  // tanggal saja dulu: "10 okt 2026" bukan jam 20:26
      if ((x = s.match(/^(.*?\S)[\sT]+(\d{1,2}(?:[:.]\d{2}){0,2}|\d{3,4})$/))) { const d = DlDate.of(x[1]), t = DlTime.of(x[2]); if (d && t) return new DlDateTime(d, t); }
      return null;
    }
    iso() { return this.date.iso() + ' ' + this.time.iso(); }
    display() { return this.date.display() + ' ' + this.time.display(); }
    toString() { return this.display(); }
  }

  // TEKS = satu objek. Kembaran PHP: Devlib\TextValue. Mode huruf aa / Aa / AA (badge di isian teks); input dirapikan.
  class DlText {
    constructor(v) { this.value = v; Object.freeze(this); }
    static of(v, mode = 'none') { return new DlText(applyCase(String(v ?? '').trim().replace(/\s+/g, ' '), mode)); }
    display(empty = '—') { return this.value === '' ? empty : this.value; }
    toString() { return this.value; }
  }

  // Panel dropdown/popover milik field: buka/tutup; klik di luar menutup semua.
  function bindPanel(root, onOpen) {
    const toggle = $(root, '[data-dl-dd-toggle]'), panel = $(root, '[data-dl-dd-panel]');
    if (!toggle || !panel) return {};
    // Panel terbuka = position:fixed di atas kotak field, supaya tidak terpotong card/section ber-overflow
    // (user 2026-09-26: daftar hanya tampak 2 item). Lebar & posisi awal diukur dari tata letak normalnya;
    // bila ruang di bawah kurang dan di atas lebih lega, panel dibuka ke atas.
    const place = () => {
      Object.assign(panel.style, {position: '', left: '', top: '', bottom: '', width: ''});
      const r = panel.getBoundingClientRect(), t = toggle.getBoundingClientRect();
      Object.assign(panel.style, {position: 'fixed', width: r.width + 'px', left: Math.max(8, Math.min(r.left, innerWidth - r.width - 8)) + 'px'});
      const below = innerHeight - t.bottom - 8, above = t.top - 8;
      if (panel.offsetHeight > below && above > below) Object.assign(panel.style, {top: 'auto', bottom: (innerHeight - t.top + 4) + 'px'});
      else Object.assign(panel.style, {top: (t.bottom + 4) + 'px', bottom: 'auto'});
    };
    const follow = () => { if (panel.classList.contains('is-open')) place(); };
    addEventListener('scroll', follow, true);
    addEventListener('resize', follow);
    toggle.addEventListener('click', e => {
      e.stopPropagation();
      const open = !panel.classList.contains('is-open');
      document.querySelectorAll('[data-dl-dd-panel].is-open').forEach(p => p.classList.remove('is-open'));
      panel.classList.toggle('is-open', open);
      if (open) place();
      if (open && onOpen) onOpen();
    });
    panel.addEventListener('click', e => e.stopPropagation());
    const search = $(panel, '[data-dl-dd-search]');
    if (search) search.addEventListener('input', () => $$(panel, '.dl-dd__item').forEach(it => { it.style.display = it.textContent.toLowerCase().includes(search.value.toLowerCase()) ? '' : 'none'; }));
    return {panel, search, close: () => panel.classList.remove('is-open')};
  }

  // Kalkulator mini (satu untuk Sheet dan toggle "00" isian angka): + - x / dan %, kiri-ke-kanan dgn prioritas x / ;
  // '=' / Enter = onResult(nilai). % ala kalkulator: 200 + 10% = 220, 200 x 10% = 20, 50% = 0,5.
  function calcPanel(p, {start = '', decimals = null, onResult}) {
    let ex = start;  // desimal ikut bahasa (NUM.dec)
    const ev = (nums, ops) => {
      const a = [nums[0]], o = [];
      ops.forEach((op, i) => { const n = nums[i + 1]; if (n == null) return; if (op === '*') a.push(a.pop() * n); else if (op === '/') a.push(a.pop() / n); else { o.push(op); a.push(n); } });
      return o.reduce((s, op, i) => op === '+' ? s + a[i + 1] : s - a[i + 1], a[0]);
    };
    const calc = () => {
      const t = ex.split(NUM.dec).join('.').match(/(\d+\.?\d*|\.\d+|[-+*/%])/g) || []; if (!t.length) return null;
      const nums = [], ops = []; let neg = false;
      for (const x of t) {
        if (x === '%') { if (nums.length !== ops.length + 1) return null; const n = nums.pop(), op = ops[ops.length - 1]; nums.push(op === '+' || op === '-' ? ev(nums, ops.slice(0, -1)) * n / 100 : n / 100); continue; }
        if ('+-*/'.includes(x) && nums.length === ops.length) { if (x === '-') { neg = !neg; continue; } return null; }
        if ('+-*/'.includes(x)) ops.push(x); else { nums.push(neg ? -x : +x); neg = false; }
      }
      if (nums.length === ops.length) ops.pop();  // operator menggantung di akhir diabaikan
      const v = ev(nums, ops);
      return isFinite(v) ? Math.round(v * 1e10) / 1e10 : null;
    };
    const show = () => { $(p, '[data-e]').textContent = ex.replace(/\*/g, '×').replace(/\//g, '÷').replace(/-/g, '−') || '0'; const v = calc(); $(p, '[data-r]').textContent = v == null ? (ex ? '–' : '') : '= ' + fmt.num(v, decimals ?? 10); };
    const key = k => {
      if (k === 'C') ex = ''; else if (k === '⌫') ex = ex.slice(0, -1);
      else if (k === '=') { let v = calc(); if (v == null) return toast(T('s_calc_bad'), 'danger'); if (decimals != null) v = Math.round(v * 10 ** decimals) / 10 ** decimals; ex = String(v).replace('.', NUM.dec); onResult(v); }
      else if (k === '%') { if (/\d$/.test(ex)) ex += k; }
      else if ('+-*/'.includes(k)) ex = /[-+*/]$/.test(ex) && k !== '-' ? ex.slice(0, -1) + k : ex + k;
      else if (k === NUM.dec) { const seg = ex.split(/[-+*/]/).pop(); if (!seg.includes(NUM.dec) && !seg.includes('%')) ex += (seg ? '' : '0') + NUM.dec; }
      else if (!ex.endsWith('%')) ex += k;
      show();
    };
    p.innerHTML = '<div class="dl-calc__screen"><div class="dl-calc__expr" data-e></div><div class="dl-calc__res" data-r></div></div><div class="dl-calc__keys">'
      + ['C', '⌫', '/', '*', '7', '8', '9', '-', '4', '5', '6', '+', '1', '2', '3', '=', '0', NUM.dec, '%'].map(k => `<button type="button" data-k="${k}" class="${'+-*/%'.includes(k) ? 'is-op' : k === '=' ? 'is-eq' : ''}" ${k === '=' ? 'title="' + esc(T('s_calc_enter')) + '"' : ''}>${{'/': '÷', '*': '×', '-': '−'}[k] ?? k}</button>`).join('') + '</div>';
    p.addEventListener('click', e => { const b = e.target.closest('[data-k]'); if (b) key(b.dataset.k); });
    p.addEventListener('keydown', e => {
      const k = e.key === 'Enter' ? '=' : e.key === 'Backspace' ? '⌫' : e.key === 'Delete' || e.key === 'c' ? 'C' : e.key === '.' || e.key === ',' ? NUM.dec : e.key === 'x' ? '*' : e.key;
      if (/^[0-9,.+\-*/%=C⌫]$/.test(k)) { e.preventDefault(); e.stopPropagation(); key(k); }
    });
    show();
  }


  // Urutan pilihan dari daftar: random, atau sequence (lanjut tiap halaman dibuka, diingat per kunci).
  // order 'day': tanggal hari ini -> baris tetap (daftar 366 baris, baris ke-60 = 29 Feb).
  function pickIndex(len, order, key) {
    if (!len) return -1;
    if (order === 'day') return dayIndex() % len;
    if (order !== 'sequence') return Math.floor(Math.random() * len);
    let i = -1; try { i = parseInt(localStorage.getItem('dl-seq-' + key) ?? '-1', 10); } catch (e) {}
    i = (i + 1) % len;
    try { localStorage.setItem('dl-seq-' + key, i); } catch (e) {}
    return i;
  }





  // Kode cuaca WMO (Open-Meteo) -> ikon + keterangan.
  const WMO = [[0, '☀️', 'Cerah', 'Clear'], [2, '⛅', 'Berawan sebagian', 'Partly cloudy'], [3, '☁️', 'Berawan', 'Cloudy'], [48, '🌫️', 'Berkabut', 'Fog'],
    [57, '🌦️', 'Gerimis', 'Drizzle'], [67, '🌧️', 'Hujan', 'Rain'], [77, '🌨️', 'Salju', 'Snow'], [82, '🌧️', 'Hujan deras', 'Showers'], [99, '⛈️', 'Badai petir', 'Thunderstorm']];



  /* ============================ LOGIN BOX ============================ */
  // Isinya komponen; di sini tinggal teks 2 bahasa dan validasi kosong.
  const LOGIN_T = {
    id: {heading:'Masuk',sub:'Gunakan akun terdaftar untuk mengakses aplikasi.',l_login:'Username atau email',l_email:'Email',l_pass:'Password',forgot:'Lupa password?',btn:'Masuk',or:'atau',no_acc:'Belum punya akun?',reg:'Daftar',err_empty:'Isi username/email dan password.',err_wrong:'Username atau password salah.'},
    en: {heading:'Sign in',sub:'Use your registered account to access the app.',l_login:'Username or email',l_email:'Email',l_pass:'Password',forgot:'Forgot password?',btn:'Sign in',or:'or',no_acc:"Don't have an account?",reg:'Register',err_empty:'Enter your username/email and password.',err_wrong:'Incorrect username or password.'}
  };


  /* ============================ SHELL ============================ */
  const typeIcon = t => t === 'grid' ? 'fa-solid fa-table-list' : t === 'report' ? 'fa-solid fa-chart-column' : 'fa-solid fa-pen-to-square';
  const HOME = {code: 'HOME', label: 'Home', icon: 'fa-solid fa-house'};


  /* ======================= MODE DEVELOPER ======================= */
  // Asal-usul tiap objek library (data-dl*). Nyala/mati: F10, HANYA pengguna ber-security Developer/Administrator
  // (halaman menandainya dengan data-dl-developer="1"; keputusan user 2026-09-25). Tab (iframe) tidak pernah
  // menyalakan dirinya sendiri: statusnya selalu dari jendela utama, jadi pengguna lain tidak bisa memunculkannya.
  // Keterangan objek yang disorot tampil di SATU kotak tetap (kanan bawah, tidak menangkap klik) -- bukan tooltip
  // yang ikut mouse, supaya tidak menutupi menu (keputusan user 2026-09-25). Objeknya diberi garis biru.
  // Elemen interaktif yang TIDAK berada di dalam komponen library diberi garis merah (.dl-nolib).
  let box, hit;
  const devOn = () => document.body.classList.contains('dl-dev');
  const devAllowed = () => !!document.querySelector('[data-dl-developer="1"],[data-dl-devtoggle]') || document.body.dataset.dlDev === '1';
  function setDevMode(on) {
    try { localStorage.setItem('dl-dev', on ? '1' : '0'); } catch (e) {}
    document.body.classList.toggle('dl-dev', on);
    document.querySelectorAll('[data-dl-devswitch]').forEach(s => s.classList.toggle('is-on', on));
    $$(document, 'button,a,input,select,textarea,table').forEach(el => el.classList.toggle('dl-nolib', on && !el.closest('[data-dl]')));
    if (on) {
      const all = $$(document, 'button,a,input,select,textarea,table');
      const lib = all.filter(el => el.closest('[data-dl]')).length;
      box.querySelector('[data-dev-pct]').textContent = Tf('dev_pct', {n: all.length ? Math.round(lib * 100 / all.length) : 100});
      box.querySelector('[data-dev-body]').innerHTML = '<span class="k">' + esc(T('dev_hover')) + '</span>';
    } else if (hit) { hit.classList.remove('dl-dev-hit'); hit = null; }
    // Frame tab ikut diberi tahu supaya isinya juga menampilkan inspektur.
    document.querySelectorAll('iframe').forEach(f => { try { f.contentWindow.postMessage({dlDev: on}, '*'); } catch (e) {} });
  }
  // F10: di jendela utama langsung; di dalam tab (iframe) minta jendela utama yang memutuskan & menyebarkan.
  function toggleDev() {
    if (window.self !== window.top) { try { window.top.postMessage({dlDevToggle: true}, '*'); } catch (e) {} return; }
    if (devAllowed()) setDevMode(!devOn());
  }
  function initDevMode() {
    box = document.createElement('div'); box.className = 'dl-dev-box'; box.setAttribute('aria-live', 'polite');
    box.innerHTML = '<div class="dl-dev-box__head"><b>' + esc(T('dev_mode')) + '</b><span>' + esc(T('dev_f10_off')) + '</span></div><div class="dl-dev-box__body" data-dev-body></div><div class="dl-dev-box__foot" data-dev-pct></div>';
    document.body.appendChild(box);
    document.addEventListener('mousemove', e => {
      if (!devOn()) return;
      const el = e.target.closest && e.target.closest('[data-dl]');
      const body = box.querySelector('[data-dev-body]');
      if (el) {
        body.innerHTML = `<b>${esc(el.dataset.dl)}</b><br><span class="k">${esc(T('dev_class'))}:</span> ${esc(el.dataset.dlClass || '—')}<br><span class="k">${esc(T('dev_source'))}:</span> ${esc(el.dataset.dlSrc || '—')}`;
      } else if (e.target.classList && e.target.classList.contains('dl-nolib')) {
        body.innerHTML = '<b>' + esc(T('dev_not_lib')) + '</b><br><span class="k">' + esc(T('dev_element')) + ':</span> &lt;' + esc(e.target.tagName.toLowerCase()) + '&gt;';
      }
      if (el !== hit) { hit?.classList.remove('dl-dev-hit'); hit = el; hit?.classList.add('dl-dev-hit'); }
    });
    const top = window.self === window.top;
    window.addEventListener('message', e => {
      if (!e.data) return;
      if (typeof e.data.dlDev === 'boolean' && !top && e.source === window.parent) setDevMode(e.data.dlDev);
      if (!top || !devAllowed()) return;
      if (e.data.dlDevToggle) setDevMode(!devOn());
      if (e.data.dlDevAsk && e.source) e.source.postMessage({dlDev: devOn()}, '*');
    });
    if (top) {
      let saved = false; try { saved = localStorage.getItem('dl-dev') === '1'; } catch (e) {}
      setDevMode(document.body.dataset.dlDev === '1' || (saved && devAllowed()));
    } else {
      setDevMode(false);
      try { window.parent.postMessage({dlDevAsk: true}, '*'); } catch (e) {}
    }
  }





  /* ================= SMALL COMPONENTS (lanjutan) ================= */
  const PRESET_T = {save: ['Save', 'Save'], cancel: ['Cancel', 'Cancel'], close: ['Tutup', 'Close'], delete: ['Delete', 'Delete'], apply: ['Terapkan', 'Apply'],
    print: ['Print', 'Print'], submit: ['Ajukan', 'Submit'], approve: ['Setujui', 'Approve'], reject: ['Tolak', 'Reject'], hold: ['Tahan', 'Hold']};
  // Label tombol preset ikut bahasa aktif (Back & Confirm & + New Record tetap).
  document.addEventListener('dl:lang', () => $$(document, '[data-dl-preset]').forEach(b => {
    const t = PRESET_T[b.dataset.dlPreset]; if (!t) return;
    const node = [...b.childNodes].reverse().find(n => n.nodeType === 3 && n.textContent.trim());
    if (node) node.textContent = t[currentLang === 'en' ? 1 : 0];
  }));








  /* ======================= FEEDBACK & STATES ======================= */
  // Toast bertumpuk: Devlib.toast('Tersimpan', 'success', 'Judul opsional').
  function toast(msg, tone = 'info', title = null) {
    let host = document.querySelector('.dl-toasts');
    if (!host) { host = document.createElement('div'); host.className = 'dl-toasts'; host.setAttribute('aria-live', 'polite'); document.body.appendChild(host); }
    const icon = {success: 'fa-circle-check', danger: 'fa-circle-xmark', warn: 'fa-triangle-exclamation', info: 'fa-circle-info'}[tone] || 'fa-circle-info';
    const t = document.createElement('div'); t.className = 'dl-toast2 dl-toast2--' + tone;
    t.innerHTML = `<i class="fa-solid ${icon}"></i><div>${title ? `<b>${esc(title)}</b>` : ''}<span>${esc(msg)}</span></div><button type="button" aria-label="${esc(T('close'))}">&times;</button>`;
    const bye = () => { t.classList.add('is-out'); setTimeout(() => t.remove(), 250); };
    $(t, 'button').addEventListener('click', bye);
    host.appendChild(t); setTimeout(bye, tone === 'danger' ? 7000 : 3500);
  }

  // Modal konfirmasi library (pengganti window.confirm): Devlib.confirm({...}).then(ok => ...)
  // reason: true = kotak alasan WAJIB diisi; hasil Promise = teks alasan (atau false bila batal).
  function confirmBox({title = T('cf_title'), message = T('cf_message'), tone = 'warn', ok = T('cf_ok'), cancel = null, reason = false} = {}) {
    return new Promise(resolve => {
      const cancelLabel = cancel || 'Cancel';
      const icon = {danger: 'fa-trash-can', warn: 'fa-triangle-exclamation', success: 'fa-circle-check', info: 'fa-circle-info'}[tone] || 'fa-circle-question';
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-confirm';
      ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="alertdialog" aria-modal="true"><div class="dl-confirm__body"><span class="dl-confirm__icon dl-confirm__icon--${tone}"><i class="fa-solid ${icon}"></i></span><div><h3>${esc(title)}</h3><p>${esc(message)}</p>${reason ? '<label class="dl-confirm__why"><span>' + esc(T('cf_reason')) + ' <b class="dl-field__req">*</b></span><textarea rows="2" data-why></textarea><em data-why-err></em></label>' : ''}</div></div>`
        + `<div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--secondary dl-btn--md" data-no>${esc(cancelLabel)}</button><button type="button" class="dl-btn dl-btn--${tone === 'danger' ? 'danger' : 'primary'} dl-btn--md" data-yes>${esc(ok)}</button></div></div>`;
      const done = v => { ov.remove(); document.removeEventListener('keydown', key); resolve(v); };
      const yes = () => { if (!reason) return done(true); const w = $(ov, '[data-why]').value.trim(); if (!w) { $(ov, '[data-why-err]').textContent = T('reason_required'); return $(ov, '[data-why]').focus(); } done(w); };
      const key = e => { if (e.key === 'Escape') done(false); else if (e.key === 'Enter' && !(reason && e.target.matches('textarea') && e.shiftKey)) { if (!reason || !e.target.matches('textarea')) { e.preventDefault(); yes(); } } };
      ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('[data-no]')) done(false); else if (e.target.closest('[data-yes]')) yes(); });
      document.addEventListener('keydown', key);
      document.body.appendChild(ov); (reason ? $(ov, '[data-why]') : $(ov, '[data-yes]')).focus();
    });
  }

  // data-dl-confirm="pesan" (+ data-dl-confirm-tone) di tombol/tautan: tanya dulu lewat modal library.
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-dl-confirm]');
    if (!b || b.dataset.dlConfirmed) return;
    e.preventDefault(); e.stopImmediatePropagation();
    confirmBox({message: b.dataset.dlConfirm, tone: b.dataset.dlConfirmTone || (b.closest('.dl-iconbtn--danger,.dl-btn--danger') ? 'danger' : 'warn')}).then(ok => {
      if (!ok) return;
      b.dataset.dlConfirmed = '1'; b.click(); delete b.dataset.dlConfirmed;
    });
  }, true);

  // Modal & drawer library: data-dl-open="<id>" membuka, [data-dl-close-modal] / Esc / klik luar menutup.
  // Keduanya di satu tombol = langkah wizard: tutup modal tempat tombol itu berada, lalu buka tujuannya.
  const layer = id => document.querySelector(`[data-dl-modal-id="${id}"],[data-dl-drawer-id="${id}"]`);
  const openLayer = id => { const l = layer(id); if (l) { l.classList.add('is-on'); const f = l.querySelector('input,textarea,select,button:not([data-dl-close-modal])'); if (f) setTimeout(() => f.focus(), 50); } };
  const closeLayer = l => l && l.classList.remove('is-on');
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-dl-open]'); if (o) { e.preventDefault(); if (o.hasAttribute('data-dl-close-modal')) closeLayer(o.closest('[data-dl-modal-id],[data-dl-drawer-id]')); openLayer(o.dataset.dlOpen); return; }
    const c = e.target.closest('[data-dl-close-modal]'); if (c) { closeLayer(c.closest('[data-dl-modal-id],[data-dl-drawer-id]')); return; }
    if (e.target.matches('[data-dl-modal-id].dl-mov')) closeLayer(e.target);
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') $$(document, '[data-dl-modal-id].is-on,[data-dl-drawer-id].is-on').slice(-1).forEach(closeLayer); });

  // Form dikirim -> tombol submit terkunci + "Memproses…" (cegah klik ganda).
  document.addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    const btn = e.submitter || e.target.querySelector('[type=submit]');
    if (!btn || btn.dataset.dlNoBusy !== undefined) return;
    setTimeout(() => { if (e.defaultPrevented) return; btn.disabled = true; btn.classList.add('is-busy'); btn.dataset.dlLabel = btn.innerHTML; btn.innerHTML = '<span class="dl-spinner__ring dl-spinner__ring--inline"></span> ' + esc(T('processing')); }, 0);
  });


  /* ======================= GLOBAL BEHAVIORS ======================= */
  // Kamus aplikasi (LOCKED 2026-10-01: tulis ENGLISH di kode). window.DL_DICT dari server = teks English -> terjemahan bahasa
  // halaman ('' = belum diterjemahkan). Teks yang belum dikenal dicatat ke server (DL_DICT_URL, tmerp: sys_dictionary) supaya
  // muncul di Setup > Translation Dictionary. T(kunci) = kamus DevLib [ID, EN]; bahasa lain lewat kamus aplikasi.
  const DICT = window.DL_DICT || {}, dictQ = new Set(); let dictTimer = null;
  const dictSend = () => {
    dictTimer = null; if (!dictQ.size || !window.DL_DICT_URL) return;
    const f = new FormData(); f.append('_token', window.DL_CSRF || ''); dictQ.forEach(x => f.append('texts[]', x)); dictQ.clear();
    try { navigator.sendBeacon(window.DL_DICT_URL, f); } catch (_) {}
  };
  window.addEventListener('pagehide', dictSend);
  const t = en => {
    if (en == null || en === '') return en ?? '';
    const s = String(en);
    if (!(s in DICT)) { DICT[s] = ''; if (window.DL_DICT_URL && s.length <= 255 && /\p{L}/u.test(s)) { dictQ.add(s); dictTimer ||= setTimeout(dictSend, 2000); } }
    return (NUM.lang !== 'en' && DICT[s]) || s;
  };
  // T + isian placeholder: Tf('g_matched', {n: 5, total: 9}) -> "5 baris cocok dari 9".
  const Tf = (k, v = {}) => T(k).replace(/\{(\w+)\}/g, (_, x) => v[x] ?? '');
  const T = k => {
    const e = window.DL_LANG && window.DL_LANG[k]; if (!e) return k;
    const row = Array.isArray(e) ? {id: e[0], en: e[1]} : e;
    return row[currentLang] ?? (currentLang === 'en' ? row.en : t(row.en));
  };

  // Bahasa: pilihan diingat (cookie dibaca server + localStorage), semua [data-dl-t] ikut berganti.
  document.addEventListener('dl:lang', () => {
    try { localStorage.setItem('dl-lang', currentLang); } catch (e) {}
    document.cookie = 'dl_lang=' + currentLang + ';path=/;max-age=31536000;SameSite=Lax';
    $$(document, '[data-dl-t]').forEach(el => { el.textContent = T(el.dataset.dlT); });
  });

  // Isian angka [data-dl-num] tampil sesuai bahasa (1.234,5 / 1,234.5) tetapi DIKIRIM ke server sebagai kanonik 1234.5
  // (submit biasa maupun new FormData(form)) -- server tidak pernah menebak bahasa. Kosong/bukan angka dikirim apa adanya.
  document.addEventListener('formdata', e => {
    const f = e.target, fd = e.formData; if (!f || !f.elements) return;
    const names = new Set(Array.from(f.elements).filter(x => x.name && !x.disabled && x.matches('[data-dl-num]')).map(x => x.name));
    names.forEach(n => {
      const vals = fd.getAll(n); fd.delete(n);
      vals.forEach(v => { const c = typeof v === 'string' ? numStr(v) : ''; fd.append(n, c === '' ? v : c); });
    });
  });

  // Dirty-guard: form (atau wadah isian non-form, mis. form JS) ber-atribut data-dl-guard yang sudah diubah -> tanya sebelum ditinggal.
  // Hanya isian bernama yang dihitung (kotak cari dropdown/grid tidak membuat form "berubah").
  const dirtyForms = new Set();
  const markDirty = e => {
    const f = e.target.closest && e.target.closest('[data-dl-guard]');
    if (f && e.target.name) { dirtyForms.add(f); f.classList.add('is-dirty'); }
  };
  document.addEventListener('input', markDirty, true);
  document.addEventListener('change', markDirty, true);
  document.addEventListener('submit', e => { dirtyForms.delete(e.target); e.target.classList.remove('is-dirty'); }, true);
  window.addEventListener('beforeunload', e => { if ([...dirtyForms].some(f => document.contains(f))) { e.preventDefault(); e.returnValue = ''; } });

  // Tiga pilihan: Simpan (kirim form), Buang (lanjut pergi), Batal (tetap di sini).
  // Mulai bersih (login baru / Logout): tab kerja ditutup dan Layer Menu kembali ke lapis utama; pilihan mini / tema / bahasa tetap.
  function resetWorkspace() {
    try {
      sessionStorage.removeItem('dl-shell-tabs');
      Object.keys(localStorage).filter(k => (k.startsWith('dl-lm-') || k.startsWith('dl-sm-')) && !k.endsWith('-mini')).forEach(k => localStorage.removeItem(k));
    } catch (e) {}
  }
  function askDirty() {
    return new Promise(resolve => {
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-confirm';
      ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="alertdialog" aria-modal="true"><div class="dl-confirm__body"><span class="dl-confirm__icon dl-confirm__icon--warn"><i class="fa-solid fa-floppy-disk"></i></span><div><h3>${esc(T('dirty_title'))}</h3><p>${esc(T('dirty_msg'))}</p></div></div>`
        + `<div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-v="stay">${esc(T('cancel'))}</button><button type="button" class="dl-btn dl-btn--secondary dl-btn--md" data-v="discard">${esc(T('dirty_nosave'))}</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-v="save">${esc(T('save'))}</button></div></div>`;
      const done = v => { ov.remove(); resolve(v); };
      ov.addEventListener('click', e => { const b = e.target.closest('[data-v]'); if (b) done(b.dataset.v); else if (e.target === ov) done('stay'); });
      document.body.appendChild(ov); $(ov, '[data-v=save]').focus();
    });
  }
  // Simpan = tombol Simpan form itu (preset save / data-dl-save), bukan sembarang submit pertama (bisa Hapus / + Tambah).
  function submitForm(f) {
    const btn = f.querySelector('[data-dl-save], [data-dl-preset=save]') || (f.tagName === 'FORM' ? f.querySelector('[type=submit]') : null);
    if (f.tagName !== 'FORM') { btn?.click(); return; }
    if (f.requestSubmit) f.requestSubmit(btn && btn.type === 'submit' ? btn : undefined); else if (btn) btn.click();
  }
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-dl-back], [data-dl-leave], a[href]:not([href^="#"]):not([target=_blank]):not([href^="javascript"])');
    const live = [...dirtyForms].filter(f => document.contains(f));
    if (!a || !live.length || a.closest('[data-dl-guard]') === live[0] && !a.hasAttribute('data-dl-back') && !a.hasAttribute('data-dl-leave')) return;
    e.preventDefault(); e.stopImmediatePropagation();
    askDirty().then(v => {
      if (v === 'save') submitForm(live[0]);
      else if (v === 'discard') { live.forEach(f => { dirtyForms.delete(f); f.classList.remove('is-dirty'); }); a.click(); }
    });
  }, true);

  // Breadcrumb mode menu: Modul/Grup diklik -> Layer Menu dibuka di cabang itu (di Shell induk bila layar ada di dalam tab).
  // Bila breadcrumb punya data-dl-crumb-url, halaman ini ikut pindah ke halaman cabang itu; isian yang belum disimpan ditanya dulu
  // (Save / Discard / Cancel), dan menu kiri baru berpindah setelah user memutuskan.
  document.addEventListener('click', e => {
    const c = e.target.closest('[data-dl-crumb-menu]'); if (!c) return;
    e.preventDefault();
    const msg = {dl: 'menu', path: JSON.parse(c.dataset.dlCrumbMenu || '[]'), leaf: c.dataset.leaf || null};
    const go = () => {
      const lm = document.querySelector('[data-dl-layer-menu]');
      if (window.parent !== window) window.parent.postMessage(msg, location.origin);
      else if (lm?.dlComponent) lm.dlComponent.goTo(msg.path, msg.leaf);
      if (c.dataset.dlCrumbUrl) location.href = c.dataset.dlCrumbUrl;
    };
    const live = [...dirtyForms].filter(f => document.contains(f));
    if (!c.dataset.dlCrumbUrl || !live.length) { go(); return; }
    askDirty().then(v => {
      if (v === 'save') submitForm(live[0]);
      else if (v === 'discard') { live.forEach(f => { dirtyForms.delete(f); f.classList.remove('is-dirty'); }); go(); }
    });
  });

  // Tombol / tautan ber-data-dl-open-item="kode menu" (+ data-label, data-url): di dalam Shell dibuka sebagai tab kerja
  // (menu kiri ikut menyorot layarnya); di luar Shell tautan biasa.
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-dl-open-item]'); if (!b || window.parent === window) return;
    e.preventDefault();
    window.parent.postMessage({dl: 'open-tab', code: b.dataset.dlOpenItem, label: b.dataset.label || b.textContent.trim(), url: b.dataset.url || b.getAttribute('href')}, location.origin);
  });

  // Form berpenanda data-dl-autosubmit: mengubah satu isian langsung mengirim form (mis. pilihan pola pada panel mesin).
  document.addEventListener('change', e => { const f = e.target.closest('form[data-dl-autosubmit]'); if (f) f.requestSubmit(); });

  // Isian ber-data-dl-swap="grup" (+ data-dl-was = nilai sekarang): satu nilai dipakai sekali dalam grup. Memilih nilai yang sudah dipakai isian lain
  // menukar keduanya (tanpa error). Elemen ber-data-dl-echo="nama-isian" (+ data-dl-echo-prefix) menampilkan awalan + teks pilihan isian itu.
  const echoAll = form => $$(form, '[data-dl-echo]').forEach(el => {
    const h = form.querySelector(`[name="${el.dataset.dlEcho}"]`), it = h?.closest('[data-dl-select]')?.querySelector(`.dl-dd__item[data-value="${h.value}"]`);
    const out = $(el, '.dl-display'); if (it && out) out.textContent = [el.dataset.dlEchoPrefix, it.textContent.trim()].filter(Boolean).join(' ');
  });
  document.addEventListener('change', e => {
    const w = e.target.closest('[data-dl-swap]');
    if (w && !w.dlSwapping) {
      const was = w.dataset.dlWas, now = e.target.value;
      w.dataset.dlWas = now;
      const other = $$(document, `[data-dl-swap="${w.dataset.dlSwap}"]`).find(o => o !== w && o.dataset.dlWas === now);
      if (other) {
        other.dlSwapping = true;
        other.dataset.dlWas = was;
        setField(e.target.form, $(other, '[name]').name, was);
        other.dlSwapping = false;
      }
    }
    if (e.target.form) echoAll(e.target.form);
  });

  // Pintasan keyboard: tombol ber-data-dl-key (preset Simpan = Ctrl+S, Baru = Alt+N), '?' = daftar pintasan.
  const keyName = e => [e.ctrlKey || e.metaKey ? 'ctrl' : '', e.altKey ? 'alt' : '', e.shiftKey ? 'shift' : '', e.key.toLowerCase()].filter(Boolean).join('+');
  const visible = el => el.offsetParent !== null && !el.disabled;
  const BUILTIN = [['ctrl+k', 'sc_search'], ['esc', 'sc_esc'], ['f1', 'sc_help'], ['f10', 'sc_dev'], ['?', 'sc_list']];
  function showShortcuts() {
    const own = $$(document, '[data-dl-key]').filter(visible).map(b => [b.dataset.dlKey, b.textContent.trim() || b.title]);
    const rows = [...new Map([...own, ...BUILTIN.map(([k, d]) => [k, T(d)])].map(r => [r[0], r])).values()];
    const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
    ov.innerHTML = `<div class="dl-modal dl-modal--sm"><div class="dl-modal__head"><h3><i class="fa-solid fa-keyboard"></i> ${esc(T('shortcuts'))}</h3><button type="button" class="dl-modal__x">&times;</button></div><div class="dl-modal__body"><table class="dl-keys">${rows.map(([k, d]) => `<tr><td>${k.split('+').map(p => `<kbd>${esc(p === 'ctrl' ? 'Ctrl' : p.length === 1 ? p.toUpperCase() : p[0].toUpperCase() + p.slice(1))}</kbd>`).join(' + ')}</td><td>${esc(d)}</td></tr>`).join('')}</table></div></div>`;
    ov.addEventListener('click', e => { if (e.target === ov || e.target.closest('.dl-modal__x')) ov.remove(); });
    document.body.appendChild(ov);
  }
  document.addEventListener('keydown', e => {
    const k = keyName(e), typing = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
    if (k === 'f10') { e.preventDefault(); toggleDev(); return; }
    // F1 = Bantuan & dukungan. Layar di dalam tab (iframe) meneruskan ke Shell induk; Shell sendiri menangani lewat dl:help.
    if (k === 'f1') { e.preventDefault(); if (window.parent !== window) window.parent.postMessage({dl: 'help'}, location.origin); else document.dispatchEvent(new CustomEvent('dl:help')); return; }
    if ((k === '?' || k === 'shift+?') && !typing) { e.preventDefault(); showShortcuts(); return; }
    const btn = $$(document, `[data-dl-key="${k}"]`).find(visible);
    if (btn) { e.preventDefault(); btn.click(); }
  });

  function initDevToggle(b) { b.addEventListener('click', () => setDevMode(!document.body.classList.contains('dl-dev'))); }

  /* ============================ BASIC ============================ */
  // Format lokal baku di browser (sama dengan Devlib\Locale di PHP): zona waktu sistem, Rupiah, angka.
  const TZ = () => document.documentElement.dataset.dlTz || 'Asia/Jakarta';
  // Tanggal hari ini menurut zona sistem -> [tahun, bulan, tanggal].
  const todayParts = () => new Date().toLocaleDateString('en-CA', {timeZone: TZ()}).split('-').map(Number);
  // Urutan tanggal dalam kalender tahun kabisat (0..365): tanggal yang sama = indeks yang sama setiap tahun.
  const dayIndex = () => { const [, m, d] = todayParts(); return (Date.UTC(2024, m - 1, d) - Date.UTC(2024, 0, 1)) / 864e5; };
  // Hari ke-n tahun berjalan (1..366).
  const dayOfYear = () => { const [y, m, d] = todayParts(); return (Date.UTC(y, m - 1, d) - Date.UTC(y, 0, 1)) / 864e5 + 1; };
  const fmt = {
    number: (n, d = 0) => numFmt(n, d),
    // desimal secukupnya (maks max): 12,5 / 1.250
    num: (n, max = 2) => numFmt(n, 0, max),
    // isian yang sedang diedit (ala sel Excel): tanpa ribuan, desimal ikut bahasa -- 1234,5
    raw: v => DlNumber.of(v)?.raw() ?? '',
    money: (n, cur = 'IDR', d = 0) => cur + ' ' + fmt.number(n, d),
    // Tanggal di grid (user 2026-09-30): selalu dd-Mmm-yyyy, mis. 10-Oct-2026. 'YYYY-MM-DD...' dibaca apa adanya (tanpa geser zona waktu).
    gridDate: v => {
      if (!v) return '';
      const d = DlDate.of(v) || (isNaN(new Date(v)) ? null : DlDate.of(new Date(v)));
      return d ? d.display() : String(v);
    },
    // short = dd-Mmm-yyyy (baku seluruh aplikasi, sama dgn gridDate) menurut zona waktu aplikasi.
    date: (v = new Date(), style = 'short') => style === 'short' ? fmt.gridDate(v instanceof Date || /[T ]\d{2}:\d{2}.*[+-]\d{2}:?\d{2}$|Z$/.test(String(v)) ? new Date(v) : v) : new Date(v).toLocaleDateString(NUM.intl, style === 'long' ? {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: TZ()} : {day: '2-digit', month: '2-digit', year: 'numeric', timeZone: TZ()}),
    time: (v = new Date()) => (DlTime.of(v instanceof Date ? v : new Date(v))?.display() ?? '') + ' WIB',
  };






  /* ===================== SMALL COMPONENTS (lanjutan) ===================== */
  // Mask: 9 = digit, karakter lain disisipkan apa adanya.
  const applyMask = (pat, d) => { let out = '', j = 0; for (const ch of pat) { if (j >= d.length) break; out += ch === '9' ? d[j++] : ch; } return out; };
  const fieldErr = (el, msg) => { const f = el.closest('.dl-field'); if (!f) return; f.classList.toggle('is-invalid', !!msg); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = msg || ''; };

  // Accordion: satu kode untuk vertikal & horizontal; aturan buka/tutup dari grupnya.
  document.addEventListener('click', e => {
    const h = e.target.closest('.dl-accordion__head'); if (!h) return;
    const acc = h.parentElement, grp = acc.parentElement.closest('[data-dl-accgroup]'), horiz = grp && grp.classList.contains('dl-accgroup--horizontal');
    const open = !acc.classList.contains('is-open');
    if (!open && horiz) return;
    if (open && grp && grp.dataset.multiple !== '1') $$(grp, ':scope > .dl-accordion.is-open').forEach(a => { a.classList.remove('is-open'); $(a, '.dl-accordion__head').setAttribute('aria-expanded', 'false'); });
    acc.classList.toggle('is-open', open); h.setAttribute('aria-expanded', String(open));
    if (grp && grp.dataset.remember) { try { localStorage.setItem('dl-acc:' + grp.dataset.remember, JSON.stringify($$(grp, ':scope > .dl-accordion.is-open').map(a => a.dataset.accKey).filter(Boolean))); } catch (_) {} }
  });




  // Kartu kredit: [kode, awalan, kelompok digit, panjang sah, digit CVV, ikon FA, nama].
  const CC = [
    ['amex', /^3[47]/, [4, 6, 5], [15], 4, 'fa-brands fa-cc-amex', 'American Express'],
    ['diners', /^3(0[0-5]|[689])/, [4, 6, 4], [14], 3, 'fa-brands fa-cc-diners-club', 'Diners Club'],
    ['visa', /^4/, [4, 4, 4, 4, 3], [13, 16, 19], 3, 'fa-brands fa-cc-visa', 'Visa'],
    ['mastercard', /^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/, [4, 4, 4, 4], [16], 3, 'fa-brands fa-cc-mastercard', 'Mastercard'],
    ['jcb', /^35/, [4, 4, 4, 4, 3], [16, 19], 3, 'fa-brands fa-cc-jcb', 'JCB'],
    ['discover', /^(6011|65|64[4-9])/, [4, 4, 4, 4, 3], [16, 19], 3, 'fa-brands fa-cc-discover', 'Discover'],
    ['unionpay', /^62/, [4, 4, 4, 4, 3], [16, 17, 18, 19], 3, 'fa-solid fa-credit-card', 'UnionPay'],
  ];
  const luhn = d => { let s = 0; for (let i = 0; i < d.length; i++) { let x = +d[d.length - 1 - i]; if (i % 2) { x *= 2; if (x > 9) x -= 9; } s += x; } return s % 10 === 0; };







  /* ============================ MASTER ============================ */
  // Isi satu field library dari nilai (dipakai Master, bisa dipakai layar lain lewat Devlib.fill).
  // Ganti daftar pilihan field select (dropdown bersambung). opts = [[value, label], ...] atau {value: label}.
  // Nilai lama dikosongkan; disabled = kotak dikunci (mis. tak ada pilihan).
  function setOptions(form, name, opts, {disabled = false} = {}) {
    const h = $(form, `[name="${CSS.escape(name)}"]`), sel = h && h.closest('[data-dl-select]');
    if (!sel) return;
    const list = Array.isArray(opts) ? opts : Object.entries(opts || {});
    $(sel, '[data-dl-dd-list]').innerHTML = list.map(([v, l]) => `<div class="dl-dd__item" data-value="${esc(v)}">${esc(l)}</div>`).join('');
    h.value = '';
    $(sel, '[data-dl-dd-text]').textContent = sel.dataset.empty;
    $(sel, '[data-dl-dd-toggle]').disabled = disabled || !list.length;
  }

  function setField(form, name, v) {
    const els = $$(form, `[name="${CSS.escape(name)}"]`); if (!els.length) return;
    const el = els[0], s = v == null ? '' : String(v);
    if (el.type === 'radio') { els.forEach(r => { r.checked = r.value === s; }); return; }
    if (el.type === 'checkbox') { el.checked = v === true || s === '1' || s === 'true'; el.dispatchEvent(new Event('change', {bubbles: true})); return; }
    el.value = s;
    const sel = el.closest('[data-dl-select]');
    if (sel) { const it = $(sel, `.dl-dd__item[data-value="${CSS.escape(s)}"]`); $(sel, '[data-dl-dd-text]').textContent = it ? it.textContent : sel.dataset.empty; return; }
    const lk = el.closest('[data-dl-lookup]');
    if (lk) { const r = JSON.parse(lk.dataset.rows || '[]').find(x => String(x[lk.dataset.key]) === s); $(lk, '[data-dl-dd-text]').textContent = r ? r[lk.dataset.label] : T('f_choose'); return; }
    const dt = el.closest('[data-dl-date]');
    if (dt) { $(dt, '[data-dl-date-text]').value = DlDate.of(s)?.display() ?? ''; return; }
    const pb = el.closest('[data-dl-phonebox]');
    if (pb) { const i = $(pb, '[data-dl-phone]'); i.value = s; i.dispatchEvent(new Event('input', {bubbles: true})); return; }
    el.dispatchEvent(new Event('input', {bubbles: true}));
  }
  // clear=true: field yang tidak ada di data dikosongkan (untuk data baru).
  function fillForm(form, data, clear = false) {
    const names = new Set($$(form, '[name]').filter(e => !e.closest('[data-l-inputs]')).map(e => e.name).filter(n => n && n !== '_token' && !n.includes('[') && !n.endsWith('_country') && !n.endsWith('_dial')));
    names.forEach(n => { if (n in data) setField(form, n, data[n]); else if (clear) setField(form, n, n.endsWith('_ppn') ? true : n.endsWith('_discount') ? '0' : ''); });
    $$(form, '[data-dl-lines]').forEach(l => { if (l.dlLines && (clear || l.dataset.name in data)) l.dlLines.set(data[l.dataset.name] || []); });
    $$(form, '[data-dl-members]').forEach(m => m.dlMembers?.reset());
    $$(form, '.dl-field.is-invalid').forEach(f => { f.classList.remove('is-invalid'); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = ''; });
    dirtyForms.delete(form); form.classList.remove('is-dirty');
  }
  // Input pendamping (mata uang / kode negara) selalu terisi, jadi tidak dihitung sebagai nilai.
  const hasValue = f => $$(f, '[name]:not([data-dl-prefix-value])').some(i => (i.type === 'checkbox' || i.type === 'radio') ? i.checked : i.value.trim() !== '');



  /* ============================ WIZARD ============================ */
  // Teks tampil sebuah field library (untuk ringkasan Review).
  function fieldText(f) {
    const q = s => $(f, s);
    if (q('[data-dl-select], [data-dl-lookup]')) return q('[data-dl-dd-text]').textContent.trim();
    if (q('[data-dl-date]')) return q('[data-dl-date-text]').value;
    if (q('[data-dl-phonebox]')) return q('[data-dl-phone]').value ? q('[data-dl-prefix-b]').textContent + ' ' + q('[data-dl-phone]').value : '';
    if (q('[data-dl-toggle]')) return q('[data-dl-toggle-label]').textContent;
    if (q('[data-dl-sign]')) return q('[data-dl-value]').value ? '✓ ' + T('sg_signed') : '';
    const radio = q('input[type=radio]:checked'); if (radio) return radio.closest('label').textContent.trim();
    const checks = $$(f, 'input[type=checkbox]:checked'); if (checks.length) return checks.map(c => c.closest('label')?.textContent.trim() || '✓').join(', ');
    const i = q('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea, select');
    return i ? (i.type === 'password' ? (i.value ? '••••••' : '') : i.value) : '';
  }




  /* ============================ TERBILANG ============================ */
  // Sama persis dengan Devlib\Terbilang (PHP). cur = [simbol, nama ID, nama EN, sub ID, sub EN, desimal].
  const W_ID = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
  const W_EN = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
  const W_EN_T = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
  const W_SC_ID = [[1e15, 'kuadriliun'], [1e12, 'triliun'], [1e9, 'miliar'], [1e6, 'juta'], [1e3, 'ribu']];
  const W_SC_EN = [[1e15, 'quadrillion'], [1e12, 'trillion'], [1e9, 'billion'], [1e6, 'million'], [1e3, 'thousand']];
  const wScale = (n, sc, say) => { for (const [d, w] of sc) if (n >= d) return `${say(Math.floor(n / d))} ${w} ${say(n % d)}`.trim(); return ''; };
  const wId = n => n < 12 ? W_ID[n] : n < 20 ? W_ID[n - 10] + ' belas' : n < 100 ? `${W_ID[Math.floor(n / 10)]} puluh ${W_ID[n % 10]}`.trim()
    : n < 200 ? `seratus ${wId(n - 100)}`.trim() : n < 1000 ? `${W_ID[Math.floor(n / 100)]} ratus ${wId(n % 100)}`.trim()
    : n < 2000 ? `seribu ${wId(n - 1000)}`.trim() : wScale(n, W_SC_ID, wId);
  const wEn = n => n < 20 ? W_EN[n] : n < 100 ? W_EN_T[Math.floor(n / 10)] + (n % 10 ? '-' + W_EN[n % 10] : '')
    : n < 1000 ? `${W_EN[Math.floor(n / 100)]} hundred ${wEn(n % 100)}`.trim() : wScale(n, W_SC_EN, wEn);
  function words(value, cur = null, lang = currentLang) {
    const s = String(value ?? '').trim(); if (s === '' || s === '-') return '';
    const en = lang === 'en', say = en ? wEn : wId, neg = s.startsWith('-');
    let [int, frac = ''] = s.replace(/^[-+]/, '').split('.');
    int = int.replace(/\D/g, '').replace(/^0+/, ''); frac = frac.replace(/\D/g, '');
    let out = int === '' ? (en ? 'zero' : 'nol') : say(+int);
    if (cur) {
      out += ' ' + (en ? cur[2] : cur[1]);
      const sub = cur[5] > 0 ? +frac.padEnd(cur[5], '0').slice(0, cur[5]) : 0;
      if (sub > 0) out += (en ? ' and ' : ' dan ') + say(sub) + ' ' + (en ? cur[4] : cur[3]);
    } else if (frac.replace(/0+$/, '')) out += (en ? ' point ' : ' koma ') + [...frac.replace(/0+$/, '')].map(d => d === '0' ? (en ? 'zero' : 'nol') : say(+d)).join(' ');
    out = (neg ? 'minus ' : '') + out;
    return out.charAt(0).toUpperCase() + out.slice(1);
  }









  /* ============================ IMPORT ============================ */
  // CSV sederhana tapi benar: tanda kutip, "" di dalam kutip, baris baru di dalam kutip, BOM, deteksi pemisah.
  function parseCsv(text) {
    text = text.replace(/^\ufeff/, '');
    const first = text.split(/\r?\n/)[0] || '', delim = [';', '\t', ','].map(d => [d, first.split(d).length]).sort((a, b) => b[1] - a[1])[0][0];
    const rows = []; let row = [], cur = '', q = false;
    for (let i = 0; i < text.length; i++) {
      const ch = text[i];
      if (q) { if (ch === '"' && text[i + 1] === '"') { cur += '"'; i++; } else if (ch === '"') q = false; else cur += ch; continue; }
      if (ch === '"') q = true; else if (ch === delim) { row.push(cur); cur = ''; } else if (ch === '\n' || ch === '\r') { if (ch === '\r' && text[i + 1] === '\n') i++; row.push(cur); rows.push(row); row = []; cur = ''; } else cur += ch;
    }
    if (cur !== '' || row.length) { row.push(cur); rows.push(row); }
    return {delim, rows: rows.filter(r => r.some(c => c.trim() !== ''))};
  }
  const csvCell = v => /[",;\n\t]/.test(String(v ?? '')) ? `"${String(v).replace(/"/g, '""')}"` : String(v ?? '');
  const downloadCsv = (name, lines) => { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.map(r => r.map(csvCell).join(';')).join('\r\n')], {type: 'text/csv'})); a.download = name; a.click(); };
  // Angka ketikan dalam bahasa tampilan -> Number (NaN bila bukan angka).
  const parseNum = v => { const c = numStr(v); return c === '' ? NaN : +c; };
  // Ketikan tanggal -> ISO yyyy-mm-dd (null = tidak sah). Lewat objek DlDate.
  const parseDate = v => DlDate.of(v)?.iso() ?? null;










  /* ============================ CHARTS ============================ */
  const CH_TONES = ['accent', 'success', 'warn', 'danger', 'violet', 'info', 'neutral', 'teal'];
  const chNum = (v, f) => {
    if (v == null || isNaN(v)) return '–';
    const U = NUM.lang === 'id' ? [' T', ' M', ' jt', ' rb'] : ['T', 'B', 'M', 'K'];
    const a = Math.abs(v), s = a >= 1e12 ? [1e12, U[0]] : a >= 1e9 ? [1e9, U[1]] : a >= 1e6 ? [1e6, U[2]] : a >= 1e4 ? [1e3, U[3]] : [1, ''];
    const n = numFmt(v / s[0], 0, s[0] > 1 ? 1 : 2) + s[1];
    return f === 'money' ? 'IDR ' + n : f === 'percent' ? n + '%' : n;
  };
  const niceMax = m => { if (m <= 0) return 1; const p = 10 ** Math.floor(Math.log10(m)), f = m / p; return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * p; };


  /* ============================ QR & BARCODE ============================ */
  // Library dimuat sekali dari cdnjs saat komponen pertama muncul (tidak membebani halaman lain).
  const libOnce = {};
  const loadLib = (url, test) => test() ? Promise.resolve() : (libOnce[url] = libOnce[url] || new Promise((ok, no) => { const s = document.createElement('script'); s.src = url; s.onload = ok; s.onerror = no; document.head.appendChild(s); }));



















  /* ============================ AUTH: REGISTER / FORGOT ============================ */
  const AUTH_T = {
    id: {r_heading: 'Daftar akun', r_sub: 'Buat akun baru untuk mengakses aplikasi.', r_approval: 'Akun baru harus disetujui admin. Grup & hak akses ditentukan admin saat persetujuan.', r_type: 'Daftar sebagai', r_name: 'Nama lengkap', r_email: 'Email', r_verify: 'Verifikasi', r_user: 'Username (otomatis dari email)', r_wa: 'No. WhatsApp aktif', r_send_otp: 'Kirim OTP', r_otp: 'Kode OTP dari WhatsApp', r_pass: 'Password', r_conf: 'Konfirmasi', r_submit: 'Kirim pendaftaran', r_done_h: 'Pendaftaran terkirim', r_done_b: 'Akun Anda menunggu persetujuan admin. Setelah disetujui, Anda bisa masuk memakai email dan password ini.',
      nik: 'NIK (No. KTP)', nrp: 'NRP (No. Induk Pegawai)', nik_bad: 'NIK harus 16 digit angka.', nik_ok: 'Format NIK valid.', email_bad: 'Format email tidak valid.', email_sent: 'Email terverifikasi.', otp_sent: 'OTP dikirim ke WhatsApp.', wa_ok: 'WhatsApp terverifikasi.', wa_bad: 'Masukkan nomor WhatsApp dulu.', otp_bad: 'Kode OTP belum lengkap.', c_len: 'Min. 8 karakter.', c_weak: 'Tambahkan huruf & angka.', c_ok: 'Password kuat.', m_ok: 'Password cocok.', m_bad: 'Password belum cocok.', need_verify: 'Verifikasi email dan WhatsApp dulu sebelum mengirim.', need_fill: 'Lengkapi semua isian wajib (*).',
      f_heading: 'Lupa password', f_sub_email: 'Masukkan email akun Anda. Kami kirim tautan untuk atur ulang password.', f_send: 'Kirim tautan reset', f_done_email: 'Jika email terdaftar, tautan reset sudah dikirim.', f_s1: 'Verifikasi', f_s2: 'Password baru', f_sub_q: 'Jawab 4 pertanyaan sesuai data akun Anda. Tanpa email.', f_birth: 'Tanggal lahir', f_phone: 'No. HP terdaftar', f_mother: 'Nama ibu kandung', f_next: 'Lanjut', f_sub_new: 'Data cocok. Buat password baru.', f_new: 'Password baru', f_save: 'Save', f_done_h: 'Password diperbarui', f_done_b: 'Silakan masuk memakai password baru.', f_wrong: 'Data tidak cocok dengan akun mana pun.', l_email: 'Email', l_login: 'Username'},
    en: {r_heading: 'Create account', r_sub: 'Create a new account to access the app.', r_approval: 'New accounts require admin approval. Group & permissions are set by the admin on approval.', r_type: 'Register as', r_name: 'Full name', r_email: 'Email', r_verify: 'Verify', r_user: 'Username (auto from email)', r_wa: 'Active WhatsApp number', r_send_otp: 'Send OTP', r_otp: 'OTP code from WhatsApp', r_pass: 'Password', r_conf: 'Confirm', r_submit: 'Submit registration', r_done_h: 'Registration submitted', r_done_b: 'Your account is awaiting admin approval. Once approved, you can sign in with this email and password.',
      nik: 'NIK (ID card no.)', nrp: 'Employee ID (NRP)', nik_bad: 'NIK must be 16 digits.', nik_ok: 'Valid NIK format.', email_bad: 'Invalid email format.', email_sent: 'Email verified.', otp_sent: 'OTP sent to WhatsApp.', wa_ok: 'WhatsApp verified.', wa_bad: 'Enter a WhatsApp number first.', otp_bad: 'OTP code is incomplete.', c_len: 'Min. 8 characters.', c_weak: 'Add letters & numbers.', c_ok: 'Strong password.', m_ok: 'Passwords match.', m_bad: 'Passwords do not match yet.', need_verify: 'Verify email and WhatsApp before submitting.', need_fill: 'Complete all required (*) fields.',
      f_heading: 'Forgot password', f_sub_email: 'Enter your account email. We will send a reset link.', f_send: 'Send reset link', f_done_email: 'If the email is registered, a reset link has been sent.', f_s1: 'Verify', f_s2: 'New password', f_sub_q: 'Answer 4 questions matching your account. No email needed.', f_birth: 'Date of birth', f_phone: 'Registered phone', f_mother: "Mother's maiden name", f_next: 'Next', f_sub_new: 'Data matched. Create a new password.', f_new: 'New password', f_save: 'Save', f_done_h: 'Password updated', f_done_b: 'Please sign in with your new password.', f_wrong: 'The data does not match any account.', l_email: 'Email', l_login: 'Username'},
  };
  const at = k => AUTH_T[currentLang]?.[k] ?? AUTH_T.id[k] ?? k;
  const authLang = root => { const f = () => $$(root, '[data-i18n]').forEach(x => { const t = AUTH_T[currentLang]?.[x.dataset.i18n]; if (t) x.textContent = t; }); document.addEventListener('dl:lang', f); f(); return f; };
  // Pesan di bawah field: ok -> hint hijau, bad -> error merah, '' -> kosong.
  const fieldMsg = (f, msg, ok) => { if (!f) return; const er = $(f, '[data-dl-field-error]'), h = $(f, '[data-dl-field-hint]'); f.classList.toggle('is-invalid', !!msg && !ok); f.classList.toggle('is-valid', !!msg && ok); if (er) er.textContent = ok ? '' : msg; if (h && ok) { h.dataset.orig ??= h.textContent; h.textContent = msg; } if (h && !msg && h.dataset.orig != null) h.textContent = h.dataset.orig; };
  const fld = (root, name) => $(root, `[data-dl-field="${name}"]`);
  const val = (root, name) => (root.querySelector(`[name="${name}"]`)?.value || '').trim();
  const passCheck = (root, pName, cName) => {
    const p = val(root, pName), c = val(root, cName);
    const pm = !p ? '' : p.length < 8 ? at('c_len') : !(/[a-z]/i.test(p) && /\d/.test(p)) ? at('c_weak') : at('c_ok');
    fieldMsg(fld(root, pName), pm, pm === at('c_ok'));
    fieldMsg(fld(root, cName), c ? (c === p ? at('m_ok') : at('m_bad')) : '', c === p);
    return pm === at('c_ok') && c === p;
  };
  const emit = (root, n, detail) => root.dispatchEvent(new CustomEvent(n, {bubbles: true, cancelable: true, detail}));





  // Asal data kolom (grid/lines): {src: 'tm2sdi.iqp_inquiry.qty'} -> tooltip Mode Developer di header kolom.
  const srcAttr = (c, kind = 'kolom') => c && c.src ? ` data-dl="${kind} ${esc(c.label || c.key)}" data-dl-class="kolom" data-dl-src="${esc(c.src)}"` : '';
  Devlib.h = {srcAttr, $, $$, esc, showQr, CASES, CASE_LABEL, applyCase, idNum, bindPanel, calcPanel, pickIndex, dayOfYear, WMO, LOGIN_T, typeIcon, HOME, setDevMode, initDevMode, PRESET_T, toast, confirmBox, layer, openLayer, closeLayer, T, Tf, t, dirtyForms, markDirty, askDirty, submitForm, resetWorkspace, keyName, visible, BUILTIN, showShortcuts, initDevToggle, TZ, fmt, applyMask, fieldErr, CC, luhn, setField, setOptions, fillForm, hasValue, fieldText, W_ID, W_EN, W_EN_T, W_SC_ID, W_SC_EN, wScale, wId, wEn, words, parseCsv, csvCell, downloadCsv, parseNum, numStr, numIn, numFmt, NUM, MON_EN, dow2, DlDate, DlNumber, DlTime, DlDateTime, DlText, parseDate, CH_TONES, chNum, niceMax, libOnce, loadLib, AUTH_T, at, authLang, fieldMsg, fld, val, passCheck, emit};
  Object.assign(Devlib, {toast, setDevMode, fmt, confirm: confirmBox, open: openLayer, close: id => closeLayer(layer(id)), fill: fillForm, setField, words, resetWorkspace}, {initDevMode});
})();
