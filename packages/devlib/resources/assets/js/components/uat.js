/* DevLib · Uat */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Uat extends Devlib.Component {
    static selector = "[data-dl-uat]";
    init() {
      $(this.el, '[data-dl-uat-toggle]').addEventListener('click', e => { e.stopPropagation(); this.el.classList.toggle('is-open'); });
      this.el.addEventListener('click', e => e.stopPropagation());
      document.addEventListener('click', () => this.el.classList.remove('is-open'));
    }
  }
  Devlib.define("Uat", Uat);
})(window.Devlib);
