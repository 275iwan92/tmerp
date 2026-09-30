/* DevLib · PickMatrix */
(function (Devlib) {
  'use strict';
  const {$, $$, esc} = Devlib.h;
  // Ringkasan pilihan per kolom: "2 PO: A (2 baris), B (1 baris)" -- dihitung ulang tiap radio berubah.
  class PickMatrix extends Devlib.Component {
    static selector = "[data-dl-pick]";
    init() {
      this.cols = JSON.parse(this.el.dataset.cols || '{}');
      this.el.addEventListener('change', () => this.sum());
      this.sum();
    }
    sum() {
      const box = $(this.el, '[data-dl-pick-sum]'); if (!box) return;
      const n = {};
      $$(this.el, 'input[type=radio]:checked').forEach(r => { if (r.dataset.col) n[r.dataset.col] = (n[r.dataset.col] || 0) + 1; });
      const keys = Object.keys(n);
      box.innerHTML = keys.length
        ? `<b>${keys.length} ${esc(this.el.dataset.unit)}</b>: ${keys.map(k => `${esc(this.cols[k] ?? k)} (${n[k]} baris)`).join(', ')}`
        : 'Belum ada baris yang dipilih.';
    }
  }
  Devlib.define("PickMatrix", PickMatrix);
})(window.Devlib);
