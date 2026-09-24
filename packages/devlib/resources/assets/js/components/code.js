/* DevLib · Code */
(function (Devlib) {
  'use strict';

  class Code extends Devlib.Component {
    static selector = "[data-dl-code]";
    init() {
      const root = this.el;
      const modal = root.querySelector('[data-dl-code-modal]');
      root.querySelector('[data-dl-code-open]').addEventListener('click', () => modal.classList.add('is-on'));
      root.querySelector('[data-dl-code-close]').addEventListener('click', () => modal.classList.remove('is-on'));
      modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('is-on'); });
      if (location.hash === '#kode') modal.classList.add('is-on');
      root.querySelectorAll('[data-tab]').forEach(t => t.addEventListener('click', () => {
        root.querySelectorAll('[data-tab]').forEach(x => x.classList.toggle('is-active', x === t));
        root.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('is-active', p.dataset.pane === t.dataset.tab));
      }));
    }
  }
  Devlib.define("Code", Code);
})(window.Devlib);
