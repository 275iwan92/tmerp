/* DevLib · Device */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Device extends Devlib.Component {
    static selector = "[data-dl-device]";
    init() {
      this.view = $(this.el, '[data-dl-device-view]');
      $$(this.el, '.dl-device__btn').forEach(b => b.addEventListener('click', () => {
        $$(this.el, '.dl-device__btn').forEach(x => x.classList.toggle('is-active', x === b));
        this.view.className = 'dl-device__view is-' + b.dataset.mode;
      }));
    }
  }
  Devlib.define("Device", Device);
})(window.Devlib);
