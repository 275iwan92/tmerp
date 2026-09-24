/* DevLib · Signature */
(function (Devlib) {
  'use strict';
  const {$, toast} = Devlib.h;
  // Tanda tangan: goresan disimpan per garis (untuk Undo), digambar ulang saat ukuran berubah.
  class Signature extends Devlib.Component {
    static selector = "[data-dl-sign]";
    init() {
      this.cv = $(this.el, 'canvas');
      this.ctx = this.cv.getContext('2d');
      this.hidden = $(this.el, '[data-dl-value]');
      this.ph = $(this.el, '[data-dl-sign-ph]');
      this.st = $(this.el, '[data-dl-sign-status]');
      this.ro = this.el.classList.contains('is-readonly');
      this.strokes = [];
      this.cur = null;
      this.bg = null;
      if (!this.ro) {
        this.cv.addEventListener('pointerdown', e => { this.cv.setPointerCapture(e.pointerId); this.cur = [this.pos(e)]; this.strokes.push(this.cur); this.draw(); });
        this.cv.addEventListener('pointermove', e => { if (!this.cur) return; this.cur.push(this.pos(e)); this.draw(); });
        const end = () => { if (this.cur) { this.cur = null; this.sync(); } };
        this.cv.addEventListener('pointerup', end); this.cv.addEventListener('pointercancel', end);
        $(this.el, '[data-dl-sign-undo]').addEventListener('click', () => { if (this.strokes.length) this.strokes.pop(); else this.bg = null; this.draw(); this.sync(); });
        $(this.el, '[data-dl-sign-clear]').addEventListener('click', () => { this.strokes = []; this.bg = null; this.draw(); this.sync(); });
      }
      $(this.el, '[data-dl-sign-png]').addEventListener('click', () => { if (!this.hidden.value) { toast('Belum ada tanda tangan.', 'warn'); return; } const a = document.createElement('a'); a.href = this.hidden.value; a.download = 'tanda-tangan.png'; a.click(); });
      if (this.hidden.value) { const im = new Image(); im.onload = () => { this.bg = im; this.draw(); this.sync(); }; im.src = this.hidden.value; }
      new ResizeObserver(this.draw).observe(this.cv);this.draw();if (!this.hidden.value) this.sync();
    }

    ink() {
      return getComputedStyle(this.el).getPropertyValue('--dl-ink-strong').trim() || '#111';
    }

    draw() {
      const r = this.cv.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
      this.cv.width = r.width * dpr; this.cv.height = r.height * dpr; this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      if (this.bg) this.ctx.drawImage(this.bg, 0, 0, r.width, r.height);
      this.ctx.lineCap = this.ctx.lineJoin = 'round'; this.ctx.strokeStyle = this.ink();
      this.strokes.forEach(p => { this.ctx.lineWidth = 2.2; this.ctx.beginPath(); this.ctx.moveTo(p[0][0], p[0][1]); for (let i = 1; i < p.length - 1; i++) this.ctx.quadraticCurveTo(p[i][0], p[i][1], (p[i][0] + p[i + 1][0]) / 2, (p[i][1] + p[i + 1][1]) / 2); const l = p[p.length - 1]; this.ctx.lineTo(l[0], l[1] + (p.length === 1 ? 0.1 : 0)); this.ctx.stroke(); });
    }

    sync() {
      const has = this.strokes.length > 0 || !!this.bg;
      this.ph.hidden = has; this.st.textContent = has ? 'Sudah ditandatangani' : 'Belum ditandatangani'; this.el.classList.toggle('is-signed', has);
      this.hidden.value = has ? this.cv.toDataURL('image/png') : ''; this.hidden.dispatchEvent(new Event('change', {bubbles: true}));
    }

    pos(e) { const r = this.cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; }
  }
  Devlib.define("Signature", Signature);
})(window.Devlib);
