/* DevLib · Notif */
(function (Devlib) {
  'use strict';
  const {$, $$, toast} = Devlib.h;

  class Notif extends Devlib.Component {
    static selector = "[data-dl-notif]";
    init() {
      this.all = $(this.el, '[data-dl-notif-all]');
      this.count = $(this.el, '[data-dl-notif-count]');
      this.el.addEventListener('click', e => { const it = e.target.closest('[data-dl-notif-item]'); if (it) { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); this.refresh(); } });
      if (this.all) this.all.addEventListener('click', () => { $$(this.el, '.dl-notif__item.is-unread').forEach(it => { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); }); this.refresh(); toast('Semua notifikasi ditandai dibaca.', 'success'); });
    }
    refresh() { const n = $$(this.el, '.dl-notif__item.is-unread').length; if (this.count) { this.count.textContent = n + ' belum dibaca'; this.count.hidden = !n; } if (this.all) this.all.hidden = !n; }
  }
  Devlib.define("Notif", Notif);
})(window.Devlib);
