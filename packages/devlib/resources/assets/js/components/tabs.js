/* DevLib · Tabs */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;

  class Tabs extends Devlib.Component {
    static selector = "[data-dl-tabs]";
    init() {
      $$(this.el, ':scope > .dl-tabs__bar .dl-tabs__tab').forEach(t => t.addEventListener('click', () => {
        $$(this.el, ':scope > .dl-tabs__bar .dl-tabs__tab').forEach(x => x.classList.toggle('is-active', x === t));
        $$(this.el, ':scope > .dl-tabs__panel').forEach(p => p.classList.toggle('is-active', p.dataset.panel === t.dataset.tab));
      }));
    }
  }
  Devlib.define("Tabs", Tabs);
})(window.Devlib);
