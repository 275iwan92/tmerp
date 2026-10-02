/* DevLib · DielineX */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, T, val} = Devlib.h;
  /* ===================== DIELINE v2 / v3 (salinan v1 + ukuran) ===================== */
  // v1 (initDieline) SENGAJA tidak diubah. level 2 = garis ukuran + istilah internasional + mm/cm;
  // level 3 = v2 + judul spesifikasi + panjang cutting/creasing/perforasi + jendela 3D (kotak bisa diputar).
  // Hanya "lem" UI; semua geometri dihitung TMDieline (salinan utuh dari tmerp).
  // Ukuran produk (baris 1 panel Dimensions); param lain (flap, bleed, dst) masuk baris "Flaps & Allowances".
  const MAIN_DIMS = ['product_length', 'product_width', 'product_height', 'diameter', 'lid_height', 'top_length', 'top_width'];
  const ROT0 = {x: -22, y: -38, z: 0, k: 1};
  class DielineX extends Devlib.Component {
    static selector = "[data-dl-dieline-x]";
    init() {
      const self = this;
      this.T = JSON.parse(this.el.dataset.types || '{}');
      this.name = this.el.dataset.name;
      this.TERMS = JSON.parse(this.el.dataset.terms || '{}');
      this.uid = 'dld' + Math.random().toString(36).slice(2, 8);
      this.v3 = this.el.dataset.level === '3';
      this.code = this.el.dataset.type;
      this.dims = JSON.parse(this.el.dataset.dims || '{}');
      // Lubang / jendela di panel depan: bukan param tipe (prod_type_param), disimpan bersama dims hanya bila dipakai.
      this.hole = {type: ['ROUND', 'RECT'].includes(this.dims.hole_type) ? this.dims.hole_type : 'NONE', w: +this.dims.hole_w || '', h: +this.dims.hole_h || ''};
      this.zoom = 1;
      this.manual = false;
      this.unit = this.el.dataset.unit || 'mm';
      // Artwork tersimpan (url) dipakai sampai user memilih file baru (setArtwork / setArtworkInner).
      // Cetak 2 sisi: tab Sisi Luar / Sisi Dalam; this.art = artwork sisi yang sedang tampil.
      this.saved = this.el.dataset.artwork || null;
      this.savedIn = this.el.dataset.artworkInner || null;
      this.outArt = this.saved;
      this.inArt = this.savedIn;
      this.side = 'out';
      this.art = this.outArt;
      this.rot = {...ROT0};
      this.q = s => $(this.el, s);
      this.fw = this.q('[data-d-fw]');
      this.fh = this.q('[data-d-fh]');
      this.fa = this.q('[data-d-fa]');
      this.el.addEventListener('input', e => {
        const d = e.target.dataset.dDim;
        if (d !== undefined) { this.dims[d] = e.target.value === '' ? '' : parseFloat(e.target.value.replace(',', '.')); return this.redraw(); }
        const hk = e.target.dataset.dHole;
        if (hk === 'w' || hk === 'h') { this.hole[hk] = parseFloat(e.target.value.replace(',', '.')) || ''; return this.redraw(); }
        if (e.target.matches('[data-d-fw],[data-d-fh]') && this.manual) this.redraw();
      });
      this.el.addEventListener('change', e => {
        if (e.target.name === this.name + '[type]') { this.code = e.target.value; this.dims = {}; this.fields(); this.redraw(); }
        if (e.target.name === this.name + '[hole_ui]') { this.hole.type = e.target.value || 'NONE'; this.holeUi(); this.redraw(); }
        if (e.target.matches('[data-d-manual]')) { this.manual = e.target.checked; this.fw.readOnly = this.fh.readOnly = !this.manual; this.redraw(); }
        if (e.target.matches('[data-d-dims-on], [data-d-opt]')) this.redraw();
      });
      this.el.addEventListener('click', e => {
        const s = e.target.closest('[data-d-size]');
        if (s) { Object.assign(this.dims, this.T[this.code].sizes[+s.dataset.dSize].dims); $$(this.el, '[data-d-dim]').forEach(i => { i.value = this.dims[i.dataset.dDim] ?? ''; }); return this.redraw(); }
        const u = e.target.closest('[data-d-unit] .dl-segment__opt'); if (u) { this.unit = u.dataset.value; return this.redraw(); }
        const sd = e.target.closest('[data-d-side] .dl-segment__opt'); if (sd) return this.showSide(sd.dataset.value);
        const a3 = e.target.closest('[data-d-3d-act]'); if (a3) return this.act3d(a3.getAttribute('data-d-3d-act'));
        const z = e.target.closest('[data-d-zoom]'); if (z) { this.zoom = Math.min(3, Math.max(0.4, Math.round((this.zoom + +z.dataset.dZoom * 0.2) * 10) / 10)); return this.redraw(); }
        if (e.target.closest('[data-d-svg]')) {
          const svgEl = this.q('[data-d-preview] svg'); if (!svgEl) return toast(Devlib.h.t('Fill in the dimensions first.'), 'warn');
          const out = svgEl.cloneNode(true); out.setAttribute('style', 'background:#1b1f2a'); out.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
          const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([out.outerHTML], {type: 'image/svg+xml'})); a.download = `dieline-${this.code.toLowerCase()}.svg`; a.click();
        }
      });
      this.el.dlDielineX = {get dims() { return {...self.dims}; }, setType: c => { this.code = c; this.dims = {}; this.fields(); this.redraw(); },
        setDims: d => { Object.assign(this.dims, d); $$(this.el, '[data-d-dim]').forEach(i => { i.value = this.dims[i.dataset.dDim] ?? ''; }); this.redraw(); }, redraw: this.redraw,
        // url gambar (object URL / storage) atau null = kembali ke artwork tersimpan.
        setArtwork: url => { this.outArt = url || this.saved; if (this.side === 'out') this.showSide('out'); },
        // File sisi dalam baru langsung ditampilkan; dihapus & tak ada yang tersimpan -> kembali ke sisi luar.
        setArtworkInner: url => { this.inArt = url || this.savedIn; this.showSide(url ? 'in' : this.inArt ? this.side : 'out'); }};
      if (!window.TMDieline) { this.q('[data-d-preview]').textContent = Devlib.h.t('TMDieline is not loaded.'); return; }
      this.bind3d();
      this.holeUi(true);
      this.sideUi();
      this.fields();this.redraw();
      if (this.art) this.loadArt();
    }

    showSide(side) {
      this.side = side === 'in' && this.inArt ? 'in' : 'out';
      this.art = this.side === 'in' ? this.inArt : this.outArt;
      this.sideUi();
      this.loadArt();
    }

    sideUi() {
      const box = this.q('[data-d-side]'), mw = this.q('[data-d-mirror-wrap]');
      if (box) { box.hidden = !this.inArt; $$(box, '.dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === this.side)); }
      if (mw) mw.hidden = this.side !== 'in';
    }

    term(k, p) {
      return this.TERMS[k] ? `${this.TERMS[k][0]} (${this.TERMS[k][1]})` : p.label;
    }

    numStr(v) {
      return v === '' || v == null || isNaN(v) ? '' : (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
    }

    fields() {
      const t = this.T[this.code]; if (!t) return;
      this.dims = Object.fromEntries(Object.entries(t.params).map(([k, p]) => [k, this.dims[k] ?? p.default ?? '']));
      const num = Object.entries(t.params).filter(([, p]) => p.type === 'NUM' || typeof p.default === 'number'), d2 = this.q('[data-d-dims2]');
      const row = ([k, p]) => `<label class="dl-dieline__dim" title="${esc(p.label)}"><span>${esc(this.term(k, p))}</span><span class="dl-field__box"><input class="dl-input" type="text" inputmode="decimal" name="${esc(this.name)}[dims][${esc(k)}]" data-d-dim="${esc(k)}" value="${esc(this.dims[k] ?? '')}"><em>${esc(p.unit || 'mm')}</em></span></label>`;
      this.q('[data-d-dims]').innerHTML = num.filter(([k]) => !d2 || MAIN_DIMS.includes(k)).map(row).join('');
      if (d2) { d2.innerHTML = num.filter(([k]) => !MAIN_DIMS.includes(k)).map(row).join(''); this.q('[data-d-dims2-wrap]').hidden = !d2.innerHTML; }
      this.markSize();
    }

    markSize() { const t = this.T[this.code]; $$(this.el, '[data-d-size]').forEach(b => { const s = t.sizes[+b.dataset.dSize]; b.classList.toggle('is-on', Object.entries(s.dims).every(([k, v]) => +this.dims[k] === +v)); }); }

    redraw() {
      const t = this.T[this.code], spec = t ? t.spec : null;
      if (!this.manual) { const f = window.TMDieline.flat(spec, this.dims); this.fw.value = f.w || ''; this.fh.value = f.h || ''; }
      const a = (parseFloat(String(this.fw.value).replace(',', '.')) || 0) * (parseFloat(String(this.fh.value).replace(',', '.')) || 0);
      this.fa.value = a ? a.toFixed(2) : '';
      window.TMDieline.render(this.q('[data-d-preview]'), spec, this.dims);
      this.decorate();
      this.annotate();
      this.q('[data-d-preview]').style.transform = `scale(${this.zoom})`; this.q('[data-d-zoomval]').textContent = Math.round(this.zoom * 100) + '%';
      this.q('[data-d-info]').innerHTML = t ? `<b>${esc(t.label)}</b>${t.category ? ` · ${esc(t.category)}` : ''} · ${Devlib.h.t('Flat blank')} <b>${this.numStr(this.fw.value)} × ${this.numStr(this.fh.value)} mm</b> · ${Devlib.h.t('Area')} <b>${this.numStr(a / 100)} cm²</b>${this.manual ? ' <span class="dl-badge dl-badge--warn">manual</span>' : ''}${this.holeOver ? ' <span class="dl-badge dl-badge--warn">hole larger than panel</span>' : ''}` : '';
      this.markSize();
      this.el.dispatchEvent(new CustomEvent('dl:dieline', {bubbles: true, detail: {type: this.code, dims: {...this.dims}, flat: {w: +this.fw.value || 0, h: +this.fh.value || 0, area: a}, manual: this.manual}}));
    }

    lengths(svg) {
      const S = window.TMDieline.STROKE, sum = {cut: 0, crease: 0, hole: 0};
      $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
        if (n.closest('.dl-dim, .dl-deco') || !n.getTotalLength) return;
        const st = (n.getAttribute('stroke') || '').toLowerCase(), L = n.getTotalLength();
        if (st === S.CUT.toLowerCase()) sum.cut += L; else if (st === S.CREASE.toLowerCase()) sum.crease += L; else if (st === S.HOLE.toLowerCase()) sum.hole += L;
      });
      const f = v => this.unit === 'cm' ? (v / 10).toLocaleString('id-ID', {maximumFractionDigits: 1}) : Math.round(v).toLocaleString('id-ID');
      this.q('[data-d-lens]').innerHTML = `<div class="is-cut"><b>${f(sum.cut)} ${this.unit}</b><span>${Devlib.h.t('Cutting line')}</span></div><div class="is-crease"><b>${f(sum.crease)} ${this.unit}</b><span>${Devlib.h.t('Creasing line')}</span></div><div class="is-hole"><b>${f(sum.hole)} ${this.unit}</b><span>${Devlib.h.t('Perforation / hole')}</span></div>`;
    }

    // Proporsi gambar menentukan cara pasang (lihat artRect); digambar ulang setelah ukuran asli diketahui.
    loadArt() {
      this.artRatio = null; this.artImg = null; this.artPix = null;
      if (!this.art) return this.redraw();
      const im = new Image(), url = this.art;
      im.onload = () => { if (this.art === url) { this.artImg = im; this.artRatio = im.naturalWidth / im.naturalHeight; this.redraw(); } };
      im.onerror = () => this.redraw();
      im.src = url;
    }

    // Artwork file cetak = satu lembar bentangan penuh (proporsi ~ bentangan dieline) -> dipasang ke seluruh tepi potong;
    // selain itu (mis. foto) -> panel utama saja.
    artRect(g) {
      const blank = (g.x1 - g.x0) / (g.y1 - g.y0);
      const full = this.artRatio && Math.abs(this.artRatio / blank - 1) <= 0.2;
      return full ? {x: g.x0, y: g.y0, w: g.x1 - g.x0, h: g.y1 - g.y0, full: true} : {x: g.px0, y: g.py0, w: g.px1 - g.px0, h: g.py1 - g.py0, full: false};
    }

    // Sumber gambar tiap sisi 3D (koordinat bentangan). Kotak tuck (panel berjajar) = panel L, W, dan tutup di atas panel L;
    // kotak model nampan (PPL, dus nasi, mailer) = panel terbesar sbg tutup, sel di bawahnya = depan, sel lebar di kanannya = samping.
    faces3d(g) {
      const cols = [g.x0, ...g.inX, g.x1], rows = [g.y0, ...g.ys, g.y1];
      const i = cols.indexOf(g.px0);
      if (g.ys.length <= 2 && g.inX.length >= 3 && i >= 0 && i + 2 < cols.length) {
        const fx = [cols[i], cols[i + 1]], sx = [cols[i + 1], cols[i + 2]], w = sx[1] - sx[0];
        return {front: [fx[0], g.py0, fx[1] - fx[0], g.py1 - g.py0], side: [sx[0], g.py0, w, g.py1 - g.py0], top: [fx[0], Math.max(g.y0, g.py0 - w), fx[1] - fx[0], Math.min(w, g.py0 - g.y0)]};
      }
      const cells = [];
      for (let r = 0; r < rows.length - 1; r++) for (let c = 0; c < cols.length - 1; c++) cells.push({r, c, a: (cols[c + 1] - cols[c]) * (rows[r + 1] - rows[r])});
      if (!cells.length) return null;
      // Tutup = panel besar (>= 60% terbesar) yang paling "ramai" gambarnya (logo/desain), bukan sekadar yang terluas.
      const maxA = Math.max(...cells.map(x => x.a)), big = cells.filter(x => x.a >= maxA * 0.6);
      const R = this.artRect(g), busy = x => this.detail(R, [cols[x.c], rows[x.r], cols[x.c + 1] - cols[x.c], rows[x.r + 1] - rows[x.r]]);
      const best = big.reduce((a, b) => (busy(b) > busy(a) ? b : a));
      const cell = (r, c) => [cols[c], rows[r], cols[c + 1] - cols[c], rows[r + 1] - rows[r]], top = cell(best.r, best.c);
      const fr = best.r + 1 < rows.length - 1 ? best.r + 1 : best.r - 1;
      let sc = best.c + 1; while (sc < cols.length - 1 && cols[sc + 1] - cols[sc] < top[2] * 0.2) sc++;
      if (sc >= cols.length - 1) sc = Math.max(0, best.c - 1);
      return {top, front: fr >= 0 ? cell(fr, best.c) : top, side: cell(best.r, sc)};
    }

    // Kerapatan detail (simpangan baku terang piksel) artwork di satu area bentangan; 0 bila gambar belum/tidak bisa dibaca.
    detail(R, src) {
      if (!this.artImg) return 0;
      if (!this.artPix) {
        const cv = document.createElement('canvas'), w = 160, h = Math.max(1, Math.round(160 / this.artRatio));
        cv.width = w; cv.height = h;
        try { const x = cv.getContext('2d'); x.drawImage(this.artImg, 0, 0, w, h); this.artPix = {w, h, d: x.getImageData(0, 0, w, h).data}; } catch (err) { this.artPix = {w: 0}; }
      }
      const P = this.artPix; if (!P.w) return 0;
      const x0 = Math.max(0, Math.floor((src[0] - R.x) / R.w * P.w)), x1 = Math.min(P.w, Math.ceil((src[0] + src[2] - R.x) / R.w * P.w));
      const y0 = Math.max(0, Math.floor((src[1] - R.y) / R.h * P.h)), y1 = Math.min(P.h, Math.ceil((src[1] + src[3] - R.y) / R.h * P.h));
      let n = 0, sum = 0, sq = 0;
      for (let y = y0; y < y1; y++) for (let x = x0; x < x1; x++) { const i = (y * P.w + x) * 4, v = P.d[i] * 0.3 + P.d[i + 1] * 0.59 + P.d[i + 2] * 0.11; n++; sum += v; sq += v * v; }
      return n ? Math.sqrt(Math.max(0, sq / n - (sum / n) ** 2)) : 0;
    }

    opt(k) { const c = this.q(`[data-d-opt="${k}"]`); return c ? c.checked : false; }

    // Tepi potong, garis lipat, kotak bleed, dan area panel utama (tanpa glue flap) dari hasil render TMDieline.
    geo(svg) {
      const S = window.TMDieline.STROKE, crease = S.CREASE.toLowerCase(), bleed = S.BLEED.toLowerCase(), cut = S.CUT.toLowerCase();
      let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity, b0 = Infinity, c0 = Infinity, b1 = -Infinity, c1 = -Infinity;
      const xs = [], hs = [], cuts = [];
      $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
        if (n.closest('.dl-deco, .dl-dim')) return;
        const st = (n.getAttribute('stroke') || '').toLowerCase(), b = n.getBBox();
        if (st === bleed) { b0 = Math.min(b0, b.x); c0 = Math.min(c0, b.y); b1 = Math.max(b1, b.x + b.width); c1 = Math.max(c1, b.y + b.height); return; }
        x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
        // Bentuk potong per flap/tutup (bukan garis lurus) -> dipakai mencari tutup atas/bawah kotak 3D.
        if (st === cut && n.tagName !== 'line') cuts.push(b);
        if (st === crease && n.tagName === 'line' && !n.getAttribute('stroke-dasharray')) {
          const a = +n.getAttribute('x1'), c = +n.getAttribute('x2'), d = +n.getAttribute('y1'), f = +n.getAttribute('y2');
          if (Math.abs(a - c) < 0.01) xs.push(a); else if (Math.abs(d - f) < 0.01) hs.push({y: d, a: Math.min(a, c), b: Math.max(a, c)});
        }
      });
      if (!isFinite(x0)) return null;
      const inX0 = [...new Set(xs.filter(v => v > x0 + 0.5 && v < x1 - 0.5).map(v => Math.round(v * 10) / 10))].sort((a, b) => a - b);
      const ys = [...new Set(hs.map(h => Math.round(h.y * 10) / 10))].filter(v => v > y0 + 0.5 && v < y1 - 0.5).sort((a, b) => a - b);
      const inX = inX0;
      // Glue flap = strip ujung yang lebih sempit (kiri/kanan) di luar lipatan vertikal pertama/terakhir.
      let px0 = x0, px1 = x1, glue = null;
      if (inX.length >= 2) {
        const left = inX[0] - x0, right = x1 - inX[inX.length - 1];
        if (left <= right) { px0 = inX[0]; glue = [x0, inX[0]]; } else { px1 = inX[inX.length - 1]; glue = [inX[inX.length - 1], x1]; }
      }
      const py0 = ys.length ? ys[0] : y0, py1 = ys.length > 1 ? ys[ys.length - 1] : y1;
      const bb = isFinite(b0) ? {x0: b0, y0: c0, x1: b1, y1: c1} : {x0, y0, x1, y1};
      return {x0, y0, x1, y1, px0, px1, py0, py1, glue, inX, ys, hs, bb, cuts};
    }

    // Lapisan tampilan di atas hasil TMDieline: artwork transparan, glue margin, arah serat, tanda register, lock tab, bleed on/off.
    decorate() {
      const svg = this.q('[data-d-preview] svg'); if (!svg) return;
      const S = window.TMDieline.STROKE, bleed = S.BLEED.toLowerCase();
      // Ukur dulu (getBBox elemen tersembunyi = 0), baru sembunyikan bleed bila Overflow mati.
      const g = this.geo(svg); if (!g) return;
      $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => { if ((n.getAttribute('stroke') || '').toLowerCase() === bleed) n.style.display = this.opt('overflow') ? '' : 'none'; });
      const W = g.x1 - g.x0, H = g.y1 - g.y0, u = Math.max(W, H) / 100, ns = 'http://www.w3.org/2000/svg';
      this.g = g;
      this.drawHole(svg, g);
      if (this.art && this.opt('artwork')) {
        const img = document.createElementNS(ns, 'image'), R = this.artRect(g);
        img.setAttribute('class', 'dl-deco dl-deco__art');
        img.setAttribute('href', this.art);
        img.setAttribute('x', R.x); img.setAttribute('y', R.y);
        img.setAttribute('width', R.w); img.setAttribute('height', R.h);
        img.setAttribute('preserveAspectRatio', 'none'); img.setAttribute('opacity', '.8');
        if (this.side === 'in' && this.opt('mirror')) img.setAttribute('transform', `translate(${2 * R.x + R.w},0) scale(-1,1)`);
        svg.insertBefore(img, svg.firstChild);
      }
      let d = `<defs><pattern id="${this.uid}h" width="${u * 2}" height="${u * 2}" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="${u * 2}" stroke="var(--dl-dl-glue)" stroke-width="${u * 0.6}"/></pattern></defs><g class="dl-deco">`;
      if (g.glue && this.opt('glue')) d += `<rect x="${g.glue[0]}" y="${g.py0}" width="${g.glue[1] - g.glue[0]}" height="${g.py1 - g.py0}" fill="url(#${this.uid}h)"/>`;
      if (this.opt('direction')) {
        const cx = (g.px0 + g.px1) / 2, cy = g.py0 + (g.py1 - g.py0) * 0.18, len = Math.min(g.px1 - g.px0, W) * 0.22, fs = u * 2.6;
        d += `<g class="dl-deco__grain"><line x1="${cx - len}" y1="${cy}" x2="${cx + len}" y2="${cy}" stroke-width="1.5"/><path d="M${cx + len},${cy} l${-u * 2.2},${-u * 1.2} v${u * 2.4} z"/><path d="M${cx - len},${cy} l${u * 2.2},${-u * 1.2} v${u * 2.4} z"/><text x="${cx}" y="${cy - fs * 0.5}" font-size="${fs}" text-anchor="middle">GRAIN</text></g>`;
      }
      if (this.opt('cross')) {
        const r = u * 2, o = u * 4;
        [[g.bb.x0 - o, g.bb.y0 - o], [g.bb.x1 + o, g.bb.y0 - o], [g.bb.x0 - o, g.bb.y1 + o], [g.bb.x1 + o, g.bb.y1 + o]].forEach(([x, y]) => {
          d += `<g class="dl-deco__cross"><circle cx="${x}" cy="${y}" r="${r}"/><line x1="${x - r * 1.8}" y1="${y}" x2="${x + r * 1.8}" y2="${y}"/><line x1="${x}" y1="${y - r * 1.8}" x2="${x}" y2="${y + r * 1.8}"/></g>`;
        });
      }
      if (this.opt('lock')) {
        // Indikatif: takik kecil di kedua ujung lipatan horizontal teratas & terbawah (engsel tuck flap).
        const t = u * 3;
        g.hs.filter(h => Math.abs(h.y - g.py0) < 0.2 || Math.abs(h.y - g.py1) < 0.2).forEach(h => {
          const dir = Math.abs(h.y - g.py0) < 0.2 ? -1 : 1;
          [[h.a, 1], [h.b, -1]].forEach(([x, s]) => { d += `<line class="dl-deco__lock" x1="${x}" y1="${h.y}" x2="${x + s * t}" y2="${h.y + dir * t}"/>`; });
        });
      }
      svg.insertAdjacentHTML('beforeend', d + '</g>');
      // Tanda register di luar bleed: perlebar tampilan bila ukuran dimatikan (annotate memakai padding sendiri).
      if (this.opt('cross')) {
        const vb = svg.viewBox.baseVal, m = u * 8, ax = Math.min(vb.x, g.bb.x0 - m), ay = Math.min(vb.y, g.bb.y0 - m);
        svg.setAttribute('viewBox', `${ax} ${ay} ${Math.max(vb.x + vb.width, g.bb.x1 + m) - ax} ${Math.max(vb.y + vb.height, g.bb.y1 + m) - ay}`);
      }
    }

    // Kotak tuck = panel berjajar (L, W, L, W) di antara lipatan atas & bawah; selain itu model nampan / lembaran.
    isTuck(g) {
      const cols = [g.x0, ...g.inX, g.x1], i = cols.indexOf(g.px0);
      return g.ys.length <= 2 && g.inX.length >= 3 && i >= 0 && i + 2 < cols.length;
    }

    // Lubang di tengah panel depan (kotak tuck) / panel utama (nampan, lembaran): Round = lingkaran Ø w, Rectangle = w x h.
    // null bila tidak dipakai / ukuran belum diisi. pw, ph = ukuran panelnya.
    holeShape(g) {
      const H = this.hole, F = H.type === 'NONE' ? null : this.faces3d(g), P = F ? (this.isTuck(g) ? F.front : F.top) : null;
      const w = +H.w || 0, h = H.type === 'ROUND' ? w : +H.h || 0;
      return P && w > 0 && h > 0 ? {round: H.type === 'ROUND', w, h, cx: P[0] + P[2] / 2, cy: P[1] + P[3] / 2, pw: P[2], ph: P[3]} : null;
    }

    // Digambar sebagai garis HOLE di luar lapisan dl-deco supaya ikut terhitung di "Perforation / hole" dan ikut ke unduhan SVG.
    drawHole(svg, g) {
      const o = this.holeShape(g), at = `fill="none" stroke="${window.TMDieline.STROKE.HOLE}" stroke-width="1"`;
      this.holeOver = !!o && (o.w > o.pw || o.h > o.ph);
      if (!o) return;
      svg.insertAdjacentHTML('beforeend', o.round ? `<circle cx="${o.cx}" cy="${o.cy}" r="${o.w / 2}" ${at}/>`
        : `<rect x="${o.cx - o.w / 2}" y="${o.cy - o.h / 2}" width="${o.w}" height="${o.h}" rx="${Math.min(3, o.w / 8, o.h / 8)}" ${at}/>`);
    }

    // Field lubang: None -> semua mati (tidak ikut tersimpan); Round -> hanya Width / Ø; Rectangle -> Width + Height.
    holeUi(fill) {
      const H = this.hole, t = this.q('[data-d-hole="type"]'), w = this.q('[data-d-hole="w"]'), h = this.q('[data-d-hole="h"]'); if (!t) return;
      t.value = H.type; t.disabled = H.type === 'NONE';
      w.disabled = H.type === 'NONE'; h.disabled = H.type !== 'RECT';
      if (fill) { w.value = H.w; h.value = H.h; }
    }

    // Sumber gambar 6 sisi kotak (koordinat bentangan). Kotak tuck = panel berjajar L, W, L, W (depan, kanan, belakang, kiri);
    // tutup atas/bawah = bentuk potong di atas/bawah panel L tempat ia menempel (menempel di panel belakang -> gambar diputar 180°).
    // Model nampan: 3 sisi dari faces3d, sisi lainnya polos.
    sides3d(g) {
      const F = this.faces3d(g); if (!F) return {};
      const out = {front: {src: F.front}, right: {src: F.side}, top: {src: F.top}};
      const cols = [g.x0, ...g.inX, g.x1], i = cols.indexOf(g.px0);
      if (!this.isTuck(g)) return out;
      const h = g.py1 - g.py0, col = n => (i + n + 1 < cols.length ? [cols[i + n], cols[i + n + 1]] : null), f = col(0), b = col(2), l = col(3);
      if (b) out.back = {src: [b[0], g.py0, b[1] - b[0], h]};
      if (l) out.left = {src: [l[0], g.py0, l[1] - l[0], h]};
      const lid = above => {
        const c = g.cuts.filter(r => (above ? r.y + r.height <= g.py0 + 0.5 : r.y >= g.py1 - 0.5) && [f, b].some(p => p && r.x >= p[0] - 0.5 && r.x + r.width <= p[1] + 0.5)).sort((p, q) => q.height - p.height)[0];
        return c ? {src: [c.x, c.y, c.width, c.height], flip: !!b && c.x >= b[0] - 0.5} : null;
      };
      out.top = lid(true) || out.top;
      const bottom = lid(false); if (bottom) out.bottom = bottom;
      return out;
    }

    // Kotak 3D yang bisa diputar: 6 sisi CSS 3D berukuran proporsional L x W x H (satuan --u mengikuti ukuran jendela),
    // gambar tiap sisi = area panelnya di bentangan. Ilustrasi kotak tertutup, bukan simulasi lipatan.
    box3d(box, L, Wd, H) {
      // 3D = tampak luar kotak; sisi dalam tidak dipasang di sini.
      const on = this.art && this.side !== 'in' && this.opt('artwork') && this.g, S = on ? this.sides3d(this.g) : {}, R = on ? this.artRect(this.g) : null;
      const u = n => `calc(${Math.round(n * 1000) / 1000} * var(--u))`, href = on ? esc(String(this.art).replace(/'/g, '%27')) : '';
      // Sumber [x,y,w,h] di bentangan -> sisi w x h: gambar (menempati R) diskalakan & digeser agar sumber jatuh di 0..w, 0..h.
      const ho = this.g ? this.holeShape(this.g) : null, hp = this.g && this.isTuck(this.g) ? 'front' : 'top';
      const face = (k, w, h, tf) => {
        const s = S[k] && S[k].src[2] > 0 && S[k].src[3] > 0 ? S[k] : null, kx = s ? w / s.src[2] : 0, ky = s ? h / s.src[3] : 0;
        const bg = s ? `background-image:url('${href}');background-size:${u(R.w * kx)} ${u(R.h * ky)};background-position:${u((R.x - s.src[0]) * kx)} ${u((R.y - s.src[1]) * ky)};` : '';
        const o = k === hp && ho ? `<i class="dl-3d__hole${ho.round ? ' is-round' : ''}" style="width:${u(ho.w * w / ho.pw)};height:${u(ho.h * h / ho.ph)}"></i>` : '';
        return `<div class="dl-3d__face is-${k}" style="width:${u(w)};height:${u(h)};margin:${u(-h / 2)} 0 0 ${u(-w / 2)};transform:${tf}${s && s.flip ? ' rotate(180deg)' : ''};${bg}">${o}</div>`;
      };
      const diag = Math.hypot(L, Wd, H), btn = (a, icon, title) => `<button type="button" class="dl-3d__btn${a === 'spin' && this.spin ? ' is-on' : ''}" data-d-3d-act="${a}" title="${title}"><i class="fa-solid ${icon}"></i></button>`;
      box.classList.add('is-live');
      box.innerHTML = `<div class="dl-3d__scene" style="--u:min(84cqw / ${Math.hypot(L, Wd)}, 76cqh / ${diag});perspective:${u(diag * 4)}"><div class="dl-3d__box">`
        + face('front', L, H, `translateZ(${u(Wd / 2)})`) + face('back', L, H, `rotateY(180deg) translateZ(${u(Wd / 2)})`)
        + face('right', Wd, H, `rotateY(90deg) translateZ(${u(L / 2)})`) + face('left', Wd, H, `rotateY(-90deg) translateZ(${u(L / 2)})`)
        + face('top', L, Wd, `rotateX(90deg) translateZ(${u(H / 2)})`) + face('bottom', L, Wd, `rotateX(-90deg) translateZ(${u(H / 2)})`)
        + `</div></div><span>3D</span><div class="dl-3d__bar">${btn('spin', 'fa-arrows-rotate', Devlib.h.t('Auto-spin'))}${btn('out', 'fa-minus', Devlib.h.t('Zoom out'))}${btn('in', 'fa-plus', Devlib.h.t('Zoom in'))}${btn('reset', 'fa-rotate-left', Devlib.h.t('Reset view'))}<b data-d-3d-deg title="${Devlib.h.t('Drag to rotate · Shift+drag to tilt · double-click to reset')}"></b></div>`;
      this.turn3d();
    }

    turn3d() {
      const b = this.q('.dl-3d__box'), r = this.rot, d = this.q('[data-d-3d-deg]'); if (!b) return;
      b.style.transform = `scale(${r.k}) rotateZ(${r.z}deg) rotateX(${r.x}deg) rotateY(${r.y}deg)`;
      const deg = v => Math.round((((v + 180) % 360) + 360) % 360 - 180);
      if (d) d.textContent = `X ${deg(r.x)}° · Y ${deg(r.y)}° · Z ${deg(r.z)}°`;
    }

    act3d(a) {
      const r = this.rot;
      if (a === 'spin') return this.spin3d(!this.spin);
      if (a === 'reset') { this.spin3d(false); this.rot = {...ROT0}; } else r.k = Math.min(1.8, Math.max(0.6, Math.round((r.k + (a === 'in' ? 0.2 : -0.2)) * 10) / 10));
      this.turn3d();
    }

    spin3d(on) {
      this.spin = on; cancelAnimationFrame(this.raf);
      const b = this.q('[data-d-3d-act="spin"]'); if (b) b.classList.toggle('is-on', on);
      const step = () => { if (!this.spin || !this.el.isConnected) return; if (!this.drag) { this.rot.y += 0.6; this.turn3d(); } this.raf = requestAnimationFrame(step); };
      if (on) step();
    }

    // Drag = orbit (sumbu Y lalu X), Shift+drag = miring (sumbu Z), klik ganda = reset.
    bind3d() {
      const d3 = this.q('[data-d-3d]'); if (!d3) return;
      d3.addEventListener('pointerdown', e => { if (e.target.closest('.dl-3d__bar') || !$(d3, '.dl-3d__box')) return; this.drag = {x: e.clientX, y: e.clientY}; try { d3.setPointerCapture(e.pointerId); } catch (err) { /* pointer sudah lepas */ } e.preventDefault(); });
      d3.addEventListener('pointermove', e => {
        const p = this.drag, r = this.rot; if (!p) return;
        const dx = e.clientX - p.x, dy = e.clientY - p.y; p.x = e.clientX; p.y = e.clientY;
        if (e.shiftKey) r.z += dx * 0.5; else { r.y += dx * 0.5; r.x = Math.max(-85, Math.min(85, r.x - dy * 0.5)); }
        this.turn3d();
      });
      const up = () => { this.drag = null; };
      d3.addEventListener('pointerup', up); d3.addEventListener('pointercancel', up);
      d3.addEventListener('dblclick', e => { if (!e.target.closest('.dl-3d__bar')) this.act3d('reset'); });
    }

    preview3d() {
      const box = this.q('[data-d-3d]'), t = this.T[this.code]; if (!box || !t) return;
      const L = +this.dims.product_length || 0, Wd = +this.dims.product_width || 0, H = +this.dims.product_height || +this.dims.lid_height || 0, D = +this.dims.diameter || 0;
      if (!D && L && Wd && H) return this.box3d(box, L, Wd, H);
      // Tabung & barang lembaran: gambar isometrik statis.
      box.classList.remove('is-live');
      const c = Math.cos(Math.PI / 6), s = 0.5, iso = (x, y, z) => [(x - y) * c, (x + y) * s - z];
      let body = '';
      if (D && H) {
        const r = D / 2, top = iso(0, 0, H), bot = iso(0, 0, 0), rx = r * 1.22, ry = r * 0.7;
        body = `<path d="M${bot[0] - rx},${top[1]} L${bot[0] - rx},${bot[1]} A${rx},${ry} 0 0 0 ${bot[0] + rx},${bot[1]} L${bot[0] + rx},${top[1]} Z" class="dl-3d__side"/><ellipse cx="${top[0]}" cy="${top[1]}" rx="${rx}" ry="${ry}" class="dl-3d__top"/>`;
      } else {
        const fwv = +this.fw.value || 100, fhv = +this.fh.value || 70, P = (x, y) => iso(x, y, 0).join(',');
        body = `<polygon points="${[P(0, 0), P(fwv, 0), P(fwv, fhv), P(0, fhv)].join(' ')}" class="dl-3d__top"/>`;
      }
      box.innerHTML = `<svg viewBox="0 0 10 10"><g>${body}</g></svg><span>3D</span>`;
      // Bingkai dari bentuk kotak saja (getBBox gambar artwork mengabaikan clip-path -> kotak jadi kecil).
      const bb = [...$$(box, 'polygon, path, ellipse')].map(n => n.getBBox()).reduce((a, b) => ({x: Math.min(a.x, b.x), y: Math.min(a.y, b.y), r: Math.max(a.r, b.x + b.width), b: Math.max(a.b, b.y + b.height)}), {x: Infinity, y: Infinity, r: -Infinity, b: -Infinity});
      bb.width = bb.r - bb.x; bb.height = bb.b - bb.y;
      const m = Math.max(bb.width, bb.height) * 0.1;
      $(box, 'svg').setAttribute('viewBox', `${bb.x - m} ${bb.y - m} ${bb.width + 2 * m} ${bb.height + 2 * m}`);
    }

    annotate() {
      const t0 = this.T[this.code], dimTxt = ['product_length', 'product_width', 'product_height'].map(k => this.dims[k]).filter(v => v !== '' && v != null);
      if (this.v3) this.q('[data-d-title]').textContent = t0 ? `${t0.label.toUpperCase()}${dimTxt.length ? ' · ' + dimTxt.join(' × ') + ' MM' : ''}${this.dims.diameter ? ' · Ø ' + this.dims.diameter + ' MM' : ''}${this.side === 'in' ? ' · ' + Devlib.h.t('INNER SIDE') : ''}` : '';
      if (this.v3) this.preview3d();
      $$(this.el, '[data-d-unit] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === this.unit));
      const svg = this.q('[data-d-preview] svg'), termsBox = this.q('[data-d-terms]');
      if (svg && this.v3) this.lengths(svg);
      if (!svg || !this.q('[data-d-dims-on]').checked) { if (termsBox) termsBox.innerHTML = ''; return; }
      const S = window.TMDieline.STROKE, crease = S.CREASE.toLowerCase(), bleed = S.BLEED.toLowerCase();
      let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
      const xs = [], ys = [];
      $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
        const st = (n.getAttribute('stroke') || '').toLowerCase();
        if (st === bleed || n.closest('.dl-deco')) return;
        const b = n.getBBox(); x0 = Math.min(x0, b.x); y0 = Math.min(y0, b.y); x1 = Math.max(x1, b.x + b.width); y1 = Math.max(y1, b.y + b.height);
        if (st === crease && n.tagName === 'line') {
          const a = +n.getAttribute('x1'), c = +n.getAttribute('x2'), d = +n.getAttribute('y1'), f = +n.getAttribute('y2');
          if (Math.abs(a - c) < 0.01 && !n.getAttribute('stroke-dasharray')) xs.push(a); else if (Math.abs(d - f) < 0.01) ys.push(d);
        }
      });
      if (!isFinite(x0)) return;
      const uniq = arr => [...arr].sort((a, b) => a - b).filter((v, i, a) => !i || v - a[i - 1] > 0.5);
      const cutX = uniq([x0, ...xs.filter(v => v > x0 + 0.5 && v < x1 - 0.5), x1]), cutY = uniq([y0, ...ys.filter(v => v > y0 + 0.5 && v < y1 - 0.5), y1]);
      const Wd = x1 - x0, Hd = y1 - y0, fs = Math.max(6, Math.min(16, Math.max(Wd, Hd) / 42)), gap = fs * 2.3;
      const params = Object.keys(this.T[this.code].params);
      // Bila beberapa parameter sama nilainya, posisi ruas menentukan (ruas bawah = Bottom Tuck, atas = Top Tuck).
      const codeOf = (len, prefer = []) => { const ok = k => this.TERMS[k] && k !== 'overflow_margin' && Math.abs((+this.dims[k] || -1) - len) < 0.06; const k = prefer.find(ok) || params.find(ok); return k ? this.TERMS[k][1] : ''; };
      const val = len => this.unit === 'cm' ? (Math.round(len / 10 * 100) / 100).toLocaleString('id-ID') : (Math.round(len * 10) / 10).toLocaleString('id-ID');
      const used = new Set();
      const hdim = (a, b, y, total) => { const c = total ? '' : codeOf(b - a); if (c) used.add(c); const t = (c ? c + ' ' : '') + val(b - a) + (total ? ' ' + this.unit : ''), tw = t.length * fs * 0.56, small = tw > b - a - 4;
        return `<line x1="${a}" y1="${y}" x2="${b}" y2="${y}" class="dl-dim__l" marker-start="url(#${this.uid}a)" marker-end="url(#${this.uid}a)"/><text x="${(a + b) / 2}" y="${y - fs * 0.45}" class="dl-dim__t ${c ? 'is-code' : ''}" font-size="${small ? fs * 0.72 : fs}" text-anchor="middle">${esc(t)}</text>`; };
      const vdim = (a, b, x, total, prefer) => { const c = total ? '' : codeOf(b - a, prefer); if (c) used.add(c); const t = (c ? c + ' ' : '') + val(b - a) + (total ? ' ' + this.unit : ''), tw = t.length * fs * 0.56, small = tw > b - a - 4, m = (a + b) / 2;
        return `<line x1="${x}" y1="${a}" x2="${x}" y2="${b}" class="dl-dim__l" marker-start="url(#${this.uid}a)" marker-end="url(#${this.uid}a)"/><text x="${x - fs * 0.45}" y="${m}" class="dl-dim__t ${c ? 'is-code' : ''}" font-size="${small ? fs * 0.72 : fs}" text-anchor="middle" transform="rotate(-90 ${x - fs * 0.45} ${m})">${esc(t)}</text>`; };
      const ext = [];
      cutX.forEach(x => ext.push(`<line x1="${x}" y1="${y0 - gap - fs * 0.6}" x2="${x}" y2="${y0 - 2}" class="dl-dim__e"/>`));
      cutY.forEach(y => ext.push(`<line x1="${x0 - gap - fs * 0.6}" y1="${y}" x2="${x0 - 2}" y2="${y}" class="dl-dim__e"/>`));
      ext.push(`<line x1="${x0}" y1="${y1 + 2}" x2="${x0}" y2="${y1 + gap + fs * 0.6}" class="dl-dim__e"/><line x1="${x1}" y1="${y1 + 2}" x2="${x1}" y2="${y1 + gap + fs * 0.6}" class="dl-dim__e"/>`);
      ext.push(`<line x1="${x1 + 2}" y1="${y0}" x2="${x1 + gap + fs * 0.6}" y2="${y0}" class="dl-dim__e"/><line x1="${x1 + 2}" y1="${y1}" x2="${x1 + gap + fs * 0.6}" y2="${y1}" class="dl-dim__e"/>`);
      let g = `<defs><marker id="${this.uid}a" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="${fs * 0.55}" markerHeight="${fs * 0.55}" markerUnits="userSpaceOnUse" orient="auto-start-reverse"><path d="M0,1 L10,5 L0,9 z" class="dl-dim__a"/></marker></defs><g class="dl-dim">${ext.join('')}`;
      if (cutX.length > 2) for (let i = 0; i < cutX.length - 1; i++) g += hdim(cutX[i], cutX[i + 1], y0 - gap);
      if (cutY.length > 2) for (let i = 0; i < cutY.length - 1; i++) g += vdim(cutY[i], cutY[i + 1], x0 - gap, false, i === 0 ? ['top_tuck_flap', 'lid_height'] : i === cutY.length - 2 ? ['bottom_tuck_flap'] : ['product_height']);
      g += hdim(x0, x1, y1 + gap, true) + vdim(y0, y1, x1 + gap + fs * 1.2, true) + '</g>';
      svg.insertAdjacentHTML('beforeend', g);
      const pad = gap + fs * 2.2;
      svg.setAttribute('viewBox', `${x0 - pad} ${y0 - pad} ${Wd + pad * 2 + fs} ${Hd + pad * 2}`);
      if (termsBox) termsBox.innerHTML = `<b>${Devlib.h.t('Unit')}: ${this.unit}</b>` + Object.entries(this.TERMS).filter(([k, t]) => used.has(t[1]) || k === 'overflow_margin' && this.dims[k]).map(([k, t]) => `<span><i>${esc(t[1])}</i>${esc(t[0])}</span>`).join('') + '<span class="is-note">' + Devlib.h.t('Total = cut edge (without bleed); the Flat blank below = including bleed. Sizes without a code = derived panels (e.g. lock tabs).') + '</span>';
    }
  }
  Devlib.define("DielineX", DielineX);
})(window.Devlib);
