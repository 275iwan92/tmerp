/* DevLib · Uat */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Uat extends Devlib.Component {
    static selector = "[data-dl-uat]";
    init() {
      const root = this.el;
      $(root, '[data-dl-uat-toggle]').addEventListener('click', e => { e.stopPropagation(); root.classList.toggle('is-open'); });
      root.addEventListener('click', e => e.stopPropagation());
      document.addEventListener('click', () => root.classList.remove('is-open'));
    }
  }
  Devlib.define("Uat", Uat);
})(window.Devlib);
