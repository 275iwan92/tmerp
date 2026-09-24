/* DevLib · Counter */
(function (Devlib) {
  'use strict';

  class Counter extends Devlib.Component {
    static selector = "[data-dl-counter]";
    init() {
      const el = this.el;
      const input = el.closest('.dl-field').querySelector('.dl-input'), max = el.dataset.max;
      const upd = () => { el.textContent = input.value.length + (max ? ' / ' + max : '') + ' karakter'; el.classList.toggle('is-full', !!max && input.value.length >= +max); };
      input.addEventListener('input', upd); upd();
    }
  }
  Devlib.define("Counter", Counter);
})(window.Devlib);
