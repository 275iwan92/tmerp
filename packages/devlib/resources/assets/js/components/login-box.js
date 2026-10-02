/* DevLib · LoginBox */
(function (Devlib) {
  'use strict';
  const {$, $$, LOGIN_T, t} = Devlib.h;
  // Bahasa selain id/en: teks English tabel LOGIN_T lewat kamus aplikasi.
  const lt = k => LOGIN_T[Devlib.lang]?.[k] ?? (LOGIN_T.en[k] != null ? t(LOGIN_T.en[k]) : '');

  class LoginBox extends Devlib.Component {
    static selector = "[data-dl-loginbox]";
    init() {
      this.alertBox = $(this.el, '[data-dl-alert]');
      this.errCode = this.el.dataset.error || '';
      document.addEventListener('dl:lang', this.applyLang);
      this.el.querySelector('form').addEventListener('submit', e => {
        let empty = false;
        $$(this.el, '.dl-field--lg').forEach(fd => { const v = $(fd, 'input').value.trim(); fd.classList.toggle('is-invalid', !v); if (!v) empty = true; });
        if (empty) { e.preventDefault(); this.errCode = 'empty'; this.showError(); }
      });
      this.applyLang();
    }
    showError() { if (!this.alertBox) return; this.alertBox.textContent = this.errCode ? lt(this.errCode === 'empty' ? 'err_empty' : 'err_wrong') : ''; this.alertBox.classList.toggle('is-show', !!this.errCode); }
    applyLang() { $$(this.el, '[data-i18n]').forEach(el => { const v = lt(el.dataset.i18n); if (v) el.textContent = v; }); this.showError(); }
  }
  Devlib.define("LoginBox", LoginBox);
})(window.Devlib);
