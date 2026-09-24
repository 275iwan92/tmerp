/* DevLib · Master */
(function (Devlib) {
  'use strict';
  const {$, $$, toast, dirtyForms, fillForm, hasValue, at} = Devlib.h;

  class Master extends Devlib.Component {
    static selector = "[data-dl-master]";
    init() {
      const m = this.el;
      const grid = $(m, '[data-dl-grid]'), form = $(m, '[data-m-form]'), gridBox = $(m, '[data-m-grid]'), d = m.dataset;
      let cur = null;
      const show = onForm => { gridBox.hidden = onForm; form.hidden = !onForm; m.scrollIntoView({block: 'start'}); };
      const open = id => {
        const g = grid.dlGrid;
        cur = id == null ? null : g.rows.find(r => String(r[g.cfg.rowKey]) === String(id));
        fillForm(form, cur || {}, true);
        $(form, '[data-m-heading]').textContent = cur ? `Edit ${d.title} · ${cur[d.nameKey] ?? ''}` : `${d.title} baru`;
        $(form, '[data-m-sub]').textContent = cur ? (d.codeKey ? cur[d.codeKey] ?? '' : '') : 'Belum disimpan';
        const pipe = $(form, '[data-m-pipe]');
        if (pipe) { const steps = $$(pipe, '[data-step]'), at = cur ? steps.findIndex(s => s.dataset.step === String(cur[d.pipelineKey])) : 0; steps.forEach((s, i) => { s.classList.toggle('is-done', i < at); s.classList.toggle('is-active', i === at); }); }
        show(true);
        const first = $(form, '.dl-master__body input:not([type=hidden]):not([readonly]), .dl-master__body textarea'); if (first) first.focus({preventScroll: true});
      };
      m.addEventListener('dl:grid-open', e => { e.preventDefault(); open(e.detail.id); });
      m.addEventListener('dl:grid-new', e => { e.preventDefault(); open(null); });
      $$(form, '[data-m-back]').forEach(b => b.addEventListener('click', () => show(false)));
      form.addEventListener('submit', e => {
        e.preventDefault();
        const missing = $$(form, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
        missing.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = 'Wajib diisi'; });
        const lines = $$(form, '[data-dl-lines]'), lineBad = lines.reduce((n, l) => n + l.dlLines.validate(), 0);
        if (missing.length || lineBad) { dirtyForms.add(form); toast(`${missing.length + lineBad} isian wajib belum diisi.`, 'danger'); (missing[0] || $(form, '.dl-lines td.is-invalid, .dl-lines__err:not(:empty)'))?.scrollIntoView({block: 'center'}); return; }
        const data = {};
        new FormData(form).forEach((v, k) => { if (k !== '_token' && !k.includes('[')) data[k] = v; });
        lines.forEach(l => { data[l.dataset.name] = l.dlLines.rows; if (l.dataset.totalKey) data[l.dataset.totalKey] = l.dlLines.total().grand; if (l.dataset.countKey) data[l.dataset.countKey] = l.dlLines.rows.length; });
        $$(form, 'input[type=checkbox][name]').forEach(c => { data[c.name] = c.checked; });
        const g = grid.dlGrid, isNew = !cur;
        if (isNew) {
          cur = Object.assign({[g.cfg.rowKey]: Math.max(0, ...g.rows.map(r => +r[g.cfg.rowKey] || 0)) + 1}, g.cfg.activeKey ? {[g.cfg.activeKey]: true} : {}, data);
          g.rows.unshift(cur);
        } else Object.assign(cur, data);
        g.redraw();
        toast(`${d.title} ${cur[d.nameKey] ?? ''} ${isNew ? 'ditambahkan' : 'disimpan'}.`, 'success');
        m.dispatchEvent(new CustomEvent('dl:master-save', {bubbles: true, detail: {record: cur, isNew}}));
        show(false);
      });
    }
  }
  Devlib.define("Master", Master);
})(window.Devlib);
