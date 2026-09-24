/* DevLib · Slider */
(function (Devlib) {
  'use strict';
  const {$, $$, idNum, fmt} = Devlib.h;
  // Slider: angka / tanggal, satu gagang / rentang, horizontal / vertikal.
  class Slider extends Devlib.Component {
    static selector = "[data-dl-slider]";
    init() {
      const sl = this.el;
      const [ia, ib] = $$(sl, '.dl-slider__input'), ha = $(sl, '[data-dl-slider-a]'), hb = $(sl, '[data-dl-slider-b]'), out = $(sl, '[data-dl-slider-value]');
      const min = +ia.min, max = +ia.max, date = sl.dataset.kind === 'date';
      const s0 = date ? Date.parse(sl.dataset.start + 'T00:00:00Z') : 0;
      const iso = n => new Date(s0 + n * 86400000).toISOString().slice(0, 10);
      const show = n => date ? fmt.date(iso(n) + 'T12:00:00+07:00') : sl.dataset.format.replace('{v}', idNum(n));
      const pct = v => max === min ? 0 : (v - min) / (max - min) * 100;
      const u = e => {
        if (ib && +ia.value > +ib.value) { if (e && e.target === ib) ib.value = ia.value; else ia.value = ib.value; }
        const a = +ia.value, b = ib ? +ib.value : a;
        sl.style.setProperty('--a', ib ? pct(a) : 0); sl.style.setProperty('--b', pct(b));
        ia.style.zIndex = ib && a >= max - (max - min) * 0.02 ? 3 : 1;
        ha.value = date ? iso(a) : a; if (hb) hb.value = date ? iso(b) : b;
        out.textContent = ib ? `${show(a)} – ${show(b)}` + (date ? ` · ${b - a + 1} hari` : '') : show(a);
      };
      [ia, ib].filter(Boolean).forEach(i => i.addEventListener('input', u)); u();
    }
  }
  Devlib.define("Slider", Slider);
})(window.Devlib);
