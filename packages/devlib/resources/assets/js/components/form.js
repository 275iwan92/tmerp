/* DevLib · Form */
(function (Devlib) {
  'use strict';
  const {$, $$, showQr, toast, layer, closeLayer, dirtyForms, fillForm, hasValue} = Devlib.h;
  // Tombol tambahan form (mockup: "Generate QR Code Data") -> QR berisi semua isian form.
  class Form extends Devlib.Component {
    static selector = "[data-dl-form]";
    init() {
      const form = this.el;
      const fs = $(form, '[data-f-fs]'), viewMode = form.dataset.mode === 'view';
      let snapshot = null;
      const setView = on => { form.classList.toggle('is-view', on); if (fs) fs.disabled = on; const ed = $(form, '[data-f-edit]'); if (ed) ed.hidden = !on; };
      if (viewMode) setView(true);
      // Isian wajib + baris detail; error di tab tersembunyi -> tab dibuka & diberi tanda jumlah error.
      const validate = () => {
        $$(form, '.dl-tabs__err').forEach(x => x.remove());
        const bad = $$(form, '.dl-field').filter(f => !f.closest('.dl-lines') && $(f, '.dl-field__req') && !hasValue(f));
        bad.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = 'Wajib diisi'; });
        const lineBad = $$(form, '[data-dl-lines]').reduce((n, l) => n + (l.dlLines ? l.dlLines.validate() : 0), 0);
        const panels = new Map();
        [...bad, ...$$(form, '.dl-lines td.is-invalid')].forEach(x => { const p = x.closest('.dl-tabs__panel'); if (p) panels.set(p, (panels.get(p) || 0) + 1); });
        panels.forEach((n, p) => { const tab = $(p.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${p.dataset.panel}"]`); if (tab) tab.insertAdjacentHTML('beforeend', `<span class="dl-tabs__err">${n}</span>`); });
        const first = bad[0] || $(form, '.dl-lines td.is-invalid');
        if (first) { const p = first.closest('.dl-tabs__panel'); if (p && !p.classList.contains('is-active')) $(p.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${p.dataset.panel}"]`)?.click(); setTimeout(() => { first.scrollIntoView({block: 'center'}); $(first, 'input:not([type=hidden]),textarea,button')?.focus(); }, 30); }
        return bad.length + lineBad;
      };
      form.addEventListener('input', e => { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } }, true);
      form.addEventListener('submit', e => {
        const n = validate();
        if (n) { e.preventDefault(); dirtyForms.add(form); form.classList.add('is-dirty'); toast(`${n} isian wajib belum diisi.`, 'danger'); return; }
        if (form.getAttribute('action')) return;
        e.preventDefault();
        const data = {}; new FormData(form).forEach((v, k) => { if (k !== '_token') data[k] = v; });
        toast('Aksi: ' + (form.querySelector('[data-dl-split-value]')?.value || 'Simpan') + ' (pratinjau, tidak dikirim)', 'success');
        form.dispatchEvent(new CustomEvent('dl:form-submit', {bubbles: true, detail: data}));
        if (viewMode) { snapshot = null; setView(true); }
        const layer = form.closest('[data-dl-modal-id],[data-dl-drawer-id]'); if (layer) closeLayer(layer);
      });
      form.addEventListener('click', e => {
        if (e.target.closest('[data-f-edit]')) { if (fs) fs.disabled = false; snapshot = {}; new FormData(form).forEach((v, k) => { snapshot[k] = v; }); $$(form, 'input[type=checkbox][name]').forEach(c => { snapshot[c.name] = c.checked; }); setView(false); $(form, '.dl-form__body input:not([type=hidden]),.dl-form__body textarea')?.focus(); return; }
        if (e.target.closest('[data-f-cancel]') && snapshot) { fillForm(form, snapshot, false); snapshot = null; setView(true); toast('Perubahan dibatalkan.'); }
      });
      const extra = form.querySelector('[data-dl-form-extra]');
      if (!extra) return;
      extra.addEventListener('click', () => {
        const data = {};
        new FormData(form).forEach((v, k) => {
          if (k === '_token' || k === '_action' || v instanceof File) return;
          if (/password/i.test(k)) v = v ? '********' : '-';
          k = k.replace(/\[\]$/, '');
          data[k] = k in data ? [].concat(data[k], v) : v;
        });
        showQr(JSON.stringify(data, null, 2));
      });
    }
  }
  Devlib.define("Form", Form);
})(window.Devlib);
