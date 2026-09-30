/* DevLib · NumberWords */
(function (Devlib) {
  'use strict';
  const {$, $$, words, fmt, NUM} = Devlib.h;

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
      // Pemisah desimal ikut bahasa tampilan (id koma, en titik); nilai kanonik dikirim lewat input tersembunyi.
      const D = NUM.dec;
      let [a, b] = this.inp.value.replace(D === '.' ? /[^\d.]/g : /[^\d,]/g, '').split(D);
      a = (a || '').replace(/^0+(?=\d)/, '').slice(0, 15);
      const hasComma = p > 0 && this.inp.value.includes(D);
      b = p > 0 ? (b || '').slice(0, p) : '';
      this.inp.value = (neg ? '-' : '') + (a ? fmt.number(+a) : (hasComma ? '0' : '')) + (hasComma ? D + b : '');
      this.hid.value = a || b ? (neg ? '-' : '') + (a || '0') + (b ? '.' + b : '') : '';
      this.out.textContent = this.hid.value ? words(this.hid.value, this.cc ? this.C[this.cc.value] : null) : '';
      this.out.classList.toggle('is-empty', !this.hid.value);
    }
  }
  Devlib.define("NumberWords", NumberWords);
})(window.Devlib);
