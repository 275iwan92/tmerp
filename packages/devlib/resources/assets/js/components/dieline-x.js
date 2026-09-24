/* DevLib · DielineX */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, T, val} = Devlib.h;
  /* ===================== DIELINE v2 / v3 (salinan v1 + ukuran) ===================== */
  // v1 (initDieline) SENGAJA tidak diubah. level 2 = garis ukuran + istilah internasional + mm/cm;
  // level 3 = v2 + judul spesifikasi + panjang cutting/creasing/perforasi + jendela 3D statis.
  // Hanya "lem" UI; semua geometri dihitung TMDieline (salinan utuh dari tmerp).
  class DielineX extends Devlib.Component {
    static selector = "[data-dl-dieline-x]";
    init() {
      const el = this.el;
      const T = JSON.parse(el.dataset.types || '{}'), name = el.dataset.name, TERMS = JSON.parse(el.dataset.terms || '{}'), uid = 'dld' + Math.random().toString(36).slice(2, 8), v3 = el.dataset.level === '3';
      let code = el.dataset.type, dims = JSON.parse(el.dataset.dims || '{}'), zoom = 1, manual = false, unit = el.dataset.unit || 'mm';
      const term = (k, p) => TERMS[k] ? `${TERMS[k][0]} (${TERMS[k][1]})` : p.label;
      const q = s => $(el, s), fw = q('[data-d-fw]'), fh = q('[data-d-fh]'), fa = q('[data-d-fa]');
      const numStr = v => v === '' || v == null || isNaN(v) ? '' : (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
      function fields() {
        const t = T[code]; if (!t) return;
        dims = Object.fromEntries(Object.entries(t.params).map(([k, p]) => [k, dims[k] ?? p.default ?? '']));
        q('[data-d-sizes]').innerHTML = (t.sizes || []).map((s, i) => `<button type="button" class="dl-dieline__size" data-d-size="${i}">${esc(s.label)}</button>`).join('') || '<span class="dl-dieline__none">Belum ada ukuran pasaran untuk tipe ini.</span>';
        q('[data-d-dims]').innerHTML = Object.entries(t.params).filter(([, p]) => p.type === 'NUM' || typeof p.default === 'number').map(([k, p]) => `<label class="dl-dieline__dim" title="${esc(p.label)}"><span>${esc(term(k, p))}</span><span class="dl-field__box"><input class="dl-input" type="text" inputmode="decimal" name="${esc(name)}[dims][${esc(k)}]" data-d-dim="${esc(k)}" value="${esc(dims[k] ?? '')}"><em>${esc(p.unit || 'mm')}</em></span></label>`).join('');
        markSize();
      }
      const markSize = () => { const t = T[code]; $$(el, '[data-d-size]').forEach(b => { const s = t.sizes[+b.dataset.dSize]; b.classList.toggle('is-on', Object.entries(s.dims).every(([k, v]) => +dims[k] === +v)); }); };
      function redraw() {
        const t = T[code], spec = t ? t.spec : null;
        if (!manual) { const f = window.TMDieline.flat(spec, dims); fw.value = f.w || ''; fh.value = f.h || ''; }
        const a = (parseFloat(String(fw.value).replace(',', '.')) || 0) * (parseFloat(String(fh.value).replace(',', '.')) || 0);
        fa.value = a ? a.toFixed(2) : '';
        window.TMDieline.render(q('[data-d-preview]'), spec, dims);
        annotate();
        q('[data-d-preview]').style.transform = `scale(${zoom})`; q('[data-d-zoomval]').textContent = Math.round(zoom * 100) + '%';
        q('[data-d-info]').innerHTML = t ? `<b>${esc(t.label)}</b>${t.category ? ` · ${esc(t.category)}` : ''} · Bentang <b>${numStr(fw.value)} × ${numStr(fh.value)} mm</b> · Luas <b>${numStr(a / 100)} cm²</b>${manual ? ' <span class="dl-badge dl-badge--warn">manual</span>' : ''}` : '';
        markSize();
        el.dispatchEvent(new CustomEvent('dl:dieline', {bubbles: true, detail: {type: code, dims: {...dims}, flat: {w: +fw.value || 0, h: +fh.value || 0, area: a}, manual}}));
      }

      // Lapisan garis ukuran (seperti gambar teknik): TMDieline TIDAK diubah; ukuran dibaca dari hasil render.
      // Batas panel = garis lipat (CREASE) tegak/mendatar + tepi luar bentuk (tanpa bleed). Tiap ruas dicocokkan
      // dengan parameter tipe -> kode internasional (L, W, H, GF, TF, DF, BF...).
      // Panjang total garis per jenis (untuk meter lari pisau): cutting, creasing, perforasi/lubang.
      function lengths(svg) {
        const S = window.TMDieline.STROKE, sum = {cut: 0, crease: 0, hole: 0};
        $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
          if (n.closest('.dl-dim') || !n.getTotalLength) return;
          const st = (n.getAttribute('stroke') || '').toLowerCase(), L = n.getTotalLength();
          if (st === S.CUT.toLowerCase()) sum.cut += L; else if (st === S.CREASE.toLowerCase()) sum.crease += L; else if (st === S.HOLE.toLowerCase()) sum.hole += L;
        });
        const f = v => unit === 'cm' ? (v / 10).toLocaleString('id-ID', {maximumFractionDigits: 1}) : Math.round(v).toLocaleString('id-ID');
        q('[data-d-lens]').innerHTML = `<div class="is-cut"><b>${f(sum.cut)} ${unit}</b><span>Cutting line</span></div><div class="is-crease"><b>${f(sum.crease)} ${unit}</b><span>Creasing line</span></div><div class="is-hole"><b>${f(sum.hole)} ${unit}</b><span>Perforation / hole</span></div>`;
      }
      // Ilustrasi 3D statis isometrik dari ukuran: kotak (L x W x H), silinder (diameter), atau lembaran datar.
      function preview3d() {
        const box = q('[data-d-3d]'), t = T[code]; if (!box || !t) return;
        const L = +dims.product_length || 0, Wd = +dims.product_width || 0, H = +dims.product_height || +dims.lid_height || 0, D = +dims.diameter || 0;
        const c = Math.cos(Math.PI / 6), s = 0.5, iso = (x, y, z) => [(x - y) * c, (x + y) * s - z];
        let body = '';
        if (D && H) {
          const r = D / 2, top = iso(0, 0, H), bot = iso(0, 0, 0), rx = r * 1.22, ry = r * 0.7;
          body = `<path d="M${bot[0] - rx},${top[1]} L${bot[0] - rx},${bot[1]} A${rx},${ry} 0 0 0 ${bot[0] + rx},${bot[1]} L${bot[0] + rx},${top[1]} Z" class="dl-3d__side"/><ellipse cx="${top[0]}" cy="${top[1]}" rx="${rx}" ry="${ry}" class="dl-3d__top"/>`;
        } else if (L && Wd && H) {
          const P = (x, y, z) => iso(x, y, z).join(','), poly = (pts, cls) => `<polygon points="${pts.join(' ')}" class="${cls}"/>`;
          body = poly([P(0, Wd, 0), P(L, Wd, 0), P(L, Wd, H), P(0, Wd, H)], 'dl-3d__front') + poly([P(L, 0, 0), P(L, Wd, 0), P(L, Wd, H), P(L, 0, H)], 'dl-3d__side') + poly([P(0, 0, H), P(L, 0, H), P(L, Wd, H), P(0, Wd, H)], 'dl-3d__top')
            + `<line x1="${iso(L, Wd, 0)[0]}" y1="${iso(L, Wd, 0)[1]}" x2="${iso(L, Wd, H)[0]}" y2="${iso(L, Wd, H)[1]}" class="dl-3d__edge"/>`;
        } else {
          const fwv = +fw.value || 100, fhv = +fh.value || 70, P = (x, y) => iso(x, y, 0).join(',');
          body = `<polygon points="${[P(0, 0), P(fwv, 0), P(fwv, fhv), P(0, fhv)].join(' ')}" class="dl-3d__top"/>`;
        }
        box.innerHTML = `<svg viewBox="0 0 10 10"><g>${body}</g></svg><span>3D</span>`;
        const g = $(box, 'g'), bb = g.getBBox(), m = Math.max(bb.width, bb.height) * 0.1;
        $(box, 'svg').setAttribute('viewBox', `${bb.x - m} ${bb.y - m} ${bb.width + 2 * m} ${bb.height + 2 * m}`);
      }
      function annotate() {
        const t0 = T[code], dimTxt = ['product_length', 'product_width', 'product_height'].map(k => dims[k]).filter(v => v !== '' && v != null);
        if (v3) q('[data-d-title]').textContent = t0 ? `${t0.label.toUpperCase()}${dimTxt.length ? ' · ' + dimTxt.join(' × ') + ' MM' : ''}${dims.diameter ? ' · Ø ' + dims.diameter + ' MM' : ''}` : '';
        if (v3) preview3d();
        $$(el, '[data-d-unit] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === unit));
        const svg = q('[data-d-preview] svg'), termsBox = q('[data-d-terms]');
        if (svg && v3) lengths(svg);
        if (!svg || !q('[data-d-dims-on]').checked) { if (termsBox) termsBox.innerHTML = ''; return; }
        const S = window.TMDieline.STROKE, crease = S.CREASE.toLowerCase(), bleed = S.BLEED.toLowerCase();
        let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        const xs = [], ys = [];
        $$(svg, 'line,rect,path,circle,polyline,polygon').forEach(n => {
          const st = (n.getAttribute('stroke') || '').toLowerCase();
          if (st === bleed) return;
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
        const params = Object.keys(T[code].params);
        // Bila beberapa parameter sama nilainya, posisi ruas menentukan (ruas bawah = Bottom Tuck, atas = Top Tuck).
        const codeOf = (len, prefer = []) => { const ok = k => TERMS[k] && k !== 'overflow_margin' && Math.abs((+dims[k] || -1) - len) < 0.06; const k = prefer.find(ok) || params.find(ok); return k ? TERMS[k][1] : ''; };
        const val = len => unit === 'cm' ? (Math.round(len / 10 * 100) / 100).toLocaleString('id-ID') : (Math.round(len * 10) / 10).toLocaleString('id-ID');
        const used = new Set();
        const hdim = (a, b, y, total) => { const c = total ? '' : codeOf(b - a); if (c) used.add(c); const t = (c ? c + ' ' : '') + val(b - a) + (total ? ' ' + unit : ''), tw = t.length * fs * 0.56, small = tw > b - a - 4;
          return `<line x1="${a}" y1="${y}" x2="${b}" y2="${y}" class="dl-dim__l" marker-start="url(#${uid}a)" marker-end="url(#${uid}a)"/><text x="${(a + b) / 2}" y="${y - fs * 0.45}" class="dl-dim__t ${c ? 'is-code' : ''}" font-size="${small ? fs * 0.72 : fs}" text-anchor="middle">${esc(t)}</text>`; };
        const vdim = (a, b, x, total, prefer) => { const c = total ? '' : codeOf(b - a, prefer); if (c) used.add(c); const t = (c ? c + ' ' : '') + val(b - a) + (total ? ' ' + unit : ''), tw = t.length * fs * 0.56, small = tw > b - a - 4, m = (a + b) / 2;
          return `<line x1="${x}" y1="${a}" x2="${x}" y2="${b}" class="dl-dim__l" marker-start="url(#${uid}a)" marker-end="url(#${uid}a)"/><text x="${x - fs * 0.45}" y="${m}" class="dl-dim__t ${c ? 'is-code' : ''}" font-size="${small ? fs * 0.72 : fs}" text-anchor="middle" transform="rotate(-90 ${x - fs * 0.45} ${m})">${esc(t)}</text>`; };
        const ext = [];
        cutX.forEach(x => ext.push(`<line x1="${x}" y1="${y0 - gap - fs * 0.6}" x2="${x}" y2="${y0 - 2}" class="dl-dim__e"/>`));
        cutY.forEach(y => ext.push(`<line x1="${x0 - gap - fs * 0.6}" y1="${y}" x2="${x0 - 2}" y2="${y}" class="dl-dim__e"/>`));
        ext.push(`<line x1="${x0}" y1="${y1 + 2}" x2="${x0}" y2="${y1 + gap + fs * 0.6}" class="dl-dim__e"/><line x1="${x1}" y1="${y1 + 2}" x2="${x1}" y2="${y1 + gap + fs * 0.6}" class="dl-dim__e"/>`);
        ext.push(`<line x1="${x1 + 2}" y1="${y0}" x2="${x1 + gap + fs * 0.6}" y2="${y0}" class="dl-dim__e"/><line x1="${x1 + 2}" y1="${y1}" x2="${x1 + gap + fs * 0.6}" y2="${y1}" class="dl-dim__e"/>`);
        let g = `<defs><marker id="${uid}a" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="${fs * 0.55}" markerHeight="${fs * 0.55}" markerUnits="userSpaceOnUse" orient="auto-start-reverse"><path d="M0,1 L10,5 L0,9 z" class="dl-dim__a"/></marker></defs><g class="dl-dim">${ext.join('')}`;
        if (cutX.length > 2) for (let i = 0; i < cutX.length - 1; i++) g += hdim(cutX[i], cutX[i + 1], y0 - gap);
        if (cutY.length > 2) for (let i = 0; i < cutY.length - 1; i++) g += vdim(cutY[i], cutY[i + 1], x0 - gap, false, i === 0 ? ['top_tuck_flap', 'lid_height'] : i === cutY.length - 2 ? ['bottom_tuck_flap'] : ['product_height']);
        g += hdim(x0, x1, y1 + gap, true) + vdim(y0, y1, x1 + gap + fs * 1.2, true) + '</g>';
        svg.insertAdjacentHTML('beforeend', g);
        const pad = gap + fs * 2.2;
        svg.setAttribute('viewBox', `${x0 - pad} ${y0 - pad} ${Wd + pad * 2 + fs} ${Hd + pad * 2}`);
        if (termsBox) termsBox.innerHTML = `<b>Satuan: ${unit}</b>` + Object.entries(TERMS).filter(([k, t]) => used.has(t[1]) || k === 'overflow_margin' && dims[k]).map(([k, t]) => `<span><i>${esc(t[1])}</i>${esc(t[0])}</span>`).join('') + '<span class="is-note">Total = tepi potong (tanpa bleed); Bentang di bawah = termasuk bleed. Ukuran tanpa kode = panel turunan (mis. sirip kunci).</span>';
      }
      el.addEventListener('input', e => {
        const d = e.target.dataset.dDim;
        if (d !== undefined) { dims[d] = e.target.value === '' ? '' : parseFloat(e.target.value.replace(',', '.')); return redraw(); }
        if (e.target.matches('[data-d-fw],[data-d-fh]') && manual) redraw();
      });
      el.addEventListener('change', e => {
        if (e.target.name === name + '[type]') { code = e.target.value; dims = {}; fields(); redraw(); }
        if (e.target.matches('[data-d-manual]')) { manual = e.target.checked; fw.readOnly = fh.readOnly = !manual; redraw(); }
        if (e.target.matches('[data-d-dims-on]')) redraw();
      });
      el.addEventListener('click', e => {
        const s = e.target.closest('[data-d-size]');
        if (s) { Object.assign(dims, T[code].sizes[+s.dataset.dSize].dims); $$(el, '[data-d-dim]').forEach(i => { i.value = dims[i.dataset.dDim] ?? ''; }); return redraw(); }
        const u = e.target.closest('[data-d-unit] .dl-segment__opt'); if (u) { unit = u.dataset.value; return redraw(); }
        const z = e.target.closest('[data-d-zoom]'); if (z) { zoom = Math.min(3, Math.max(0.4, Math.round((zoom + +z.dataset.dZoom * 0.2) * 10) / 10)); return redraw(); }
        if (e.target.closest('[data-d-svg]')) {
          const svgEl = q('[data-d-preview] svg'); if (!svgEl) return toast('Isi dimensi dulu.', 'warn');
          const out = svgEl.cloneNode(true); out.setAttribute('style', 'background:#1b1f2a'); out.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
          const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([out.outerHTML], {type: 'image/svg+xml'})); a.download = `dieline-${code.toLowerCase()}.svg`; a.click();
        }
      });
      el.dlDielineX = {get dims() { return {...dims}; }, setType: c => { code = c; dims = {}; fields(); redraw(); }, redraw};
      if (!window.TMDieline) { q('[data-d-preview]').textContent = 'TMDieline tidak termuat.'; return; }
      fields(); redraw();
    }
  }
  Devlib.define("DielineX", DielineX);
})(window.Devlib);
