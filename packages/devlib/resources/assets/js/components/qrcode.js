/* DevLib · Qrcode */
(function (Devlib) {
  'use strict';
  const {loadLib} = Devlib.h;

  class Qrcode extends Devlib.Component {
    static selector = "[data-dl-qrcode]";
    init() {
      const el = this.el;
      loadLib('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', () => window.QRCode).then(() => {
        el.innerHTML = ''; new window.QRCode(el, {text: el.dataset.text || ' ', width: +el.dataset.size, height: +el.dataset.size, colorDark: '#111827', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel.M});
        const img = el.querySelector('img'); if (img) img.alt = 'QR ' + el.dataset.text;
      }).catch(() => { el.textContent = el.dataset.text; el.classList.add('is-fallback'); });
    }
  }
  Devlib.define("Qrcode", Qrcode);
})(window.Devlib);
