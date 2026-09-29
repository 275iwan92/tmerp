/* MockupParts · Box3d — kotak 3D dari artwork; geser (mouse/jari) untuk memutar. */
(function (Devlib) {
  'use strict';
  class Box3d extends Devlib.Component {
    init() {
      const cube = this.el.querySelector('.mp-box3d__cube');
      let rx = -18, ry = -32, drag = null;
      this.el.addEventListener('pointerdown', e => { drag = [e.clientX, e.clientY, rx, ry]; this.el.setPointerCapture(e.pointerId); });
      this.el.addEventListener('pointermove', e => {
        if (!drag) return;
        ry = drag[3] + (e.clientX - drag[0]) * .5;
        rx = Math.max(-80, Math.min(80, drag[2] - (e.clientY - drag[1]) * .5));
        cube.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg)`;
      });
      this.el.addEventListener('pointerup', () => { drag = null; });
    }
  }
  Devlib.register(Box3d);
})(window.Devlib);
