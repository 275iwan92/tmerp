/* DevLib · Clock */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Clock extends Devlib.Component {
    static selector = "[data-dl-clock]";
    init() {
      const root = this.el;
      const t = $(root, '[data-dl-clock-time]'), d = $(root, '[data-dl-clock-date]'), timeZone = root.dataset.tz || undefined;
      const tick = () => {
        const now = new Date(), loc = Devlib.lang === 'id' ? 'id-ID' : 'en-US';
        if (t) t.textContent = now.toLocaleTimeString(loc, {hour12: false, timeZone});
        d.textContent = now.toLocaleDateString(loc, {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone});
      };
      tick(); setInterval(tick, 1000); document.addEventListener('dl:lang', tick);
    }
  }
  Devlib.define("Clock", Clock);
})(window.Devlib);
