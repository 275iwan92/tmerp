/* DevLib · Flow */
(function (Devlib) {
  'use strict';
  // Diagram alur: blok ber-url membuka layarnya; blok tanpa url memancarkan dl:flow {key} (bisa di-override: open()).
  class Flow extends Devlib.Component {
    static selector = '[data-dl-flow]';
    init() {
      this.on('click', '[data-flow-key]', (e, a) => { e.preventDefault(); this.open(a.dataset.flowKey); });
    }
    open(key) {
      this.emit('dl:flow', {key});
    }
  }
  Devlib.define('Flow', Flow);
})(window.Devlib);
