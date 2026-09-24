/* DevLib · Drill */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, layer} = Devlib.h;
  /* ============================ DRILLDOWN ============================ */
  class Drill extends Devlib.Component {
    static selector = "[data-dl-drill]";
    init() {
      const el = this.el;
      const [sumEl, detEl] = $$(el, '[data-dl-grid]'), sumBox = $(el, '[data-dd-summary]'), detBox = $(el, '[data-dd-detail]'), crumb = $(el, '[data-dd-crumb]');
      if (!sumEl || !detEl) return;
      const all = [...detEl.dlGrid.rows], link = el.dataset.link;
      const home = () => { sumBox.hidden = false; detBox.hidden = true; crumb.innerHTML = `<b><i class="fa-solid fa-layer-group"></i> ${esc(el.dataset.title)}</b><small>Klik 2x baris untuk melihat rinciannya</small>`; };
      sumEl.addEventListener('dl:grid-open', e => {
        e.preventDefault(); e.stopPropagation();
        const g = sumEl.dlGrid, row = g.rows.find(r => String(r[g.cfg.rowKey]) === String(e.detail.id)); if (!row) return;
        const list = all.filter(r => String(r[link]) === String(row[g.cfg.rowKey]));
        detEl.dlGrid.setRows(list);
        sumBox.hidden = true; detBox.hidden = false;
        crumb.innerHTML = `<button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-dd-back><i class="fa-solid fa-arrow-left"></i> ${esc(el.dataset.title)}</button><i class="fa-solid fa-chevron-right"></i><b>${esc(row[el.dataset.label] ?? e.detail.id)}</b><small>${list.length} baris rincian</small>`;
      });
      el.addEventListener('click', e => { if (e.target.closest('[data-dd-back]')) home(); });
      home();
    }
  }
  Devlib.define("Drill", Drill);
})(window.Devlib);
