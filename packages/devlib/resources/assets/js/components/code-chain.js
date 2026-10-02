/* DevLib · CodeChain — dropdown saling terkait yang menyusun kode (x-dl::code-chain) */
(function (Devlib) {
  'use strict';
  const {$, $$, setOptions} = Devlib.h;

  class CodeChain extends Devlib.Component {
    static selector = "[data-dl-chain]";
    init() {
      const d = this.el.dataset;
      this.name = d.name; this.n = +d.levels || 0;
      this.nodes = JSON.parse(d.nodes || '[]');            // [id, induk, level, label, potongan kode]
      this.byId = new Map(this.nodes.map(n => [String(n[0]), n]));
      this.el.addEventListener('change', e => {
        const m = e.target.name && e.target.name.startsWith(this.name + '_') ? +e.target.name.slice(this.name.length + 1) : 0;
        if (m >= 1 && m <= this.n) this.pick(m, e.target.value);
      });
    }
    input(level) { return $(this.el, `[name="${CSS.escape(this.name + '_' + level)}"]`); }
    val(level) { const i = this.input(level); return i ? i.value : ''; }
    children(parent, level) { return this.nodes.filter(n => String(n[1]) === String(parent) && n[2] === level); }
    pick(level, id) {
      const d = this.el.dataset;
      // Layar master: tiap pilihan membuka baris itu (dikosongkan = kembali ke tingkat di atasnya).
      if (d.navigate) { location.href = d.navigate.replace('{id}', encodeURIComponent(id || (level > 1 ? this.val(level - 1) : ''))); return; }
      for (let l = level + 1; l <= this.n; l++) {
        const rows = l === level + 1 && id ? this.children(id, l) : [];
        setOptions(this.el, this.name + '_' + l, [['', d.tChoose], ...rows.map(n => [n[0], n[3]])], {disabled: !rows.length});
      }
      this.paint();
    }
    paint() {
      const d = this.el.dataset, ids = [];
      for (let l = 1; l <= this.n; l++) { const v = this.val(l); if (!v) break; ids.push(v); }
      const segs = ids.map(id => (this.byId.get(String(id)) || [])[4] || '');
      $$(this.el, '[data-cc-level]').forEach(col => {
        const l = +col.dataset.ccLevel, seg = $(col, '[data-cc-seg]'), add = $(col, '[data-cc-add]');
        if (seg) seg.textContent = segs[l - 1] || '';
        if (add) {
          const open = l === 1 || ids.length >= l - 1;
          add.classList.toggle('is-disabled', !open);
          if (open) add.href = d.add.replace('{level}', l).replace('{parent}', ids[l - 2] || ''); else add.removeAttribute('href');
        }
      });
      const code = segs.join('');
      $(this.el, '[data-cc-code]').textContent = code || '—';
      $(this.el, '[data-cc-hint]').textContent = !ids.length ? d.tFirst : ids.length === this.n ? d.tComplete : d.tLevel.replace(':n', ids.length).replace(':of', this.n);
      $(this.el, '[data-cc-value]').value = ids[ids.length - 1] || '';
      $(this.el, '[data-cc-codeval]').value = code;
      this.emit('dl:chain-change', {ids, code, complete: ids.length === this.n});
    }
  }
  Devlib.define("CodeChain", CodeChain);
})(window.Devlib);
