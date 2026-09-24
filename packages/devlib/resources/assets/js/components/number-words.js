/* DevLib · NumberWords */
(function (Devlib) {
  'use strict';
  const {$, $$, words} = Devlib.h;

  class NumberWords extends Devlib.Component {
    static selector = "[data-dl-words]";
    init() {
      this.C = JSON.parse(this.el.dataset.currencies);
      this.inp = $(this.el, '[data-dl-words-input]');
      this.hid = $(this.el, '[data-dl-words-value]');
      this.out = $(this.el, '[data-dl-words-text]');
      this.cc = $(this.el, '[data-dl-prefix-value]');
      this.inp.addEventListener('input', this.upd);
      $$(this.el, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { this.upd(); this.hid.dispatchEvent(new Event('change', {bubbles: true})); }));
      if (this.hid.value) this.inp.value = String(this.hid.value).replace('.', ',');
      this.upd();
    }

    places() {
      return this.cc ? this.C[this.cc.value][5] : +this.el.dataset.decimals;
    }

    upd() {
      const p = this.places(), neg = this.inp.value.trim().startsWith('-');
      let [a, b] = this.inp.value.replace(/[^\d,]/g, '').split(',');
      a = (a || '').replace(/^0+(?=\d)/, '').slice(0, 15);
      const hasComma = p > 0 && this.inp.value.includes(',');
      b = p > 0 ? (b || '').slice(0, p) : '';
      this.inp.value = (neg ? '-' : '') + (a ? (+a).toLocaleString('id-ID') : (hasComma ? '0' : '')) + (hasComma ? ',' + b : '');
      this.hid.value = a || b ? (neg ? '-' : '') + (a || '0') + (b ? '.' + b : '') : '';
      this.out.textContent = this.hid.value ? words(this.hid.value, this.cc ? this.C[this.cc.value] : null) : '';
      this.out.classList.toggle('is-empty', !this.hid.value);
    }
  }
  Devlib.define("NumberWords", NumberWords);
})(window.Devlib);
