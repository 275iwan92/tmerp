/* DevLib · Notif */
(function (Devlib) {
  'use strict';
  const {$, $$, toast} = Devlib.h;

  class Notif extends Devlib.Component {
    static selector = "[data-dl-notif]";
    init() {
      const root = this.el;
      const all = $(root, '[data-dl-notif-all]'), count = $(root, '[data-dl-notif-count]');
      const refresh = () => { const n = $$(root, '.dl-notif__item.is-unread').length; if (count) { count.textContent = n + ' belum dibaca'; count.hidden = !n; } if (all) all.hidden = !n; };
      root.addEventListener('click', e => { const it = e.target.closest('[data-dl-notif-item]'); if (it) { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); refresh(); } });
      if (all) all.addEventListener('click', () => { $$(root, '.dl-notif__item.is-unread').forEach(it => { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); }); refresh(); toast('Semua notifikasi ditandai dibaca.', 'success'); });
    }
  }
  Devlib.define("Notif", Notif);
})(window.Devlib);
