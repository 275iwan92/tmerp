/* MockupParts · Sales · ProductTree — klik node pohon product -> panel node yang sama tampil. */
(function (Devlib) {
  'use strict';
  class ProductTree extends Devlib.Component {
    init() {
      this.on('click', '[data-pm-node]', (e, b) => {
        this.el.querySelectorAll('[data-pm-node]').forEach(x => x.classList.toggle('is-on', x === b));
        this.el.querySelectorAll('[data-pm-panel]').forEach(p => { p.hidden = p.dataset.pmPanel !== b.dataset.pmNode; });
        const d = this.el.querySelector(`[data-pm-panel="${b.dataset.pmNode}"] [data-dl-dieline-x]`);
        if (d && d.dlDielineX) d.dlDielineX.redraw();
      });
    }
  }
  Devlib.register(ProductTree);
})(window.Devlib);
