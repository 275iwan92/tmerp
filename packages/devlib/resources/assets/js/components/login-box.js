/* DevLib · LoginBox */
(function (Devlib) {
  'use strict';
  const {$, $$, LOGIN_T} = Devlib.h;

  class LoginBox extends Devlib.Component {
    static selector = "[data-dl-loginbox]";
    init() {
      const root = this.el;
      const alertBox = $(root, '[data-dl-alert]');
      let errCode = root.dataset.error || '';
      const showError = () => { if (!alertBox) return; alertBox.textContent = errCode ? LOGIN_T[Devlib.lang][errCode === 'empty' ? 'err_empty' : 'err_wrong'] : ''; alertBox.classList.toggle('is-show', !!errCode); };
      const applyLang = () => { $$(root, '[data-i18n]').forEach(el => { const k = el.dataset.i18n; if (LOGIN_T[Devlib.lang][k]) el.textContent = LOGIN_T[Devlib.lang][k]; }); showError(); };
      document.addEventListener('dl:lang', applyLang);
      root.querySelector('form').addEventListener('submit', e => {
        let empty = false;
        $$(root, '.dl-field--lg').forEach(fd => { const v = $(fd, 'input').value.trim(); fd.classList.toggle('is-invalid', !v); if (!v) empty = true; });
        if (empty) { e.preventDefault(); errCode = 'empty'; showError(); }
      });
      applyLang();
    }
  }
  Devlib.define("LoginBox", LoginBox);
})(window.Devlib);
