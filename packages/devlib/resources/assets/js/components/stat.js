/* DevLib · Stat */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;
  // Kotak statistik = filter: klik menyalakan, klik lagi melepas (satu aktif per kelompok).
  class Stat extends Devlib.Component {
    static selector = "[data-dl-stat]";
    init() {
      this.el.addEventListener('click', () => {
        const on = !this.el.classList.contains('is-active');
        (this.el.parentElement ? $$(this.el.parentElement, '[data-dl-stat]') : [this.el]).forEach(b => b.classList.remove('is-active'));
        this.el.classList.toggle('is-active', on);
        this.el.dispatchEvent(new CustomEvent('dl:stat', {bubbles: true, detail: {active: on}}));
      });
    }
  }
  Devlib.define("Stat", Stat);
})(window.Devlib);
