/* DevLib · Stat */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;
  // Kotak statistik = filter: klik menyalakan, klik lagi melepas (satu aktif per kelompok).
  class Stat extends Devlib.Component {
    static selector = "[data-dl-stat]";
    init() {
      const btn = this.el;
      btn.addEventListener('click', () => {
        const on = !btn.classList.contains('is-active');
        (btn.parentElement ? $$(btn.parentElement, '[data-dl-stat]') : [btn]).forEach(b => b.classList.remove('is-active'));
        btn.classList.toggle('is-active', on);
        btn.dispatchEvent(new CustomEvent('dl:stat', {bubbles: true, detail: {active: on}}));
      });
    }
  }
  Devlib.define("Stat", Stat);
})(window.Devlib);
