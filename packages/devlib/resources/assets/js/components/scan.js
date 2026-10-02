/* DevLib · Scan */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  // Kotak pindai: scanner keyboard = ketik + Enter (form biasa). Kamera = BarcodeDetector (Chrome / Edge / Android) di
  // halaman aman (https / localhost); tanpa dukungan itu tombol kamera tidak ditampilkan.
  class Scan extends Devlib.Component {
    static selector = "[data-dl-scan]";
    init() {
      this.input = $(this.el, '[data-scan-input]'); this.box = $(this.el, '[data-scan-video]'); this.video = $(this.box, 'video');
      const cam = $(this.el, '[data-scan-cam]');
      if ('BarcodeDetector' in window && navigator.mediaDevices && window.isSecureContext) cam.hidden = false;
      cam.addEventListener('click', () => this.start());
      $(this.el, '[data-scan-stop]').addEventListener('click', () => this.stop());
      this.el.addEventListener('submit', e => {
        const code = this.input.value.trim();
        if (!code) { e.preventDefault(); return; }
        if (!this.emit('dl:scan', {code}, true) || !this.el.getAttribute('action')) e.preventDefault();
      });
    }

    async start() {
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}});
        this.video.srcObject = this.stream; await this.video.play(); this.box.hidden = false;
        const det = new window.BarcodeDetector({formats: ['qr_code', 'code_128', 'code_39', 'ean_13']});
        const tick = async () => {
          if (!this.stream) return;
          try {
            const found = await det.detect(this.video);
            if (found.length) { this.input.value = found[0].rawValue; this.stop(); this.el.requestSubmit ? this.el.requestSubmit() : this.el.submit(); return; }
          } catch (e) { /* bingkai belum siap */ }
          this.timer = setTimeout(tick, 250);
        };
        tick();
      } catch (e) { Devlib.toast(Devlib.h.t('The camera cannot be opened. Type the code instead.'), 'warn'); this.stop(); }
    }

    stop() {
      clearTimeout(this.timer);
      if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
      this.box.hidden = true; this.input.focus();
    }
  }
  Devlib.define("Scan", Scan);
})(window.Devlib);
