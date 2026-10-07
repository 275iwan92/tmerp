/* DevLib · Blueprint (zoom/pan via viewBox, crosshair, HUD) */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class Blueprint extends Devlib.Component {
    static selector = "[data-dl-blueprint]";
    init() {
      this.svg = $(this.el, '[data-dl-bp-svg]');
      this.hud = $(this.el, '[data-dl-bp-hud]');
      this.cross = $(this.el, '[data-dl-bp-cross]');
      const w = +this.el.dataset.w, h = +this.el.dataset.h;
      this.home = [-20, -20, w + 40, h + 40];
      this.vb = this.home.slice();
      this.unit = this.el.dataset.unit || 'mm';
      this.svg.addEventListener('wheel', e => { e.preventDefault(); this.zoomAt(e, e.deltaY < 0 ? .8 : 1.25); }, {passive: false});
      this.svg.addEventListener('pointerdown', e => { this.drag = {x: e.clientX, y: e.clientY}; this.svg.setPointerCapture(e.pointerId); });
      this.svg.addEventListener('pointerup', () => { this.drag = null; });
      this.svg.addEventListener('pointermove', e => this.move(e));
      this.svg.addEventListener('pointerleave', () => { this.cross.hidden = true; });
      $(this.el, '[data-dl-bp-zin]').addEventListener('click', () => this.zoomAt(null, .8));
      $(this.el, '[data-dl-bp-zout]').addEventListener('click', () => this.zoomAt(null, 1.25));
      $(this.el, '[data-dl-bp-fit]').addEventListener('click', () => { this.vb = this.home.slice(); this.apply(); });
    }

    apply() { this.svg.setAttribute('viewBox', this.vb.join(' ')); }

    world(e) {
      const p = this.svg.createSVGPoint(); p.x = e.clientX; p.y = e.clientY;
      return p.matrixTransform(this.svg.getScreenCTM().inverse());
    }

    zoomAt(e, k) {
      const c = e ? this.world(e) : {x: this.vb[0] + this.vb[2] / 2, y: this.vb[1] + this.vb[3] / 2};
      this.vb = [c.x - (c.x - this.vb[0]) * k, c.y - (c.y - this.vb[1]) * k, this.vb[2] * k, this.vb[3] * k];
      this.apply();
    }

    move(e) {
      if (this.drag) {
        const r = this.svg.getBoundingClientRect(), s = Math.max(this.vb[2] / r.width, this.vb[3] / r.height);
        this.vb[0] -= (e.clientX - this.drag.x) * s;
        this.vb[1] -= (e.clientY - this.drag.y) * s;
        this.drag = {x: e.clientX, y: e.clientY};
        this.apply();
      }
      const p = this.world(e), h = $(this.cross, '[data-h]'), v = $(this.cross, '[data-v]');
      this.cross.hidden = false;
      h.setAttribute('y1', p.y); h.setAttribute('y2', p.y);
      v.setAttribute('x1', p.x); v.setAttribute('x2', p.x);
      this.hud.textContent = 'X ' + p.x.toFixed(1) + ' Y ' + p.y.toFixed(1) + ' ' + this.unit + ' · ' + Math.round(this.home[2] / this.vb[2] * 100) + '%';
    }
  }
  Devlib.define("Blueprint", Blueprint);
})(window.Devlib);