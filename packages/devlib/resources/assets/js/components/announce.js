/* DevLib · Announce */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Announce extends Devlib.Component {
    static selector = "[data-dl-announce]";
    init() {
      const root = this.el;
      const lines = JSON.parse(root.dataset.lines || '[]'), txt = $(root, '[data-dl-announce-text]');
      let i = 0;
      if (lines.length > 1) setInterval(() => { i = (i + 1) % lines.length; txt.textContent = lines[i]; }, (+root.dataset.interval || 6) * 1000);
    }
  }
  Devlib.define("Announce", Announce);
})(window.Devlib);
