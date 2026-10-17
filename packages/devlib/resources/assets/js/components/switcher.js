/* DevLib · Switcher: dropdown yang menukar panel x-dl::switch-case di bawahnya */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Switcher extends Devlib.Component {
    static selector = "[data-dl-switcher]";
    init() {
      this.select = $(this.el, '[data-sw-select]');
      this.select.addEventListener('change', () => this.show(this.select.value));
      this.show(this.select.value);
    }
    show(v) {
      [...$$(this.el, '[data-sw-case]')].forEach(c => { c.hidden = c.dataset.swCase !== v; });
    }
  }
  Devlib.define("Switcher", Switcher);
})(window.Devlib);
