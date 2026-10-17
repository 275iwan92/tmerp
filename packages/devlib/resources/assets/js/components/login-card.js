/* DevLib · LoginCard */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class LoginCard extends Devlib.Component {
    static selector = "[data-dl-logincard]";
    init() {
      $(this.el, '[data-lc-print]')?.addEventListener('click', () => this.print());
    }
    print() { window.print(); }
  }
  Devlib.define("LoginCard", LoginCard);
})(window.Devlib);
