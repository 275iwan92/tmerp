/* DevLib · Device */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Device extends Devlib.Component {
    static selector = "[data-dl-device]";
    init() {
      const root = this.el;
      const view = $(root, '[data-dl-device-view]');
      $$(root, '.dl-device__btn').forEach(b => b.addEventListener('click', () => {
        $$(root, '.dl-device__btn').forEach(x => x.classList.toggle('is-active', x === b));
        view.className = 'dl-device__view is-' + b.dataset.mode;
      }));
    }
  }
  Devlib.define("Device", Device);
})(window.Devlib);
