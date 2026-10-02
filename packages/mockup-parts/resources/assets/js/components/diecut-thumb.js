/* MockupParts · DiecutThumb — gambar kecil diecut dari dieline_spec (TMDieline.svg, baca-saja). */
(function (Devlib) {
  'use strict';
  class DiecutThumb extends Devlib.Component {
    static selector = '[data-mp-diecut]';
    init() {
      const spec = JSON.parse(this.el.dataset.spec || 'null');
      if (!spec || !spec.elements || !window.TMDieline) return;
      try { this.el.innerHTML = window.TMDieline.svg(spec, JSON.parse(this.el.dataset.dims || '{}')); } catch (e) { this.el.textContent = Devlib.h.t('Failed to load.'); }
    }
  }
  Devlib.define('DiecutThumb', DiecutThumb);

  // Teks bentangan "W × H mm" dari dieline_spec + dims (TMDieline.flat, rumus sama dengan Dieline).
  class FlatSize extends Devlib.Component {
    static selector = '[data-mp-flat]';
    init() {
      const spec = JSON.parse(this.el.dataset.spec || 'null');
      if (!spec || !window.TMDieline) return;
      const f = window.TMDieline.flat(spec, JSON.parse(this.el.dataset.dims || '{}')), n = v => (+v).toLocaleString('id-ID', {maximumFractionDigits: 2});
      this.el.textContent = f && f.w ? `${n(f.w)} × ${n(f.h)} mm` : '—';
    }
  }
  Devlib.define('FlatSize', FlatSize);
})(window.Devlib);
