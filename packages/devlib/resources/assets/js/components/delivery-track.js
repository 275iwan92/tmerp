/* DevLib · DeliveryTrack — truk menempuh rute SVG sampai titik tahap dokumen. Animasi = Anime.js v4 (js/vendor/anime.umd.min.js,
 * global `anime`). Seperti autoplay video: mulai saat objek terlihat (>= 60%), kembali ke awal saat keluar layar sehingga
 * berjalan lagi tiap muncul; tombol Replay memutar ulang kapan saja. Pembaruan tahap tanpa memuat ulang:
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
      this.target = parseFloat(this.el.dataset.target) || 0;
      this.moving = this.el.dataset.moving === '1';
      this.reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
      this.place(0);
      this.el.addEventListener('dl:track', e => { this.target = e.detail.target; this.moving = !!e.detail.moving; this.go(this.target, this.moving); });
      this.on('click', '[data-dl-track-replay]', () => this.play());
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(es => es.forEach(e => e.isIntersecting ? this.play() : this.rewind()), {threshold: 0.6}).observe(this.el);
      } else this.play();
    }

    place(p) {
      const a = this.road.getPointAtLength(p * this.len);
      this.truck.setAttribute('transform', `translate(${a.x},${a.y})`);
      this.trail.style.strokeDashoffset = this.len * (1 - p);
      this.at = p;
    }

    play() { this.stop(); this.place(0); this.go(this.target, this.moving); }

    rewind() { this.stop(); this.el.classList.remove('is-moving'); this.place(0); }

    stop() { if (this.anim) this.anim.pause(); }

    go(target, moving) {
      this.el.classList.toggle('is-moving', moving && !this.reduced);
      this.stop();
      if (this.reduced || target === this.at || !window.anime) { this.place(target); return; }
      const pos = {p: this.at};
      this.anim = window.anime.animate(pos, {p: target, duration: 1400 * Math.max(0.3, Math.abs(target - this.at)), ease: 'inOutQuad', onUpdate: () => this.place(pos.p)});
    }
  }
  Devlib.define("DeliveryTrack", DeliveryTrack);
})(window.Devlib);