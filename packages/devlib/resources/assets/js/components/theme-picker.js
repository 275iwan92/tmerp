/* DevLib · ThemePicker */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;

  class ThemePicker extends Devlib.Component {
    static selector = "[data-dl-themepick]";
    init() {
      this.html = document.documentElement;
      $$(this.el, '[data-theme]').forEach(b => b.addEventListener('click', () => { this.html.dataset.dlTheme = b.dataset.theme; this.save('dl-theme', b.dataset.theme); this.mark(); }));
      $$(this.el, '[data-density]').forEach(b => b.addEventListener('click', () => { this.html.dataset.dlDensity = b.dataset.density; this.save('dl-density', b.dataset.density); this.mark(); }));
      $$(this.el, '[data-scale]').forEach(b => b.addEventListener('click', () => { this.html.style.setProperty('--dl-font-scale', b.dataset.scale); this.save('dl-font-scale', b.dataset.scale); this.mark(); }));
      this.mark();
    }
    save(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

    mark() {
      $$(this.el, '[data-theme]').forEach(b => b.classList.toggle('is-active', b.dataset.theme === this.html.dataset.dlTheme));
      $$(this.el, '[data-density]').forEach(b => b.classList.toggle('is-active', b.dataset.density === (this.html.dataset.dlDensity || 'normal')));
      const sc = this.html.style.getPropertyValue('--dl-font-scale') || '1';
      $$(this.el, '[data-scale]').forEach(b => b.classList.toggle('is-active', b.dataset.scale === sc.trim()));
      document.dispatchEvent(new CustomEvent('dl:theme'));
    }
  }
  Devlib.define("ThemePicker", ThemePicker);
})(window.Devlib);
