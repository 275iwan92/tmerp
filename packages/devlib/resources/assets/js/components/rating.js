/* DevLib · Rating */
(function (Devlib) {
  'use strict';
  const {$, $$, at} = Devlib.h;
  // Rating: bintang penuh / setengah, klik yang sama = kosongkan, readonly tanpa interaksi.
  class Rating extends Devlib.Component {
    static selector = "[data-dl-rating]";
    init() {
      const rt = this.el;
      const stars = $$(rt, '.dl-rating__star'), hidden = $(rt, '[data-dl-value]'), max = +rt.dataset.max, half = rt.dataset.half === '1';
      const paint = v => { stars.forEach(s => { const i = +s.dataset.value; s.classList.toggle('is-active', i <= v); s.classList.toggle('is-half', i - 0.5 === v); }); };
      const text = v => { $(rt, '[data-dl-rating-text]').textContent = `${String(v).replace('.', ',')} / ${max}`; rt.setAttribute('aria-valuenow', v); };
      const at = (s, e) => { const i = +s.dataset.value; return half && e.offsetX < s.offsetWidth / 2 ? i - 0.5 : i; };
      paint(+hidden.value); text(+hidden.value);
      if (rt.classList.contains('is-readonly')) return;
      stars.forEach(s => {
        s.addEventListener('click', e => { const v = at(s, e); hidden.value = v === +hidden.value ? 0 : v; paint(+hidden.value); text(+hidden.value); hidden.dispatchEvent(new Event('change', {bubbles: true})); });
        s.addEventListener('mousemove', e => paint(at(s, e)));
      });
      rt.addEventListener('mouseleave', () => paint(+hidden.value));
    }
  }
  Devlib.define("Rating", Rating);
})(window.Devlib);
