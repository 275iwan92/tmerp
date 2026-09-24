/* DevLib · AccGroup */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class AccGroup extends Devlib.Component {
    static selector = "[data-dl-accgroup]";
    init() {
      const g = this.el;
      if (g.classList.contains('dl-accgroup--horizontal') && !$(g, ':scope > .dl-accordion.is-open')) { const a = $(g, ':scope > .dl-accordion'); if (a) a.classList.add('is-open'); }
    }
  }
  Devlib.define("AccGroup", AccGroup);
})(window.Devlib);
