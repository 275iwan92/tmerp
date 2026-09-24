/* DevLib · Rating */
(function (Devlib) {
  'use strict';
  const {$, $$, at} = Devlib.h;
  // Rating: bintang penuh / setengah, klik yang sama = kosongkan, readonly tanpa interaksi.
  class Rating extends Devlib.Component {
    static selector = "[data-dl-rating]";
    init() {
      this.stars = $$(this.el, '.dl-rating__star');
      this.hidden = $(this.el, '[data-dl-value]');
      this.max = +this.el.dataset.max;
      this.half = this.el.dataset.half === '1';
      this.paint(+this.hidden.value);this.text(+this.hidden.value);
      if (this.el.classList.contains('is-readonly')) return;
      this.stars.forEach(s => {
        s.addEventListener('click', e => { const v = this.at(s, e); this.hidden.value = v === +this.hidden.value ? 0 : v; this.paint(+this.hidden.value); this.text(+this.hidden.value); this.hidden.dispatchEvent(new Event('change', {bubbles: true})); });
        s.addEventListener('mousemove', e => this.paint(this.at(s, e)));
      });
      this.el.addEventListener('mouseleave', () => this.paint(+this.hidden.value));
    }
    paint(v) { this.stars.forEach(s => { const i = +s.dataset.value; s.classList.toggle('is-active', i <= v); s.classList.toggle('is-half', i - 0.5 === v); }); }
    text(v) { $(this.el, '[data-dl-rating-text]').textContent = `${String(v).replace('.', ',')} / ${this.max}`; this.el.setAttribute('aria-valuenow', v); }
    at(s, e) { const i = +s.dataset.value; return this.half && e.offsetX < s.offsetWidth / 2 ? i - 0.5 : i; }
  }
  Devlib.define("Rating", Rating);
})(window.Devlib);
