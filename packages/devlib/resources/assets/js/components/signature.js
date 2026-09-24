/* DevLib · Signature */
(function (Devlib) {
  'use strict';
  const {$, toast} = Devlib.h;
  // Tanda tangan: goresan disimpan per garis (untuk Undo), digambar ulang saat ukuran berubah.
  class Signature extends Devlib.Component {
    static selector = "[data-dl-sign]";
    init() {
      const box = this.el;
      const cv = $(box, 'canvas'), ctx = cv.getContext('2d'), hidden = $(box, '[data-dl-value]'), ph = $(box, '[data-dl-sign-ph]'), st = $(box, '[data-dl-sign-status]');
      const ro = box.classList.contains('is-readonly'); let strokes = [], cur = null, bg = null;
      const ink = () => getComputedStyle(box).getPropertyValue('--dl-ink-strong').trim() || '#111';
      const draw = () => {
        const r = cv.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
        cv.width = r.width * dpr; cv.height = r.height * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        if (bg) ctx.drawImage(bg, 0, 0, r.width, r.height);
        ctx.lineCap = ctx.lineJoin = 'round'; ctx.strokeStyle = ink();
        strokes.forEach(p => { ctx.lineWidth = 2.2; ctx.beginPath(); ctx.moveTo(p[0][0], p[0][1]); for (let i = 1; i < p.length - 1; i++) ctx.quadraticCurveTo(p[i][0], p[i][1], (p[i][0] + p[i + 1][0]) / 2, (p[i][1] + p[i + 1][1]) / 2); const l = p[p.length - 1]; ctx.lineTo(l[0], l[1] + (p.length === 1 ? 0.1 : 0)); ctx.stroke(); });
      };
      const sync = () => {
        const has = strokes.length > 0 || !!bg;
        ph.hidden = has; st.textContent = has ? 'Sudah ditandatangani' : 'Belum ditandatangani'; box.classList.toggle('is-signed', has);
        hidden.value = has ? cv.toDataURL('image/png') : ''; hidden.dispatchEvent(new Event('change', {bubbles: true}));
      };
      const pos = e => { const r = cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
      if (!ro) {
        cv.addEventListener('pointerdown', e => { cv.setPointerCapture(e.pointerId); cur = [pos(e)]; strokes.push(cur); draw(); });
        cv.addEventListener('pointermove', e => { if (!cur) return; cur.push(pos(e)); draw(); });
        const end = () => { if (cur) { cur = null; sync(); } };
        cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);
        $(box, '[data-dl-sign-undo]').addEventListener('click', () => { if (strokes.length) strokes.pop(); else bg = null; draw(); sync(); });
        $(box, '[data-dl-sign-clear]').addEventListener('click', () => { strokes = []; bg = null; draw(); sync(); });
      }
      $(box, '[data-dl-sign-png]').addEventListener('click', () => { if (!hidden.value) { toast('Belum ada tanda tangan.', 'warn'); return; } const a = document.createElement('a'); a.href = hidden.value; a.download = 'tanda-tangan.png'; a.click(); });
      if (hidden.value) { const im = new Image(); im.onload = () => { bg = im; draw(); sync(); }; im.src = hidden.value; }
      new ResizeObserver(draw).observe(cv); draw(); if (!hidden.value) sync();
    }
  }
  Devlib.define("Signature", Signature);
})(window.Devlib);
