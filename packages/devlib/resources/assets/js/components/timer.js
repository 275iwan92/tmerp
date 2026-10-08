/* DevLib · Timer */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Timer extends Devlib.Component {
    static selector = "[data-dl-timer]";
    init() {
      this.out = $(this.el, '[data-dl-timer-text]');
      this.base = parseInt(this.el.dataset.seconds || '0', 10);
      this.t0 = Date.now();
      this.running = this.el.dataset.running === '1';
      this.tick();
      if (this.running) setInterval(this.tick, 1000);
    }

    tick() {
      const s = this.base + (this.running ? Math.floor((Date.now() - this.t0) / 1000) : 0), p = n => String(n).padStart(2, '0');
      this.out.textContent = p(Math.floor(s / 3600)) + ':' + p(Math.floor(s % 3600 / 60)) + ':' + p(s % 60);
    }
  }
  Devlib.define("Timer", Timer);
})(window.Devlib);
