/* DevLib · FmtDemo */
(function (Devlib) {
  'use strict';
  const {$$, fmt} = Devlib.h;
  // Contoh format lokal hidup (halaman Locale & Formats).
  class FmtDemo extends Devlib.Component {
    static selector = "[data-dl-fmt-demo]";
    init() {
      const root = this.el;
      const tick = () => $$(root, '[data-fmt]').forEach(el => { el.textContent = new Function('fmt', 'return ' + el.dataset.fmt)(fmt); });
      tick(); setInterval(tick, 1000);
    }
  }
  Devlib.define("FmtDemo", FmtDemo);
})(window.Devlib);
