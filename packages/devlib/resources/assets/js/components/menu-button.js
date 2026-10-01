/* DevLib · MenuButton — tombol + daftar pilihan (x-dl::menu-button). Item ber-href membuka alamatnya;
   item ber-act memancarkan event dl:menu {act} untuk dijalankan layar/komponen pemakainya. */
(function (Devlib) {
  'use strict';

  class MenuButton extends Devlib.Component {
    static selector = '[data-dl-menubtn]';

    init() {
      this.on('click', '[data-mb-toggle]', e => { e.stopPropagation(); this.el.classList.toggle('is-open'); });
      document.addEventListener('click', e => { if (!this.el.contains(e.target)) this.el.classList.remove('is-open'); });
      this.on('click', '[data-mb-item]', (e, b) => {
        this.el.classList.remove('is-open');
        const href = b.dataset.href;
        if (href) { if (b.hasAttribute('data-blank')) window.open(href, '_blank', 'noopener'); else location.href = href; return; }
        this.el.dispatchEvent(new CustomEvent('dl:menu', {bubbles: true, detail: {act: b.dataset.mbAct}}));
      });
    }
  }
  Devlib.define('MenuButton', MenuButton);
})(window.Devlib);
