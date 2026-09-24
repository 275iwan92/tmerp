/* DevLib · Back */
(function (Devlib) {
  'use strict';
  // Back: ke layar sebelumnya bila datang dari situs ini, selain itu ke alamat fallback.
  class Back extends Devlib.Component {
    static selector = "[data-dl-back]";
    init() {
      const a = this.el;
      a.addEventListener('click', e => {
        if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) { e.preventDefault(); history.back(); }
      });
    }
  }
  Devlib.define("Back", Back);
})(window.Devlib);
