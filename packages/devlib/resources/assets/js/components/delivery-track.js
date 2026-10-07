/* DevLib · DeliveryTrack — truk menempuh rute SVG sampai titik tahap dokumen. Animasi = Anime.js v4 (js/vendor/anime.umd.min.js,
 * global `anime`); posisi dihitung getPointAtLength. Pembaruan tahap tanpa memuat ulang:
 * el.dispatchEvent(new CustomEvent('dl:track', {detail: {target: 1, moving: false}})). */
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
      if (this.anim) this.anim.pause();
      if (this.reduced || target === this.at || !window.anime) { this.place(target); return; }
      const pos = {p: this.at};
      this.anim = window.anime.animate(pos, {p: target, duration: 1400 * Math.max(0.3, Math.abs(target - this.at)), ease: 'inOutQuad', onUpdate: () => this.place(pos.p)});
    }
  }
  Devlib.define("DeliveryTrack", DeliveryTrack);
})(window.Devlib);