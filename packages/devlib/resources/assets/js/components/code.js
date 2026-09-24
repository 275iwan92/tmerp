/* DevLib · Code */
(function (Devlib) {
  'use strict';

  class Code extends Devlib.Component {
    static selector = "[data-dl-code]";
    init() {
      this.modal = this.el.querySelector('[data-dl-code-modal]');
      this.el.querySelector('[data-dl-code-open]').addEventListener('click', () => this.modal.classList.add('is-on'));
      this.el.querySelector('[data-dl-code-close]').addEventListener('click', () => this.modal.classList.remove('is-on'));
      this.modal.addEventListener('click', e => { if (e.target === this.modal) this.modal.classList.remove('is-on'); });
      if (location.hash === '#kode') this.modal.classList.add('is-on');
      this.el.querySelectorAll('[data-tab]').forEach(t => t.addEventListener('click', () => {
        this.el.querySelectorAll('[data-tab]').forEach(x => x.classList.toggle('is-active', x === t));
        this.el.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('is-active', p.dataset.pane === t.dataset.tab));
      }));
    }
  }
  Devlib.define("Code", Code);
})(window.Devlib);
