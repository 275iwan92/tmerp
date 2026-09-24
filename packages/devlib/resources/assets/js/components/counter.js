/* DevLib · Counter */
(function (Devlib) {
  'use strict';

  class Counter extends Devlib.Component {
    static selector = "[data-dl-counter]";
    init() {
      this.input = this.el.closest('.dl-field').querySelector('.dl-input');
      this.max = this.el.dataset.max;
      this.input.addEventListener('input', this.upd);this.upd();
    }
    upd() { this.el.textContent = this.input.value.length + (this.max ? ' / ' + this.max : '') + ' karakter'; this.el.classList.toggle('is-full', !!this.max && this.input.value.length >= +this.max); }
  }
  Devlib.define("Counter", Counter);
})(window.Devlib);
