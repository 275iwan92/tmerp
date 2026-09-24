/* DevLib · Drill */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, layer} = Devlib.h;
  /* ============================ DRILLDOWN ============================ */
  class Drill extends Devlib.Component {
    static selector = "[data-dl-drill]";
    init() {
      [this.sumEl, this.detEl] = $$(this.el, '[data-dl-grid]');
      this.sumBox = $(this.el, '[data-dd-summary]');
      this.detBox = $(this.el, '[data-dd-detail]');
      this.crumb = $(this.el, '[data-dd-crumb]');
      if (!this.sumEl || !this.detEl) return;
      this.all = [...this.detEl.dlGrid.rows];
      this.link = this.el.dataset.link;
      this.sumEl.addEventListener('dl:grid-open', e => {
        e.preventDefault(); e.stopPropagation();
        const g = this.sumEl.dlGrid, row = g.rows.find(r => String(r[g.cfg.rowKey]) === String(e.detail.id)); if (!row) return;
        const list = this.all.filter(r => String(r[this.link]) === String(row[g.cfg.rowKey]));
        this.detEl.dlGrid.setRows(list);
        this.sumBox.hidden = true; this.detBox.hidden = false;
        this.crumb.innerHTML = `<button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-dd-back><i class="fa-solid fa-arrow-left"></i> ${esc(this.el.dataset.title)}</button><i class="fa-solid fa-chevron-right"></i><b>${esc(row[this.el.dataset.label] ?? e.detail.id)}</b><small>${list.length} baris rincian</small>`;
      });
      this.el.addEventListener('click', e => { if (e.target.closest('[data-dd-back]')) this.home(); });
      this.home();
    }
    home() { this.sumBox.hidden = false; this.detBox.hidden = true; this.crumb.innerHTML = `<b><i class="fa-solid fa-layer-group"></i> ${esc(this.el.dataset.title)}</b><small>Klik 2x baris untuk melihat rinciannya</small>`; }
  }
  Devlib.define("Drill", Drill);
})(window.Devlib);
