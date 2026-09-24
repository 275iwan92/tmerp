/* DevLib · Segment */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Segment extends Devlib.Component {
    static selector = "[data-dl-segment]";
    init() {
      this.hidden = $(this.el, '[data-dl-value]');
      $$(this.el, '.dl-segment__opt').forEach(b => b.addEventListener('click', () => {
        $$(this.el, '.dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === b));
        this.hidden.value = b.dataset.value; this.hidden.dispatchEvent(new Event('change', {bubbles: true}));
      }));
    }
  }
  Devlib.define("Segment", Segment);
})(window.Devlib);
