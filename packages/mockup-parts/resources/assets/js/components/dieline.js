/* DevLib · Dieline */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, T} = Devlib.h;
  /* ============================ DIELINE ============================ */
  // Hanya "lem" UI; semua geometri dihitung TMDieline (salinan utuh dari tmerp).
  class Dieline extends Devlib.Component {
    static selector = "[data-dl-dieline]";
    init() {
      const self = this;
      this.T = JSON.parse(this.el.dataset.types || '{}');
      this.name = this.el.dataset.name;
      this.code = this.el.dataset.type;
      this.dims = JSON.parse(this.el.dataset.dims || '{}');
      this.zoom = 1;
      this.manual = false;
      this.q = s => $(this.el, s);
      this.fw = this.q('[data-d-fw]');
      this.fh = this.q('[data-d-fh]');
      this.fa = this.q('[data-d-fa]');
      this.el.addEventListener('input', e => {
        const d = e.target.dataset.dDim;
        if (d !== undefined) { this.dims[d] = e.target.value === '' ? '' : parseFloat(e.target.value.replace(',', '.')); return this.redraw(); }
        if (e.target.matches('[data-d-fw],[data-d-fh]') && this.manual) this.redraw();
      });
      this.el.addEventListener('change', e => {
        if (e.target.name === this.name + '[type]') { this.code = e.target.value; this.dims = {}; this.fields(); this.redraw(); }
        if (e.target.matches('[data-d-manual]')) { this.manual = e.target.checked; this.fw.readOnly = this.fh.readOnly = !this.manual; this.redraw(); }
      });
      this.el.addEventListener('click', e => {
        const s = e.target.closest('[data-d-size]');
        if (s) { Object.assign(this.dims, this.T[this.code].sizes[+s.dataset.dSize].dims); $$(this.el, '[data-d-dim]').forEach(i => { i.value = this.dims[i.dataset.dDim] ?? ''; }); return this.redraw(); }
        const z = e.target.closest('[data-d-zoom]'); if (z) { this.zoom = Math.min(3, Math.max(0.4, Math.round((this.zoom + +z.dataset.dZoom * 0.2) * 10) / 10)); return this.redraw(); }
        if (e.target.closest('[data-d-svg]')) {
          const svg = window.TMDieline.svg(this.T[this.code].spec, this.dims); if (!svg) return toast(Devlib.h.t('Fill in the dimensions first.'), 'warn');
          const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([svg.replace('<svg ', '<svg style="background:#1b1f2a" ')], {type: 'image/svg+xml'})); a.download = `dieline-${this.code.toLowerCase()}.svg`; a.click();
        }
      });
      this.el.dlDieline = {get dims() { return {...self.dims}; }, setType: c => { this.code = c; this.dims = {}; this.fields(); this.redraw(); }, redraw: this.redraw};
      if (!window.TMDieline) { this.q('[data-d-preview]').textContent = Devlib.h.t('TMDieline is not loaded.'); return; }
      this.fields();this.redraw();
    }

    numStr(v) {
      return v === '' || v == null || isNaN(v) ? '' : (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
    }

    fields() {
      const t = this.T[this.code]; if (!t) return;
      this.dims = Object.fromEntries(Object.entries(t.params).map(([k, p]) => [k, this.dims[k] ?? p.default ?? '']));
      this.q('[data-d-sizes]').innerHTML = (t.sizes || []).map((s, i) => `<button type="button" class="dl-dieline__size" data-d-size="${i}">${esc(s.label)}</button>`).join('') || '<span class="dl-dieline__none">' + Devlib.h.t('No standard size for this type yet.') + '</span>';
      this.q('[data-d-dims]').innerHTML = Object.entries(t.params).filter(([, p]) => p.type === 'NUM' || typeof p.default === 'number').map(([k, p]) => `<label class="dl-dieline__dim"><span>${esc(p.label)}</span><span class="dl-field__box"><input class="dl-input" type="text" inputmode="decimal" name="${esc(this.name)}[dims][${esc(k)}]" data-d-dim="${esc(k)}" value="${esc(this.dims[k] ?? '')}"><em>${esc(p.unit || 'mm')}</em></span></label>`).join('');
      this.markSize();
    }

    markSize() { const t = this.T[this.code]; $$(this.el, '[data-d-size]').forEach(b => { const s = t.sizes[+b.dataset.dSize]; b.classList.toggle('is-on', Object.entries(s.dims).every(([k, v]) => +this.dims[k] === +v)); }); }

    redraw() {
      const t = this.T[this.code], spec = t ? t.spec : null;
      if (!this.manual) { const f = window.TMDieline.flat(spec, this.dims); this.fw.value = f.w || ''; this.fh.value = f.h || ''; }
      const a = (parseFloat(String(this.fw.value).replace(',', '.')) || 0) * (parseFloat(String(this.fh.value).replace(',', '.')) || 0);
      this.fa.value = a ? a.toFixed(2) : '';
      window.TMDieline.render(this.q('[data-d-preview]'), spec, this.dims);
      this.q('[data-d-preview]').style.transform = `scale(${this.zoom})`; this.q('[data-d-zoomval]').textContent = Math.round(this.zoom * 100) + '%';
      this.q('[data-d-info]').innerHTML = t ? `<b>${esc(t.label)}</b>${t.category ? ` · ${esc(t.category)}` : ''} · ${Devlib.h.t('Flat blank')} <b>${this.numStr(this.fw.value)} × ${this.numStr(this.fh.value)} mm</b> · ${Devlib.h.t('Area')} <b>${this.numStr(a / 100)} cm²</b>${this.manual ? ' <span class="dl-badge dl-badge--warn">manual</span>' : ''}` : '';
      this.markSize();
      this.el.dispatchEvent(new CustomEvent('dl:dieline', {bubbles: true, detail: {type: this.code, dims: {...this.dims}, flat: {w: +this.fw.value || 0, h: +this.fh.value || 0, area: a}, manual: this.manual}}));
    }
  }
  Devlib.define("Dieline", Dieline);
})(window.Devlib);
