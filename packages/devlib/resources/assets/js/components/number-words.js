/* DevLib · NumberWords */
(function (Devlib) {
  'use strict';
  const {$, $$, words} = Devlib.h;

  class NumberWords extends Devlib.Component {
    static selector = "[data-dl-words]";
    init() {
      const box = this.el;
      const C = JSON.parse(box.dataset.currencies), inp = $(box, '[data-dl-words-input]'), hid = $(box, '[data-dl-words-value]'), out = $(box, '[data-dl-words-text]'), cc = $(box, '[data-dl-prefix-value]');
      const places = () => cc ? C[cc.value][5] : +box.dataset.decimals;
      // Ketik gaya Indonesia: titik = ribuan (otomatis), koma = desimal.
      const upd = () => {
        const p = places(), neg = inp.value.trim().startsWith('-');
        let [a, b] = inp.value.replace(/[^\d,]/g, '').split(',');
        a = (a || '').replace(/^0+(?=\d)/, '').slice(0, 15);
        const hasComma = p > 0 && inp.value.includes(',');
        b = p > 0 ? (b || '').slice(0, p) : '';
        inp.value = (neg ? '-' : '') + (a ? (+a).toLocaleString('id-ID') : (hasComma ? '0' : '')) + (hasComma ? ',' + b : '');
        hid.value = a || b ? (neg ? '-' : '') + (a || '0') + (b ? '.' + b : '') : '';
        out.textContent = hid.value ? words(hid.value, cc ? C[cc.value] : null) : '';
        out.classList.toggle('is-empty', !hid.value);
      };
      inp.addEventListener('input', upd);
      $$(box, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { upd(); hid.dispatchEvent(new Event('change', {bubbles: true})); }));
      if (hid.value) inp.value = String(hid.value).replace('.', ',');
      upd();
    }
  }
  Devlib.define("NumberWords", NumberWords);
})(window.Devlib);
