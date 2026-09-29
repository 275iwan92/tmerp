/* DevLib · Dashboard */
(function (Devlib) {
  'use strict';
  const {$, $$, dayOfYear} = Devlib.h;

  // Opening screen: tombol periode (Harian .. 12 Bulan) mengganti panel KPI/grafik/aktivitas; pilihan diingat per browser.
  class Dashboard extends Devlib.Component {
    static selector = "[data-dl-dash]";
    init() {
      this.key = 'dl-dash-period-' + this.el.dataset.key;
      let saved = null; try { saved = localStorage.getItem(this.key); } catch (e) {}
      if (saved && $(this.el, `[data-dash-panel="${saved}"]`)) this.show(saved);
      $$(this.el, '[data-dash-period]').forEach(b => b.addEventListener('click', () => {
        this.show(b.dataset.dashPeriod);
        try { localStorage.setItem(this.key, b.dataset.dashPeriod); } catch (e) {}
      }));
      const doy = $(this.el, '[data-dash-doy]');
      if (doy) doy.textContent = dayOfYear();
    }

    show(p) {
      $$(this.el, '[data-dash-period]').forEach(b => b.classList.toggle('is-active', b.dataset.dashPeriod === p));
      $$(this.el, '[data-dash-panel]').forEach(x => x.classList.toggle('is-active', x.dataset.dashPanel === p));
    }
  }
  Devlib.define("Dashboard", Dashboard);
})(window.Devlib);
