/* DevLib · Qrcode */
(function (Devlib) {
  'use strict';
  const {loadLib} = Devlib.h;

  class Qrcode extends Devlib.Component {
    static selector = "[data-dl-qrcode]";
    init() {
      loadLib('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', () => window.QRCode).then(() => {
        this.el.innerHTML = ''; new window.QRCode(this.el, {text: this.el.dataset.text || ' ', width: +this.el.dataset.size, height: +this.el.dataset.size, colorDark: '#111827', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel.M});
        const img = this.el.querySelector('img'); if (img) img.alt = 'QR ' + this.el.dataset.text;
      }).catch(() => { this.el.textContent = this.el.dataset.text; this.el.classList.add('is-fallback'); });
    }
  }
  Devlib.define("Qrcode", Qrcode);
})(window.Devlib);
