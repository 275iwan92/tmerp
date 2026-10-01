/* DevLib · Notif */
(function (Devlib) {
  'use strict';
  const {$, $$, toast, T, Tf} = Devlib.h;

  class Notif extends Devlib.Component {
    static selector = "[data-dl-notif]";
    init() {
      this.all = $(this.el, '[data-dl-notif-all]');
      this.count = $(this.el, '[data-dl-notif-count]');
      this.el.addEventListener('click', e => { const it = e.target.closest('[data-dl-notif-item]'); if (it) { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); this.refresh(); } });
      if (this.all) this.all.addEventListener('click', () => { $$(this.el, '.dl-notif__item.is-unread').forEach(it => { it.classList.remove('is-unread'); const d = $(it, '.dl-notif__dot'); if (d) d.remove(); }); this.refresh(); toast(T('n_all_read'), 'success'); });
    }
    refresh() { const n = $$(this.el, '.dl-notif__item.is-unread').length; if (this.count) { this.count.textContent = Tf('n_unread', {n}); this.count.hidden = !n; } if (this.all) this.all.hidden = !n; }
  }
  Devlib.define("Notif", Notif);
})(window.Devlib);
