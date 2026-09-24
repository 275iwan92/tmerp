/* DevLib · Barcode */
(function (Devlib) {
  'use strict';
  const {$, esc, loadLib} = Devlib.h;

  class Barcode extends Devlib.Component {
    static selector = "[data-dl-barcode]";
    init() {
      loadLib('https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.6/JsBarcode.all.min.js', () => window.JsBarcode).then(() => {
        try { window.JsBarcode(this.el, this.el.dataset.value, {format: this.el.dataset.format, height: +this.el.dataset.height, width: +this.el.dataset.width, displayValue: this.el.dataset.text === '1', margin: 0, fontSize: 13, font: 'monospace', background: 'transparent'}); }
        catch (e) { this.el.outerHTML = `<span class="dl-barcode__err">Barcode ${esc(this.el.dataset.format)} tidak sah: ${esc(this.el.dataset.value)}</span>`; }
      }).catch(() => { this.el.outerHTML = `<code>${esc(this.el.dataset.value)}</code>`; });
    }
  }
  Devlib.define("Barcode", Barcode);
})(window.Devlib);
