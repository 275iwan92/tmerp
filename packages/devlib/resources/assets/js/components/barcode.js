/* DevLib · Barcode */
(function (Devlib) {
  'use strict';
  const {$, esc, loadLib} = Devlib.h;

  class Barcode extends Devlib.Component {
    static selector = "[data-dl-barcode]";
    init() {
      const el = this.el;
      loadLib('https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js', () => window.JsBarcode).then(() => {
        try { window.JsBarcode(el, el.dataset.value, {format: el.dataset.format, height: +el.dataset.height, width: +el.dataset.width, displayValue: el.dataset.text === '1', margin: 0, fontSize: 13, font: 'monospace', background: 'transparent'}); }
        catch (e) { el.outerHTML = `<span class="dl-barcode__err">Barcode ${esc(el.dataset.format)} tidak sah: ${esc(el.dataset.value)}</span>`; }
      }).catch(() => { el.outerHTML = `<code>${esc(el.dataset.value)}</code>`; });
    }
  }
  Devlib.define("Barcode", Barcode);
})(window.Devlib);
