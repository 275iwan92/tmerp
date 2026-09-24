/* DevLib · Langs */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;

  class Langs extends Devlib.Component {
    static selector = "[data-dl-langs]";
    init() {
      $$(this.el, '.dl-flag').forEach(f => f.addEventListener('click', () => {
        Devlib.lang = f.dataset.lang;
        document.querySelectorAll('[data-dl-langs] .dl-flag').forEach(x => x.classList.toggle('is-active', x.dataset.lang === Devlib.lang));
        document.documentElement.lang = Devlib.lang;
        document.dispatchEvent(new CustomEvent('dl:lang', {detail: Devlib.lang}));
      }));
      $$(this.el, '.dl-flag').forEach(x => x.classList.toggle('is-active', x.dataset.lang === Devlib.lang));
    }
  }
  Devlib.define("Langs", Langs);
})(window.Devlib);
