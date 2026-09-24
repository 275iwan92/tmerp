/* DevLib · Clock */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Clock extends Devlib.Component {
    static selector = "[data-dl-clock]";
    init() {
      this.t = $(this.el, '[data-dl-clock-time]');
      this.d = $(this.el, '[data-dl-clock-date]');
      this.timeZone = this.el.dataset.tz || undefined;
      this.tick();setInterval(this.tick, 1000);document.addEventListener('dl:lang', this.tick);
    }

    tick() {
      const now = new Date(), loc = Devlib.lang === 'id' ? 'id-ID' : 'en-US';
      if (this.t) this.t.textContent = now.toLocaleTimeString(loc, {hour12: false, timeZone: this.timeZone});
      this.d.textContent = now.toLocaleDateString(loc, {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: this.timeZone});
    }
  }
  Devlib.define("Clock", Clock);
})(window.Devlib);
