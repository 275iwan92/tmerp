/* DevLib · Form */
(function (Devlib) {
  'use strict';
  const {$, $$, showQr, toast, confirmBox, layer, closeLayer, dirtyForms, fillForm, hasValue, T, Tf, esc} = Devlib.h;
  // Tombol tambahan form (mockup: "Generate QR Code Data") -> QR berisi semua isian form.
  class Form extends Devlib.Component {
    static selector = "[data-dl-form]";
    init() {
      this.fs = $$(this.el, '[data-f-fs]');  // Form panel: satu fieldset per bagian (tombol tab di luar fieldset)
      this.viewMode = this.el.dataset.mode === 'view';
      this.snapshot = null;
      this.saved = this.values();  // titik kembali Undo = isi saat form dibuka
      if (this.viewMode) this.setView(true);
      this.el.addEventListener('input', e => { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } }, true);
      this.el.addEventListener('submit', e => {
        const af = this.el.dataset.actionField || '_action';
        if (e.submitter?.name === af) $(this.el, `input[type=hidden][name="${af}"]`)?.remove();  // Save menang atas sisa aksi panel
        const n = this.validate();
        if (n) { e.preventDefault(); dirtyForms.add(this.el); this.el.classList.add('is-dirty'); toast(Tf('f_required_n', {n}), 'danger'); return; }
        if (this.el.getAttribute('action')) return;
        e.preventDefault();
        const data = {}; new FormData(this.el).forEach((v, k) => { if (k !== '_token') data[k] = v; });
        toast(Tf('f_action_preview', {a: this.el.querySelector('[data-dl-split-value]')?.value || 'Save'}), 'success');
        this.el.dispatchEvent(new CustomEvent('dl:form-submit', {bubbles: true, detail: data}));
        if (this.viewMode) { this.snapshot = null; this.setView(true); }
        const layer = this.el.closest('[data-dl-modal-id],[data-dl-drawer-id]'); if (layer) closeLayer(layer);
      });
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-f-edit]')) { this.fs.forEach(f => { f.disabled = false; }); this.snapshot = {}; new FormData(this.el).forEach((v, k) => { this.snapshot[k] = v; }); $$(this.el, 'input[type=checkbox][name]').forEach(c => { this.snapshot[c.name] = c.checked; }); this.setView(false); $(this.el, '.dl-form__body input:not([type=hidden]),.dl-form__body textarea')?.focus(); return; }
        const act = e.target.closest('[data-f-act]')?.dataset.fAct;
        if (act === 'back' && !e.target.closest('a[href]')) return history.back();
        if (act === 'new' && e.target.closest('a[href]')) return;
        if (act === 'undo') return this.undo();
        if (act && act !== 'save') return this.act(act);
        if (e.target.closest('[data-f-cancel]') && this.snapshot) { fillForm(this.el, this.snapshot, false); this.snapshot = null; this.setView(true); toast(T('f_changes_discarded')); }
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
    values() {
      const v = {}; new FormData(this.el).forEach((x, k) => { if (!(x instanceof File)) v[k] = x; });
      $$(this.el, 'input[type=checkbox][name]').forEach(c => { v[c.name] = c.checked; });
      return v;
    }

    // Undo = buang perubahan yang belum disimpan, kembali ke isi saat form dibuka (tanpa kirim ke server).
    undo() {
      if (!this.el.classList.contains('is-dirty')) return toast(T('f_nothing_undo'));
      confirmBox({message: T('f_discard_all'), tone: 'warn'}).then(ok => {
        if (!ok) return;
        fillForm(this.el, this.saved, false);
        $$(this.el, '.dl-field.is-invalid').forEach(f => f.classList.remove('is-invalid')); $$(this.el, '.dl-tabs__err').forEach(x => x.remove());
        dirtyForms.delete(this.el); this.el.classList.remove('is-dirty'); toast(T('f_changes_discarded'));
      });
    }

    // Tombol panel (selain Save = submit biasa). Ada action -> kirim ulang form dgn _action=<act>; tanpa action = pratinjau DevLib.
    act(a) {
      // [pesan, nada, alasan wajib?] -- Cancel, Reject, Back to Draft wajib alasan (tercatat di History).
      const ask = {delete: [T('f_ask_delete'), 'danger'], confirm: [T('f_ask_confirm'), 'warn'], cancel: [T('f_ask_cancel'), 'danger', true], draft: [T('f_ask_draft'), 'warn', true],
        approve: [T('f_ask_approve'), 'success'], reject: [T('f_ask_reject'), 'danger', true], hold: [T('f_ask_hold'), 'warn', true], deactivate: [T('f_ask_deactivate'), 'warn'], activate: [T('f_ask_activate'), 'warn']}[a];
      const go = why => {
        this.el.dispatchEvent(new CustomEvent('dl:form-action', {bubbles: true, detail: {action: a}}));
        // Print/Email tidak mengubah dokumen -> tidak kirim form (layar menangkap dl:form-action, mis. buka x-dl::doc-share).
        if (!this.el.getAttribute('action') || a === 'print' || a === 'email') return toast(Tf('f_action_preview2', {a}), 'success');
        const af = this.el.dataset.actionField || '_action';
        let h = $(this.el, `input[type=hidden][name="${af}"]`); if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = af; this.el.appendChild(h); }
        h.value = a;
        let r = $(this.el, 'input[name=_reason]'); if (!r) { r = document.createElement('input'); r.type = 'hidden'; r.name = '_reason'; this.el.appendChild(r); }
        r.value = typeof why === 'string' ? why : '';
        if (a === 'confirm') this.el.requestSubmit(); else HTMLFormElement.prototype.submit.call(this.el);  // hanya Confirm yang divalidasi
      };
      if (!ask) return go();
      confirmBox({message: ask[0], tone: ask[1], reason: !!ask[2]}).then(ok => { if (ok) go(ok); });
    }

    setView(on) { this.el.classList.toggle('is-view', on); this.fs.forEach(f => { f.disabled = on; }); const ed = $(this.el, '[data-f-edit]'); if (ed) ed.hidden = !on; }

    validate() {
      $$(this.el, '.dl-tabs__err').forEach(x => x.remove());
      const bad = $$(this.el, '.dl-field').filter(f => !f.closest('.dl-lines') && $(f, '.dl-field__req') && !hasValue(f));
      bad.forEach(f => { f.classList.add('is-invalid'); const er = $(f, '[data-dl-field-error]'); if (er) er.textContent = T('f_required'); });
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
