/* DevLib · CodeGenerator */
(function (Devlib) {
  'use strict';
  const {$, loadLib, toast} = Devlib.h;

  // Pembuat QR / barcode: gambar dibuat ulang tiap isian berubah. Barcode 1D = <svg> JsBarcode, QR = qrcodejs.
  class CodeGenerator extends Devlib.Component {
    static selector = "[data-dl-codegen]";
    init() {
      this.types = JSON.parse(this.el.dataset.types);
      this.qr = $(this.el, '[data-cg-qr]'); this.bar = $(this.el, '[data-cg-bar]'); this.err = $(this.el, '[data-cg-err]');
      this.el.addEventListener('input', () => this.draw());
      this.el.addEventListener('change', e => { if (e.target.name === 'type') this.pickType(); this.draw(); });
      $(this.el, '[data-cg-png]').addEventListener('click', () => this.png());
      $(this.el, '[data-cg-svg]').addEventListener('click', () => this.svg());
      $(this.el, '[data-cg-print]').addEventListener('click', () => this.print());
      this.draw();
    }

    get(name) { const f = this.el.querySelector(`[name="${name}"]`); return f ? f.value : ''; }
    get kind() { return this.types[this.get('type')] || this.types.QR; }
    get isQr() { return !this.kind.format; }

    pickType() {
      const input = this.el.querySelector('[name="value"]');
      if (input) { input.value = this.kind.example; input.dispatchEvent(new Event('dl:grow')); }
    }

    show(msg) { this.err.hidden = !msg; this.err.textContent = msg || ''; }

    async draw() {
      const qr = this.isQr;
      $(this.el, '[data-cg-qr-opts]').hidden = !qr; $(this.el, '[data-cg-bar-opts]').hidden = qr;
      this.qr.hidden = !qr; this.bar.hidden = qr;
      $(this.el, '[data-cg-hint]').textContent = this.kind.hint;
      $(this.el, '[data-cg-svg]').hidden = qr;
      const value = this.get('value');
      if (!value) { this.show(Devlib.h.t('Type the content first.')); this.clear(); return; }
      try {
        if (qr) await this.drawQr(value); else await this.drawBar(value);
        this.show('');
      } catch (e) {
        this.clear(); this.show(Devlib.h.t('This content is not valid for the chosen code type.') + ' ' + this.kind.hint);
      }
    }

    clear() { this.qr.innerHTML = ''; while (this.bar.firstChild) this.bar.removeChild(this.bar.firstChild); }

    async drawQr(value) {
      await loadLib('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', () => window.QRCode);
      const size = Math.min(1000, Math.max(80, +this.get('size') || 200));
      this.qr.innerHTML = '';
      new window.QRCode(this.qr, {text: value, width: size, height: size, colorDark: '#111827', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel[this.get('ec') || 'M']});
    }

    async drawBar(value) {
      await loadLib('https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js', () => window.JsBarcode);
      let bad = false;
      window.JsBarcode(this.bar, value, {
        format: this.kind.format, height: Math.max(20, +this.get('height') || 70), width: Math.max(1, +this.get('width') || 2),
        displayValue: !!this.el.querySelector('[name="text"]').checked, margin: 10, fontSize: 14, font: 'monospace', background: '#ffffff',
        valid: ok => { bad = !ok; },
      });
      if (bad) throw new Error('invalid');
    }

    fileName(ext) { return (this.get('type') + '-' + this.get('value')).replace(/[^A-Za-z0-9_-]+/g, '_').slice(0, 60) + '.' + ext; }

    save(blob, name) {
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click();
      setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }

    // PNG: QR sudah <canvas>; barcode = SVG digambar ke canvas 2x supaya tajam saat dicetak.
    toCanvas() {
      if (this.isQr) { const c = this.qr.querySelector('canvas'); return Promise.resolve(c); }
      return new Promise((ok, no) => {
        const xml = new XMLSerializer().serializeToString(this.bar), img = new Image();
        img.onload = () => {
          const c = document.createElement('canvas'), w = this.bar.width.baseVal.value, h = this.bar.height.baseVal.value;
          c.width = w * 2; c.height = h * 2; const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0, c.width, c.height); ok(c);
        };
        img.onerror = no; img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
      });
    }

    async png() {
      if (!this.ready()) return;
      const c = await this.toCanvas();
      if (!c) return toast(Devlib.h.t('Type the content first.'), 'warn');
      c.toBlob(b => this.save(b, this.fileName('png')), 'image/png');
    }

    svg() {
      if (!this.ready() || this.isQr) return;
      this.save(new Blob([new XMLSerializer().serializeToString(this.bar)], {type: 'image/svg+xml'}), this.fileName('svg'));
    }

    async print() {
      if (!this.ready()) return;
      const c = await this.toCanvas(); if (!c) return;
      const w = window.open('', '_blank'); if (!w) return toast(Devlib.h.t('The print window was blocked.'), 'warn');
      w.document.write('<!doctype html><title>' + this.get('value').replace(/[<&]/g, '') + '</title><body style="margin:0;display:grid;place-items:center;min-height:100vh"><img style="max-width:90vw" src="' + c.toDataURL('image/png') + '"></body>');
      w.document.close(); w.onload = () => { w.print(); };
      setTimeout(() => { try { w.print(); } catch (e) { /* sudah dicetak */ } }, 400);
    }

    ready() { if (this.err.hidden) return true; toast(this.err.textContent, 'warn'); return false; }
  }
  Devlib.define("CodeGenerator", CodeGenerator);
})(window.Devlib);
