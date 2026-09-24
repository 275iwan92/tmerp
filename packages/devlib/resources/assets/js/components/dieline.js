/* DevLib · Dieline */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, T} = Devlib.h;
  /* ============================ DIELINE ============================ */
  // Hanya "lem" UI; semua geometri dihitung TMDieline (salinan utuh dari tmerp).
  class Dieline extends Devlib.Component {
    static selector = "[data-dl-dieline]";
    init() {
      const el = this.el;
      const T = JSON.parse(el.dataset.types || '{}'), name = el.dataset.name;
      let code = el.dataset.type, dims = JSON.parse(el.dataset.dims || '{}'), zoom = 1, manual = false;
      const q = s => $(el, s), fw = q('[data-d-fw]'), fh = q('[data-d-fh]'), fa = q('[data-d-fa]');
      const numStr = v => v === '' || v == null || isNaN(v) ? '' : (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
      function fields() {
        const t = T[code]; if (!t) return;
        dims = Object.fromEntries(Object.entries(t.params).map(([k, p]) => [k, dims[k] ?? p.default ?? '']));
        q('[data-d-sizes]').innerHTML = (t.sizes || []).map((s, i) => `<button type="button" class="dl-dieline__size" data-d-size="${i}">${esc(s.label)}</button>`).join('') || '<span class="dl-dieline__none">Belum ada ukuran pasaran untuk tipe ini.</span>';
        q('[data-d-dims]').innerHTML = Object.entries(t.params).filter(([, p]) => p.type === 'NUM' || typeof p.default === 'number').map(([k, p]) => `<label class="dl-dieline__dim"><span>${esc(p.label)}</span><span class="dl-field__box"><input class="dl-input" type="text" inputmode="decimal" name="${esc(name)}[dims][${esc(k)}]" data-d-dim="${esc(k)}" value="${esc(dims[k] ?? '')}"><em>${esc(p.unit || 'mm')}</em></span></label>`).join('');
        markSize();
      }
      const markSize = () => { const t = T[code]; $$(el, '[data-d-size]').forEach(b => { const s = t.sizes[+b.dataset.dSize]; b.classList.toggle('is-on', Object.entries(s.dims).every(([k, v]) => +dims[k] === +v)); }); };
      function redraw() {
        const t = T[code], spec = t ? t.spec : null;
        if (!manual) { const f = window.TMDieline.flat(spec, dims); fw.value = f.w || ''; fh.value = f.h || ''; }
        const a = (parseFloat(String(fw.value).replace(',', '.')) || 0) * (parseFloat(String(fh.value).replace(',', '.')) || 0);
        fa.value = a ? a.toFixed(2) : '';
        window.TMDieline.render(q('[data-d-preview]'), spec, dims);
        q('[data-d-preview]').style.transform = `scale(${zoom})`; q('[data-d-zoomval]').textContent = Math.round(zoom * 100) + '%';
        q('[data-d-info]').innerHTML = t ? `<b>${esc(t.label)}</b>${t.category ? ` · ${esc(t.category)}` : ''} · Bentang <b>${numStr(fw.value)} × ${numStr(fh.value)} mm</b> · Luas <b>${numStr(a / 100)} cm²</b>${manual ? ' <span class="dl-badge dl-badge--warn">manual</span>' : ''}` : '';
        markSize();
        el.dispatchEvent(new CustomEvent('dl:dieline', {bubbles: true, detail: {type: code, dims: {...dims}, flat: {w: +fw.value || 0, h: +fh.value || 0, area: a}, manual}}));
      }
      el.addEventListener('input', e => {
        const d = e.target.dataset.dDim;
        if (d !== undefined) { dims[d] = e.target.value === '' ? '' : parseFloat(e.target.value.replace(',', '.')); return redraw(); }
        if (e.target.matches('[data-d-fw],[data-d-fh]') && manual) redraw();
      });
      el.addEventListener('change', e => {
        if (e.target.name === name + '[type]') { code = e.target.value; dims = {}; fields(); redraw(); }
        if (e.target.matches('[data-d-manual]')) { manual = e.target.checked; fw.readOnly = fh.readOnly = !manual; redraw(); }
      });
      el.addEventListener('click', e => {
        const s = e.target.closest('[data-d-size]');
        if (s) { Object.assign(dims, T[code].sizes[+s.dataset.dSize].dims); $$(el, '[data-d-dim]').forEach(i => { i.value = dims[i.dataset.dDim] ?? ''; }); return redraw(); }
        const z = e.target.closest('[data-d-zoom]'); if (z) { zoom = Math.min(3, Math.max(0.4, Math.round((zoom + +z.dataset.dZoom * 0.2) * 10) / 10)); return redraw(); }
        if (e.target.closest('[data-d-svg]')) {
          const svg = window.TMDieline.svg(T[code].spec, dims); if (!svg) return toast('Isi dimensi dulu.', 'warn');
          const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([svg.replace('<svg ', '<svg style="background:#1b1f2a" ')], {type: 'image/svg+xml'})); a.download = `dieline-${code.toLowerCase()}.svg`; a.click();
        }
      });
      el.dlDieline = {get dims() { return {...dims}; }, setType: c => { code = c; dims = {}; fields(); redraw(); }, redraw};
      if (!window.TMDieline) { q('[data-d-preview]').textContent = 'TMDieline tidak termuat.'; return; }
      fields(); redraw();
    }
  }
  Devlib.define("Dieline", Dieline);
})(window.Devlib);
