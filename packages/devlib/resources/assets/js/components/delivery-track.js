/* DevLib · DeliveryTrack — truk menempuh rute SVG sampai titik tahap dokumen. Tanpa library: requestAnimationFrame + getPointAtLength.
 * Pembaruan tahap tanpa memuat ulang: el.dispatchEvent(new CustomEvent('dl:track', {detail: {target: 1, moving: false}})). */
(function (Devlib) {
  'use strict';

  class DeliveryTrack extends Devlib.Component {
    static selector = "[data-dl-track]";

    init() {
      this.road = this.$('[data-dl-track-road]');
      this.trail = this.$('[data-dl-track-trail]');
      this.truck = this.$('[data-dl-track-truck]');
      if (!this.road || !this.trail || !this.truck) return;
      this.len = this.road.getTotalLength();
      this.trail.style.strokeDasharray = this.len;
      this.at = 0;
      this.reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
      this.el.addEventListener('dl:track', e => this.go(e.detail.target, !!e.detail.moving));
      this.go(parseFloat(this.el.dataset.target) || 0, this.el.dataset.moving === '1');
    }

    place(p) {
      const a = this.road.getPointAtLength(p * this.len);
      this.truck.setAttribute('transform', `translate(${a.x},${a.y})`);
      this.trail.style.strokeDashoffset = this.len * (1 - p);
      this.at = p;
    }

    go(target, moving) {
      this.el.classList.toggle('is-moving', moving && !this.reduced);
      cancelAnimationFrame(this.raf);
      if (this.reduced || target === this.at) { this.place(target); return; }
      const from = this.at, t0 = performance.now(), ms = 1400 * Math.max(0.3, Math.abs(target - from));
      const step = now => {
        const k = Math.min(1, (now - t0) / ms), e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
        this.place(from + (target - from) * e);
        if (k < 1) this.raf = requestAnimationFrame(step);
      };
      this.raf = requestAnimationFrame(step);
    }
  }
  Devlib.define("DeliveryTrack", DeliveryTrack);
})(window.Devlib);