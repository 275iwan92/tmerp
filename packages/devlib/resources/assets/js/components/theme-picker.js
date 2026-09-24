/* DevLib · ThemePicker */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;

  class ThemePicker extends Devlib.Component {
    static selector = "[data-dl-themepick]";
    init() {
      const root = this.el;
      const html = document.documentElement;
      const save = (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} };
      const mark = () => {
        $$(root, '[data-theme]').forEach(b => b.classList.toggle('is-active', b.dataset.theme === html.dataset.dlTheme));
        $$(root, '[data-density]').forEach(b => b.classList.toggle('is-active', b.dataset.density === (html.dataset.dlDensity || 'normal')));
        const sc = html.style.getPropertyValue('--dl-font-scale') || '1';
        $$(root, '[data-scale]').forEach(b => b.classList.toggle('is-active', b.dataset.scale === sc.trim()));
        document.dispatchEvent(new CustomEvent('dl:theme'));
      };
      $$(root, '[data-theme]').forEach(b => b.addEventListener('click', () => { html.dataset.dlTheme = b.dataset.theme; save('dl-theme', b.dataset.theme); mark(); }));
      $$(root, '[data-density]').forEach(b => b.addEventListener('click', () => { html.dataset.dlDensity = b.dataset.density; save('dl-density', b.dataset.density); mark(); }));
      $$(root, '[data-scale]').forEach(b => b.addEventListener('click', () => { html.style.setProperty('--dl-font-scale', b.dataset.scale); save('dl-font-scale', b.dataset.scale); mark(); }));
      mark();
    }
  }
  Devlib.define("ThemePicker", ThemePicker);
})(window.Devlib);
