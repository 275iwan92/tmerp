/* DevLib · Greeting */
(function (Devlib) {
  'use strict';

  class Greeting extends Devlib.Component {
    static selector = "[data-dl-greeting]";
    init() {
      this.G = ['gr_morning', 'gr_afternoon', 'gr_evening', 'gr_night'];
      this.upd();setInterval(this.upd, 60000);document.addEventListener('dl:lang', this.upd);
    }

    upd() {
      const h = +new Date().toLocaleString('en-US', {hour: 'numeric', hour12: false, timeZone: this.el.dataset.tz || undefined}) % 24;
      const i = h < 11 ? 0 : h < 15 ? 1 : h < 18 ? 2 : 3;
      this.el.textContent = Devlib.h.T(this.G[i]) + (this.el.dataset.name ? ', ' + this.el.dataset.name : '') + ' 👋';
    }
  }
  Devlib.define("Greeting", Greeting);
})(window.Devlib);
