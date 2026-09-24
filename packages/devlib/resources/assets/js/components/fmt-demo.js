/* DevLib · FmtDemo */
(function (Devlib) {
  'use strict';
  const {$$, fmt} = Devlib.h;
  // Contoh format lokal hidup (halaman Locale & Formats).
  class FmtDemo extends Devlib.Component {
    static selector = "[data-dl-fmt-demo]";
    init() {
      this.tick();setInterval(this.tick, 1000);
    }

    tick() {
      return $$(this.el, '[data-fmt]').forEach(el => { el.textContent = new Function('fmt', 'return ' + el.dataset.fmt)(fmt); });
    }
  }
  Devlib.define("FmtDemo", FmtDemo);
})(window.Devlib);
