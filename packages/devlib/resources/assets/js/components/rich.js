/* DevLib · Rich */
(function (Devlib) {
  'use strict';
  const {$, $$, val} = Devlib.h;

  class Rich extends Devlib.Component {
    static selector = "[data-dl-rich]";
    init() {
      this.area = $(this.el, '.dl-rich__area');
      this.val = $(this.el, '[data-dl-rich-value]');
      $$(this.el, '[data-cmd]').forEach(b => b.addEventListener('mousedown', e => { e.preventDefault(); document.execCommand(b.dataset.cmd); this.sync(); }));
      this.area.addEventListener('input', this.sync);
    }
    sync() { this.val.value = this.area.innerHTML; }
  }
  Devlib.define("Rich", Rich);
})(window.Devlib);
