/* DevLib · Tokens */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;
  // Nilai token terkini (ikut tema aktif) ditulis ke tabel dokumentasi.
  class Tokens extends Devlib.Component {
    static selector = ".dl-tokens";
    init() {
      this.paint();document.addEventListener('dl:theme', this.paint);
    }

    paint() {
      return $$(this.el, '[data-dl-token-value]').forEach(td => { td.textContent = getComputedStyle(document.documentElement).getPropertyValue(td.dataset.dlTokenValue).trim() || '—'; });
    }
  }
  Devlib.define("Tokens", Tokens);
})(window.Devlib);
