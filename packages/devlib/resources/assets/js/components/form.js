/* DevLib · Form */
(function (Devlib) {
  'use strict';
  const {$, $$, showQr, toast, layer, closeLayer, dirtyForms, fillForm, hasValue} = Devlib.h;
  // Tombol tambahan form (mockup: "Generate QR Code Data") -> QR berisi semua isian form.
  class Form extends Devlib.Component {
    static selector = "[data-dl-form]";
    init() {
      this.fs = $(this.el, '[data-f-fs]');
      this.viewMode = this.el.dataset.mode === 'view';
      this.snapshot = null;
      if (this.viewMode) this.setView(true);
      this.el.addEventListener('input', e => { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } }, true);
      this.el.addEventListener('submit', e => {
        const n = this.validate();
        if (n) { e.preventDefault(); dirtyForms.add(this.el); this.el.classList.add('is-dirty'); toast(`${n} isian wajib belum diisi.`, 'danger'); return; }
        if (this.el.getAttribute('action')) return;
        e.preventDefault();
        const data = {}; new FormData(this.el).forEach((v, k) => { if (k !== '_token') data[k] = v; });
        toast('Aksi: ' + (this.el.querySelector('[data-dl-split-value]')?.value || 'Simpan') + ' (pratinjau, tidak dikirim)', 'success');
        this.el.dispatchEvent(new CustomEvent('dl:form-submit', {bubbles: true, detail: data}));
        if (this.viewMode) { this.snapshot = null; this.setView(true); }
        const layer = this.el.closest('[data-dl-modal-id],[data-dl-drawer-id]'); if (layer) closeLayer(layer);
      });
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-f-edit]')) { if (this.fs) this.fs.disabled = false; this.snapshot = {}; new FormData(this.el).forEach((v, k) => { this.snapshot[k] = v; }); $$(this.el, 'input[type=checkbox][name]').forEach(c => { this.snapshot[c.name] = c.checked; }); this.setView(false); $(this.el, '.dl-form__body input:not([type=hidden]),.dl-form__body textarea')?.focus(); return; }
        if (e.target.closest('[data-f-cancel]') && this.snapshot) { fillForm(this.el, this.snapshot, false); this.snapshot = null; this.setView(true); toast('Perubahan dibatalkan.'); }
      });
      this.extra = this.el.querySelector('[data-dl-form-extra]');
      if (!this.extra) return;
      this.extra.addEventListener('click', () => {
        const data = {};
        new FormData(this.el).forEach((v, k) => {
          if (k === '_token' || k === '_action' || v instanceof File) return;
          if (/password/i.test(k)) v = v ? '********' : '-';
          k = k.replace(/\[\]$/, '');
          data[k] = k in data ? [].concat(data[k], v) : v;
        });
        showQr(JSON.stringify(data, null, 2));
      });
    }
    setView(on) { this.el.classList.toggle('is-view', on); if (this.fs) this.fs.disabled = on; const ed = $(this.el, '[data-f-edit]'); if (ed) ed.hidden = !on; }

    validate() {
      $$(this.el, '.dl-tabs__err').forEach(x => x.remove());
      const bad = $$(this.el, '.dl-field').filter(f => !f.closest('.dl-lines') && $(f, '.dl-field__req') && !hasValue(f));
      bad.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = 'Wajib diisi'; });
      const lineBad = $$(this.el, '[data-dl-lines]').reduce((n, l) => n + (l.dlLines ? l.dlLines.validate() : 0), 0);
      const panels = new Map();
      [...bad, ...$$(this.el, '.dl-lines td.is-invalid')].forEach(x => { const p = x.closest('.dl-tabs__panel'); if (p) panels.set(p, (panels.get(p) || 0) + 1); });
      panels.forEach((n, p) => { const tab = $(p.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${p.dataset.panel}"]`); if (tab) tab.insertAdjacentHTML('beforeend', `<span class="dl-tabs__err">${n}</span>`); });
      const first = bad[0] || $(this.el, '.dl-lines td.is-invalid');
      if (first) { const p = first.closest('.dl-tabs__panel'); if (p && !p.classList.contains('is-active')) $(p.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${p.dataset.panel}"]`)?.click(); setTimeout(() => { first.scrollIntoView({block: 'center'}); $(first, 'input:not([type=hidden]),textarea,button')?.focus(); }, 30); }
      return bad.length + lineBad;
    }
  }
  Devlib.define("Form", Form);
})(window.Devlib);
