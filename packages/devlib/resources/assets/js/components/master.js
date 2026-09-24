/* DevLib · Master */
(function (Devlib) {
  'use strict';
  const {$, $$, toast, dirtyForms, fillForm, hasValue, at} = Devlib.h;

  class Master extends Devlib.Component {
    static selector = "[data-dl-master]";
    init() {
      this.grid = $(this.el, '[data-dl-grid]');
      this.form = $(this.el, '[data-m-form]');
      this.gridBox = $(this.el, '[data-m-grid]');
      this.d = this.el.dataset;
      this.cur = null;
      this.el.addEventListener('dl:grid-open', e => { e.preventDefault(); this.open(e.detail.id); });
      this.el.addEventListener('dl:grid-new', e => { e.preventDefault(); this.open(null); });
      $$(this.form, '[data-m-back]').forEach(b => b.addEventListener('click', () => this.show(false)));
      this.form.addEventListener('submit', e => {
        e.preventDefault();
        const missing = $$(this.form, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
        missing.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = 'Wajib diisi'; });
        const lines = $$(this.form, '[data-dl-lines]'), lineBad = lines.reduce((n, l) => n + l.dlLines.validate(), 0);
        if (missing.length || lineBad) { dirtyForms.add(this.form); toast(`${missing.length + lineBad} isian wajib belum diisi.`, 'danger'); (missing[0] || $(this.form, '.dl-lines td.is-invalid, .dl-lines__err:not(:empty)'))?.scrollIntoView({block: 'center'}); return; }
        const data = {};
        new FormData(this.form).forEach((v, k) => { if (k !== '_token' && !k.includes('[')) data[k] = v; });
        lines.forEach(l => { data[l.dataset.name] = l.dlLines.rows; if (l.dataset.totalKey) data[l.dataset.totalKey] = l.dlLines.total().grand; if (l.dataset.countKey) data[l.dataset.countKey] = l.dlLines.rows.length; });
        $$(this.form, 'input[type=checkbox][name]').forEach(c => { data[c.name] = c.checked; });
        const g = this.grid.dlGrid, isNew = !this.cur;
        if (isNew) {
          this.cur = Object.assign({[g.cfg.rowKey]: Math.max(0, ...g.rows.map(r => +r[g.cfg.rowKey] || 0)) + 1}, g.cfg.activeKey ? {[g.cfg.activeKey]: true} : {}, data);
          g.rows.unshift(this.cur);
        } else Object.assign(this.cur, data);
        g.redraw();
        toast(`${this.d.title} ${this.cur[this.d.nameKey] ?? ''} ${isNew ? 'ditambahkan' : 'disimpan'}.`, 'success');
        this.el.dispatchEvent(new CustomEvent('dl:master-save', {bubbles: true, detail: {record: this.cur, isNew}}));
        this.show(false);
      });
    }
    show(onForm) { this.gridBox.hidden = onForm; this.form.hidden = !onForm; this.el.scrollIntoView({block: 'start'}); }

    open(id) {
      const g = this.grid.dlGrid;
      this.cur = id == null ? null : g.rows.find(r => String(r[g.cfg.rowKey]) === String(id));
      fillForm(this.form, this.cur || {}, true);
      $(this.form, '[data-m-heading]').textContent = this.cur ? `Edit ${this.d.title} · ${this.cur[this.d.nameKey] ?? ''}` : `${this.d.title} baru`;
      $(this.form, '[data-m-sub]').textContent = this.cur ? (this.d.codeKey ? this.cur[this.d.codeKey] ?? '' : '') : 'Belum disimpan';
      const pipe = $(this.form, '[data-m-pipe]');
      if (pipe) { const steps = $$(pipe, '[data-step]'), at = this.cur ? steps.findIndex(s => s.dataset.step === String(this.cur[this.d.pipelineKey])) : 0; steps.forEach((s, i) => { s.classList.toggle('is-done', i < at); s.classList.toggle('is-active', i === at); }); }
      this.show(true);
      const first = $(this.form, '.dl-master__body input:not([type=hidden]):not([readonly]), .dl-master__body textarea'); if (first) first.focus({preventScroll: true});
    }
  }
  Devlib.define("Master", Master);
})(window.Devlib);
