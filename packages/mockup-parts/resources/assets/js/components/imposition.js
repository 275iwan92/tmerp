/* DevLib · Imposition */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ IMPOSITION ============================ */
  // Penampil hasil ImpositionEngine: TIDAK menghitung UP sendiri. Sel digambar dengan dieline asli (TMDieline).
  class Imposition extends Devlib.Component {
    static selector = "[data-dl-impo]";
    init() {
      this.jobs = JSON.parse(this.el.dataset.jobs || '[]');
      this.planos = JSON.parse(this.el.dataset.planos || '[]');
      this.G = +this.el.dataset.gripper;
      this.M = +this.el.dataset.margin;
      this.job = this.jobs[0];
      this.plano = this.planos[0];
      this.zoom = 1;
      this.sel = null;
      this.calc = 'pcs';
      this.xf = {};
      this.paper = $(this.el, '[data-i2-paper]');
      this.el.addEventListener('click', e => {
        const x = e.target;
        const j = x.closest('[data-i2-job]'); if (j) { this.job = this.jobs.find(v => v.code === j.dataset.i2Job); this.sel = null; Object.keys(this.xf).forEach(k => delete this.xf[k]); this.panels(); return this.draw(); }
        const p = x.closest('[data-i2-plano]'); if (p) { this.plano = this.planos.find(v => v.code === p.dataset.i2Plano); this.sel = null; this.panels(); return this.draw(); }
        const c = x.closest('[data-i2-cell]'); if (c) { this.sel = this.sel === +c.dataset.i2Cell ? null : +c.dataset.i2Cell; return this.draw(); }
        const z = x.closest('[data-i2-zoom]'); if (z) { this.zoom = Math.min(3, Math.max(0.4, Math.round((this.zoom + +z.dataset.i2Zoom * 0.2) * 10) / 10)); return this.draw(); }
        const tf = x.closest('[data-i2-rot],[data-i2-mx],[data-i2-my]');
        if (tf) { const k = tf.hasAttribute('data-i2-rot') ? 'rot' : tf.hasAttribute('data-i2-mx') ? 'mx' : 'my'; const targets = this.sel ? [this.sel] : this.res().cells.map(c => c.n); targets.forEach(n => { this.xf[n] = this.xf[n] || {}; this.xf[n][k] = !this.xf[n][k]; }); toast(`${{rot: Devlib.h.t('Rotate 180°'), mx: 'Mirror X', my: 'Mirror Y'}[k]} → ${this.sel ? 'UP #' + this.sel : Devlib.h.t('all UPs')} (${Devlib.h.t('display only')})`); return this.draw(); }
        const cm = x.closest('[data-i2-calc] .dl-segment__opt'); if (cm) { this.calc = cm.dataset.value; $$(this.el, '[data-i2-calc] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b === cm)); return this.calcOut(); }
        if (x.closest('[data-i2-svg]')) { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([$(this.paper, 'svg').outerHTML], {type: 'image/svg+xml'})); a.download = `imposition-${this.job.code}-${this.plano.code}.svg`; a.click(); }
      });
      this.el.addEventListener('input', e => { if (e.target.matches('[data-i2-qty],[data-i2-spoil]')) this.calcOut(); });
      this.el.addEventListener('change', e => { if (e.target.matches('[data-i2-numbers]')) this.draw(); });
      this.el.dlImposition = {select: (j, p) => { this.job = this.jobs.find(v => v.code === j) || this.job; this.plano = this.planos.find(v => v.code === p) || this.plano; this.panels(); this.draw(); }};
      this.panels();this.draw();
    }

    n0(v) {
      return (+v || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
    }

    res() {
      return this.job.results[this.plano.code];
    }

    unitSvg(j) {
      const s = window.TMDieline && window.TMDieline.svg(j.spec, j.dims); if (!s) return '';
      const P = +j.dims.overflow_margin || 0;
      return s.replace(/viewBox="[^"]*"/, `viewBox="${-P} ${-P} ${j.flat.w} ${j.flat.h}" preserveAspectRatio="none"`).replace(/width="100%" height="100%"/, `width="${j.flat.w}" height="${j.flat.h}"`);
    }

    draw() {
      const r = this.res(), W = this.plano.w, H = this.plano.h, unit = this.unitSvg(this.job), nums = $(this.el, '[data-i2-numbers]').checked;
      const cells = r.cells.map(c => {
        const t = this.xf[c.n] || {}, inner = c.rot ? `translate(${c.x + c.w},${c.y}) rotate(90)` : `translate(${c.x},${c.y})`;
        const fw = this.job.flat.w, fh = this.job.flat.h, extra = [t.rot ? `rotate(180 ${fw / 2} ${fh / 2})` : '', t.mx ? `translate(${fw} 0) scale(-1 1)` : '', t.my ? `translate(0 ${fh}) scale(1 -1)` : ''].join(' ');
        return `<g class="dl-impo__cell ${this.sel === c.n ? 'is-sel' : ''}" data-i2-cell="${c.n}"><rect x="${c.x}" y="${c.y}" width="${c.w}" height="${c.h}" class="dl-impo__cellbg"/><g transform="${inner}"><g transform="${extra}">${unit}</g></g>${nums ? `<text x="${c.x + c.w / 2}" y="${c.y + c.h / 2}" class="dl-impo__num">${c.n}${c.rot ? '↻' : ''}</text>` : ''}</g>`;
      }).join('');
      const tick = [];
      for (let x = 0; x <= W; x += 50) tick.push(`<line x1="${x}" y1="0" x2="${x}" y2="${x % 100 ? -6 : -12}" class="dl-impo__tk"/>${x % 100 === 0 ? `<text x="${x}" y="-16" class="dl-impo__tt">${x / 10}</text>` : ''}`);
      for (let y = 0; y <= H; y += 50) tick.push(`<line x1="0" y1="${y}" x2="${y % 100 ? -6 : -12}" y2="${y}" class="dl-impo__tk"/>${y % 100 === 0 ? `<text x="-16" y="${y + 4}" class="dl-impo__tt" text-anchor="end">${y / 10}</text>` : ''}`);
      this.paper.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-44 -34 ${W + 60} ${H + 50}" style="width:${this.zoom * 100}%">
        <defs><pattern id="dl-impo-hatch" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="8" class="dl-impo__hatch"/></pattern></defs>
        ${tick.join('')}<rect x="0" y="0" width="${W}" height="${H}" class="dl-impo__sheet"/>
        <rect x="0" y="0" width="${W}" height="${this.G}" fill="url(#dl-impo-hatch)" class="dl-impo__grip"/><text x="${W / 2}" y="${this.G - 2}" class="dl-impo__gt">GRIPPER ${this.G} mm</text>
        <rect x="${this.M}" y="${this.G + this.M}" width="${W - 2 * this.M}" height="${H - this.G - 2 * this.M}" class="dl-impo__area"/>
        ${cells}</svg>`;
      $(this.el, '[data-i2-zoomval]').textContent = Math.round(this.zoom * 100) + '%';
      $(this.el, '[data-i2-sel]').textContent = this.sel ? Devlib.h.t('UP #:n selected').replace(':n', this.sel) : Devlib.h.t('Click an object to select it');
    }

    panels() {
      $(this.el, '[data-i2-jobs]').innerHTML = this.jobs.map(j => `<button type="button" class="dl-impo__job ${j === this.job ? 'is-on' : ''}" data-i2-job="${esc(j.code)}"><b>${esc(j.label)}</b><small>${esc(j.code)} · ${Devlib.h.t('flat blank')} ${this.n0(j.flat.w / 10)} × ${this.n0(j.flat.h / 10)} cm</small></button>`).join('');
      $(this.el, '[data-i2-planos]').innerHTML = this.planos.map(p => `<button type="button" class="dl-segment__opt ${p === this.plano ? 'is-active' : ''}" data-i2-plano="${esc(p.code)}">${esc(p.code)}<small>${this.job.results[p.code].up} up</small></button>`).join('');
      const best = this.planos.reduce((a, p) => this.job.results[p.code].efficiency > this.job.results[a.code].efficiency ? p : a, this.planos[0]);
      $(this.el, '[data-i2-kv]').innerHTML = `<dt>Plano</dt><dd>${this.n0(this.plano.w / 10)} × ${this.n0(this.plano.h / 10)} cm</dd><dt>${Devlib.h.t('Flat blank size')}</dt><dd>${this.n0(this.job.flat.w / 10)} × ${this.n0(this.job.flat.h / 10)} cm</dd><dt>${Devlib.h.t('Blank area')}</dt><dd>${this.n0(this.job.flat.w * this.job.flat.h / 100)} cm²</dd><dt>Gap / Gripper / Margin</dt><dd>${this.el.dataset.gap || 3} / ${this.G} / ${this.M} mm</dd><dt>${Devlib.h.t('Most efficient plano')}</dt><dd><b>${esc(best.code)}</b> (${this.n0(this.job.results[best.code].efficiency)}%)</dd>`;
      const r = this.res();
      $(this.el, '[data-i2-hero]').innerHTML = `<div><small>UP / plano</small><b>${r.up}</b></div><div><small>${Devlib.h.t('Method')}</small><b>${esc(r.method)}</b></div><div><small>${Devlib.h.t('Efficiency')}</small><b class="is-pos">${this.n0(r.efficiency)}%</b></div><div><small>Waste</small><b class="is-neg">${this.n0(r.waste)}%</b></div>`;
      $(this.el, '[data-i2-bar]').innerHTML = `<span style="width:${r.efficiency}%">${Devlib.h.t('Used')} ${this.n0(r.efficiency)}%</span><em>${Devlib.h.t('wasted')} ${this.n0(r.waste)}%</em>`;
      this.calcOut();
    }

    calcOut() {
      const r = this.res(), q = +String($(this.el, '[data-i2-qty]').value).replace(/\D/g, '') || 0, sp = parseFloat(String($(this.el, '[data-i2-spoil]').value).replace(',', '.')) || 0;
      $(this.el, '[data-i2-inlabel]').textContent = this.calc === 'pcs' ? Devlib.h.t('Qty order (pcs)') : Devlib.h.t('Number of planos (sheets)');
      let html;
      if (this.calc === 'pcs') { const net = r.up ? Math.ceil(q / r.up) : 0, extra = Math.ceil(net * sp / 100); html = `<div class="dl-impo__big"><small>${Devlib.h.t('Planos needed')}</small><b>${this.n0(net + extra)}</b><span>${Devlib.h.t('sheets')}</span></div><p>${Devlib.h.t(':net net + :extra spoilage / inschiet (:sp%) · leftover output :rest pcs').replace(':net', this.n0(net)).replace(':extra', this.n0(extra)).replace(':sp', this.n0(sp)).replace(':rest', this.n0((net) * r.up - q))}</p>`; }
      else { const net = Math.floor(q / (1 + sp / 100)); html = `<div class="dl-impo__big"><small>${Devlib.h.t('Product output')}</small><b>${this.n0(net * r.up)}</b><span>pcs</span></div><p>${Devlib.h.t(':q planos − spoilage / inschiet :sp% = :net effective planos × :up up').replace(':q', this.n0(q)).replace(':sp', this.n0(sp)).replace(':net', this.n0(net)).replace(':up', r.up)}</p>`; }
      $(this.el, '[data-i2-result]').innerHTML = html;
    }
  }
  Devlib.define("Imposition", Imposition);
})(window.Devlib);
