/* DevLib · Split */
(function (Devlib) {
  'use strict';
  const {$, $$, val} = Devlib.h;

  class Split extends Devlib.Component {
    static selector = "[data-dl-split]";
    init() {
      const root = this.el;
      const val = $(root, '[data-dl-split-value]');
      $(root, '[data-dl-split-toggle]').addEventListener('click', e => { e.stopPropagation(); root.classList.toggle('is-open'); });
      $$(root, '.dl-split__item').forEach(it => it.addEventListener('click', e => {
        e.stopPropagation();
        val.value = it.dataset.action;
        $(root, '[data-dl-split-label]').textContent = it.dataset.action;
        $(root, '[data-dl-split-icon]').textContent = it.dataset.icon;
        root.classList.remove('is-open');
      }));
    }
  }
  Devlib.define("Split", Split);
})(window.Devlib);
