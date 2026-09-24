/* DevLib · Announce */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Announce extends Devlib.Component {
    static selector = "[data-dl-announce]";
    init() {
      this.lines = JSON.parse(this.el.dataset.lines || '[]');
      this.txt = $(this.el, '[data-dl-announce-text]');
      this.i = 0;
      if (this.lines.length > 1) setInterval(() => { this.i = (this.i + 1) % this.lines.length; this.txt.textContent = this.lines[this.i]; }, (+this.el.dataset.interval || 6) * 1000);
    }
  }
  Devlib.define("Announce", Announce);
})(window.Devlib);
