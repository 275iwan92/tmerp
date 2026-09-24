/* DevLib · Segment */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Segment extends Devlib.Component {
    static selector = "[data-dl-segment]";
    init() {
      const root = this.el;
      const hidden = $(root, '[data-dl-value]');
      $$(root, '.dl-segment__opt').forEach(b => b.addEventListener('click', () => {
        $$(root, '.dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === b));
        hidden.value = b.dataset.value; hidden.dispatchEvent(new Event('change', {bubbles: true}));
      }));
    }
  }
  Devlib.define("Segment", Segment);
})(window.Devlib);
