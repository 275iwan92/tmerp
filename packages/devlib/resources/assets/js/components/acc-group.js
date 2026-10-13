/*
 * REMARK
 * Tujuan       : Memulihkan panel accordion yang terbuka dari localStorage (grup dengan data-remember).
 * Sumber hulu  : localStorage dl-acc:<nama>.
 * Input        : Atribut data-remember dan data-acc-key.
 * Output       : Kelas is-open per panel.
 * Dipakai oleh : x-dl::accordion-group.
 * Status       : Aktif (DevLib).
 */
/* DevLib · AccGroup */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class AccGroup extends Devlib.Component {
    static selector = "[data-dl-accgroup]";
    init() {
      // Remembered panels (prop remember): restore which ones were open the last time.
      if (this.el.dataset.remember) {
        try {
          const keys = JSON.parse(localStorage.getItem('dl-acc:' + this.el.dataset.remember) || 'null');
          if (Array.isArray(keys)) Devlib.h.$$(this.el, ':scope > .dl-accordion').forEach(a => {
            const on = keys.includes(a.dataset.accKey); a.classList.toggle('is-open', on); const hd = $(a, '.dl-accordion__head'); if (hd) hd.setAttribute('aria-expanded', String(on));
          });
        } catch (_) {}
      }
      if (this.el.classList.contains('dl-accgroup--horizontal') && !$(this.el, ':scope > .dl-accordion.is-open')) { const a = $(this.el, ':scope > .dl-accordion'); if (a) a.classList.add('is-open'); }
    }
  }
  Devlib.define("AccGroup", AccGroup);
})(window.Devlib);
