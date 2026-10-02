/* DevLib · Langs */
(function (Devlib) {
  'use strict';
  const {$$, T, Tf} = Devlib.h;

  class Langs extends Devlib.Component {
    static selector = "[data-dl-langs]";
    init() {
      const mark = () => document.querySelectorAll('[data-dl-langs] .dl-flag').forEach(x => x.classList.toggle('is-active', x.dataset.lang === Devlib.lang));
      const reload = this.el.dataset.reload != null;
      // Bahasa berganti: cookie perangkat + teks [data-dl-t] (event dl:lang). Dengan data-reload pilihan disimpan ke akun
      // user (DL_LANG_URL) lalu halaman dimuat ulang, karena hampir semua teks dirender server.
      const apply = lang => {
        Devlib.lang = lang; mark();
        document.documentElement.lang = lang;
        document.dispatchEvent(new CustomEvent('dl:lang', {detail: lang}));
        if (!reload) return;
        const done = () => location.reload();
        if (!window.DL_LANG_URL) return done();
        const f = new FormData(); f.append('_token', window.DL_CSRF || ''); f.append('lang', lang);
        fetch(window.DL_LANG_URL, {method: 'POST', body: f, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}}).catch(() => {}).finally(done);
      };
      $$(this.el, '.dl-flag').forEach(f => f.addEventListener('click', () => {
        const lang = f.dataset.lang; if (lang === Devlib.lang) return;
        // Tab yang isinya belum di-Save ikut dimuat ulang -> tanya sekali dulu (sama dengan klik logo aplikasi).
        const shell = document.querySelector('.dl-shell')?.dlComponent, n = reload && shell?.dirtyTabs ? shell.dirtyTabs().length : 0;
        if (!n) return apply(lang);
        Devlib.confirm({title: T('lang_reload_title'), message: Tf('lang_reload_msg', {n}), ok: T('lang_reload_ok')}).then(ok => { if (ok) apply(lang); });
      }));
      mark();
    }
  }
  Devlib.define("Langs", Langs);
})(window.Devlib);
