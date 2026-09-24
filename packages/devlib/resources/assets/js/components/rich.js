/* DevLib · Rich */
(function (Devlib) {
  'use strict';
  const {$, $$, val} = Devlib.h;

  class Rich extends Devlib.Component {
    static selector = "[data-dl-rich]";
    init() {
      const root = this.el;
      const area = $(root, '.dl-rich__area'), val = $(root, '[data-dl-rich-value]');
      const sync = () => { val.value = area.innerHTML; };
      $$(root, '[data-cmd]').forEach(b => b.addEventListener('mousedown', e => { e.preventDefault(); document.execCommand(b.dataset.cmd); sync(); }));
      area.addEventListener('input', sync);
    }
  }
  Devlib.define("Rich", Rich);
})(window.Devlib);
