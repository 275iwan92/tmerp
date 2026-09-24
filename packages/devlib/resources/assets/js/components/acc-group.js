/* DevLib · AccGroup */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class AccGroup extends Devlib.Component {
    static selector = "[data-dl-accgroup]";
    init() {
      if (this.el.classList.contains('dl-accgroup--horizontal') && !$(this.el, ':scope > .dl-accordion.is-open')) { const a = $(this.el, ':scope > .dl-accordion'); if (a) a.classList.add('is-open'); }
    }
  }
  Devlib.define("AccGroup", AccGroup);
})(window.Devlib);
