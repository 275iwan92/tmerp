/* DevLib · Slider */
(function (Devlib) {
  'use strict';
  const {$, $$, idNum, fmt, Tf} = Devlib.h;
  // Slider: angka / tanggal, satu gagang / rentang, horizontal / vertikal.
  class Slider extends Devlib.Component {
    static selector = "[data-dl-slider]";
    init() {
      [this.ia, this.ib] = $$(this.el, '.dl-slider__input');
      this.ha = $(this.el, '[data-dl-slider-a]');
      this.hb = $(this.el, '[data-dl-slider-b]');
      this.out = $(this.el, '[data-dl-slider-value]');
      this.min = +this.ia.min;
      this.max = +this.ia.max;
      this.date = this.el.dataset.kind === 'date';
      this.s0 = this.date ? Date.parse(this.el.dataset.start + 'T00:00:00Z') : 0;
      [this.ia, this.ib].filter(Boolean).forEach(i => i.addEventListener('input', this.u));this.u();
    }

    iso(n) {
      return new Date(this.s0 + n * 86400000).toISOString().slice(0, 10);
    }

    show(n) {
      return this.date ? fmt.date(this.iso(n) + 'T12:00:00+07:00') : this.el.dataset.format.replace('{v}', idNum(n));
    }

    pct(v) {
      return this.max === this.min ? 0 : (v - this.min) / (this.max - this.min) * 100;
    }

    u(e) {
      if (this.ib && +this.ia.value > +this.ib.value) { if (e && e.target === this.ib) this.ib.value = this.ia.value; else this.ia.value = this.ib.value; }
      const a = +this.ia.value, b = this.ib ? +this.ib.value : a;
      this.el.style.setProperty('--a', this.ib ? this.pct(a) : 0); this.el.style.setProperty('--b', this.pct(b));
      this.ia.style.zIndex = this.ib && a >= this.max - (this.max - this.min) * 0.02 ? 3 : 1;
      this.ha.value = this.date ? this.iso(a) : a; if (this.hb) this.hb.value = this.date ? this.iso(b) : b;
      this.out.textContent = this.ib ? `${this.show(a)} – ${this.show(b)}` + (this.date ? ' · ' + Tf('sl_days', {n: b - a + 1}) : '') : this.show(a);
    }
  }
  Devlib.define("Slider", Slider);
})(window.Devlib);
