/* DevLib · Split */
(function (Devlib) {
  'use strict';
  const {$, $$, val} = Devlib.h;

  class Split extends Devlib.Component {
    static selector = "[data-dl-split]";
    init() {
      this.val = $(this.el, '[data-dl-split-value]');
      $(this.el, '[data-dl-split-toggle]')?.addEventListener('click', e => { e.stopPropagation(); this.el.classList.toggle('is-open'); });
      document.addEventListener('click', () => this.el.classList.remove('is-open'));
      $$(this.el, '.dl-splitbtn__item').forEach(it => it.addEventListener('click', e => {
        e.stopPropagation();
        this.val.value = it.dataset.action;
        $(this.el, '[data-dl-split-label]').textContent = it.dataset.action;
        $(this.el, '[data-dl-split-icon]').innerHTML = $(it, 'span').innerHTML;
        this.el.classList.remove('is-open');
      }));
    }
  }
  Devlib.define("Split", Split);
})(window.Devlib);
