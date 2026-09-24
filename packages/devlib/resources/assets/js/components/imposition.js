/* DevLib · Imposition */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ IMPOSITION ============================ */
  // Penampil hasil ImpositionEngine: TIDAK menghitung UP sendiri. Sel digambar dengan dieline asli (TMDieline).
  class Imposition extends Devlib.Component {
    static selector = "[data-dl-impo]";
    init() {
      const el = this.el;
      const jobs = JSON.parse(el.dataset.jobs || '[]'), planos = JSON.parse(el.dataset.planos || '[]'), G = +el.dataset.gripper, M = +el.dataset.margin;
      let job = jobs[0], plano = planos[0], zoom = 1, sel = null, calc = 'pcs';
      const xf = {};  // transform tampilan per sel: {rot180, mx, my}
      const paper = $(el, '[data-i2-paper]');
      const n0 = v => (+v || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
      const res = () => job.results[plano.code];
      // dieline 1 UP (viewBox dipotong ke bentang flat supaya pas 1:1 dengan sel)
      function unitSvg(j) {
        const s = window.TMDieline && window.TMDieline.svg(j.spec, j.dims); if (!s) return '';
        const P = +j.dims.overflow_margin || 0;
        return s.replace(/viewBox="[^"]*"/, `viewBox="${-P} ${-P} ${j.flat.w} ${j.flat.h}" preserveAspectRatio="none"`).replace(/width="100%" height="100%"/, `width="${j.flat.w}" height="${j.flat.h}"`);
      }
      function draw() {
        const r = res(), W = plano.w, H = plano.h, unit = unitSvg(job), nums = $(el, '[data-i2-numbers]').checked;
        const cells = r.cells.map(c => {
          const t = xf[c.n] || {}, inner = c.rot ? `translate(${c.x + c.w},${c.y}) rotate(90)` : `translate(${c.x},${c.y})`;
          const fw = job.flat.w, fh = job.flat.h, extra = [t.rot ? `rotate(180 ${fw / 2} ${fh / 2})` : '', t.mx ? `translate(${fw} 0) scale(-1 1)` : '', t.my ? `translate(0 ${fh}) scale(1 -1)` : ''].join(' ');
          return `<g class="dl-impo__cell ${sel === c.n ? 'is-sel' : ''}" data-i2-cell="${c.n}"><rect x="${c.x}" y="${c.y}" width="${c.w}" height="${c.h}" class="dl-impo__cellbg"/><g transform="${inner}"><g transform="${extra}">${unit}</g></g>${nums ? `<text x="${c.x + c.w / 2}" y="${c.y + c.h / 2}" class="dl-impo__num">${c.n}${c.rot ? '↻' : ''}</text>` : ''}</g>`;
        }).join('');
        const tick = [];
        for (let x = 0; x <= W; x += 50) tick.push(`<line x1="${x}" y1="0" x2="${x}" y2="${x % 100 ? -6 : -12}" class="dl-impo__tk"/>${x % 100 === 0 ? `<text x="${x}" y="-16" class="dl-impo__tt">${x / 10}</text>` : ''}`);
        for (let y = 0; y <= H; y += 50) tick.push(`<line x1="0" y1="${y}" x2="${y % 100 ? -6 : -12}" y2="${y}" class="dl-impo__tk"/>${y % 100 === 0 ? `<text x="-16" y="${y + 4}" class="dl-impo__tt" text-anchor="end">${y / 10}</text>` : ''}`);
        paper.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-44 -34 ${W + 60} ${H + 50}" style="width:${zoom * 100}%">
          <defs><pattern id="dl-impo-hatch" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="8" class="dl-impo__hatch"/></pattern></defs>
          ${tick.join('')}<rect x="0" y="0" width="${W}" height="${H}" class="dl-impo__sheet"/>
          <rect x="0" y="0" width="${W}" height="${G}" fill="url(#dl-impo-hatch)" class="dl-impo__grip"/><text x="${W / 2}" y="${G - 2}" class="dl-impo__gt">GRIPPER ${G} mm</text>
          <rect x="${M}" y="${G + M}" width="${W - 2 * M}" height="${H - G - 2 * M}" class="dl-impo__area"/>
          ${cells}</svg>`;
        $(el, '[data-i2-zoomval]').textContent = Math.round(zoom * 100) + '%';
        $(el, '[data-i2-sel]').textContent = sel ? `UP #${sel} terpilih` : 'Klik objek untuk memilih';
      }
      function panels() {
        $(el, '[data-i2-jobs]').innerHTML = jobs.map(j => `<button type="button" class="dl-impo__job ${j === job ? 'is-on' : ''}" data-i2-job="${esc(j.code)}"><b>${esc(j.label)}</b><small>${esc(j.code)} · bentang ${n0(j.flat.w / 10)} × ${n0(j.flat.h / 10)} cm</small></button>`).join('');
        $(el, '[data-i2-planos]').innerHTML = planos.map(p => `<button type="button" class="dl-segment__opt ${p === plano ? 'is-active' : ''}" data-i2-plano="${esc(p.code)}">${esc(p.code)}<small>${job.results[p.code].up} up</small></button>`).join('');
        const best = planos.reduce((a, p) => job.results[p.code].efficiency > job.results[a.code].efficiency ? p : a, planos[0]);
        $(el, '[data-i2-kv]').innerHTML = `<dt>Plano</dt><dd>${n0(plano.w / 10)} × ${n0(plano.h / 10)} cm</dd><dt>Bentang (flat)</dt><dd>${n0(job.flat.w / 10)} × ${n0(job.flat.h / 10)} cm</dd><dt>Area blank</dt><dd>${n0(job.flat.w * job.flat.h / 100)} cm²</dd><dt>Gap / Gripper / Margin</dt><dd>${el.dataset.gap || 3} / ${G} / ${M} mm</dd><dt>Plano paling efisien</dt><dd><b>${esc(best.code)}</b> (${n0(job.results[best.code].efficiency)}%)</dd>`;
        const r = res();
        $(el, '[data-i2-hero]').innerHTML = `<div><small>UP / plano</small><b>${r.up}</b></div><div><small>Metode</small><b>${esc(r.method)}</b></div><div><small>Efisiensi</small><b class="is-pos">${n0(r.efficiency)}%</b></div><div><small>Waste</small><b class="is-neg">${n0(r.waste)}%</b></div>`;
        $(el, '[data-i2-bar]').innerHTML = `<span style="width:${r.efficiency}%">Terpakai ${n0(r.efficiency)}%</span><em>terbuang ${n0(r.waste)}%</em>`;
        calcOut();
      }
      function calcOut() {
        const r = res(), q = +String($(el, '[data-i2-qty]').value).replace(/\D/g, '') || 0, sp = parseFloat(String($(el, '[data-i2-spoil]').value).replace(',', '.')) || 0;
        $(el, '[data-i2-inlabel]').textContent = calc === 'pcs' ? 'Qty order (pcs)' : 'Jumlah plano (lembar)';
        let html;
        if (calc === 'pcs') { const net = r.up ? Math.ceil(q / r.up) : 0, extra = Math.ceil(net * sp / 100); html = `<div class="dl-impo__big"><small>Plano dibutuhkan</small><b>${n0(net + extra)}</b><span>lembar</span></div><p>${n0(net)} bersih + ${n0(extra)} inschiet (${n0(sp)}%) · sisa hasil ${n0((net) * r.up - q)} pcs</p>`; }
        else { const net = Math.floor(q / (1 + sp / 100)); html = `<div class="dl-impo__big"><small>Hasil produk</small><b>${n0(net * r.up)}</b><span>pcs</span></div><p>${n0(q)} plano − inschiet ${n0(sp)}% = ${n0(net)} plano efektif × ${r.up} up</p>`; }
        $(el, '[data-i2-result]').innerHTML = html;
      }
      el.addEventListener('click', e => {
        const x = e.target;
        const j = x.closest('[data-i2-job]'); if (j) { job = jobs.find(v => v.code === j.dataset.i2Job); sel = null; Object.keys(xf).forEach(k => delete xf[k]); panels(); return draw(); }
        const p = x.closest('[data-i2-plano]'); if (p) { plano = planos.find(v => v.code === p.dataset.i2Plano); sel = null; panels(); return draw(); }
        const c = x.closest('[data-i2-cell]'); if (c) { sel = sel === +c.dataset.i2Cell ? null : +c.dataset.i2Cell; return draw(); }
        const z = x.closest('[data-i2-zoom]'); if (z) { zoom = Math.min(3, Math.max(0.4, Math.round((zoom + +z.dataset.i2Zoom * 0.2) * 10) / 10)); return draw(); }
        const tf = x.closest('[data-i2-rot],[data-i2-mx],[data-i2-my]');
        if (tf) { const k = tf.hasAttribute('data-i2-rot') ? 'rot' : tf.hasAttribute('data-i2-mx') ? 'mx' : 'my'; const targets = sel ? [sel] : res().cells.map(c => c.n); targets.forEach(n => { xf[n] = xf[n] || {}; xf[n][k] = !xf[n][k]; }); toast(`${{rot: 'Putar 180°', mx: 'Mirror X', my: 'Mirror Y'}[k]} → ${sel ? 'UP #' + sel : 'semua UP'} (tampilan)`); return draw(); }
        const cm = x.closest('[data-i2-calc] .dl-segment__opt'); if (cm) { calc = cm.dataset.value; $$(el, '[data-i2-calc] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b === cm)); return calcOut(); }
        if (x.closest('[data-i2-svg]')) { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([$(paper, 'svg').outerHTML], {type: 'image/svg+xml'})); a.download = `imposition-${job.code}-${plano.code}.svg`; a.click(); }
      });
      el.addEventListener('input', e => { if (e.target.matches('[data-i2-qty],[data-i2-spoil]')) calcOut(); });
      el.addEventListener('change', e => { if (e.target.matches('[data-i2-numbers]')) draw(); });
      el.dlImposition = {select: (j, p) => { job = jobs.find(v => v.code === j) || job; plano = planos.find(v => v.code === p) || plano; panels(); draw(); }};
      panels(); draw();
    }
  }
  Devlib.define("Imposition", Imposition);
})(window.Devlib);
