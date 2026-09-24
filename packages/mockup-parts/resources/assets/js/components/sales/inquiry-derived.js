/* MockupParts · Sales · InquiryDerived (+ InquiryRevise, InquiryRecalc) — dokumen Inquiry turunan inkuiri lain.
   Sama dengan tmerp revise.blade.php / recalc.blade.php: Simpan = DRAFT, Submit ke Costing = SUBMITTED + terkunci. */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, fieldErr} = Devlib.h;

  class InquiryDerived extends Devlib.classes.InquiryDoc {
    static selector = '[data-mp-inquiry-derived]';

    init() {
      super.init();
      this.on('click', '[data-inq-save]', () => this.save());
      this.on('click', '[data-inq-submit]', () => this.submit());
      this.summary();
      if (this.cfg.readonly || this.doc.stat_confirm) this.lockAll();
    }

    validate() { return true; }
    summaryText() { return ''; }
    summary() { $(this.el, '[data-inq-summary]').innerHTML = this.summaryText(); }

    save() {
      if (!this.validate()) return;
      this.number(); this.summary();
      this.flash(`Tersimpan sebagai ${this.doc.inquiry_no} (DRAFT).`);
    }

    submit() {
      if (!this.validate()) return;
      this.number(); this.doc.stat_confirm = true; this.state('SUBMITTED'); this.lockAll(); this.summary();
      this.flash(`Inkuiri ${this.doc.inquiry_no} dikirim ke Costing — status SUBMITTED, form terkunci.`);
    }

    lockAll() { super.lockAll(); this.locked = true; }
  }
  Devlib.define('InquiryDerived', InquiryDerived);

  // REVISE: scope menentukan blok edit yang terbuka & konsekuensi (imposisi/proof/costing/due-date); wajib scope + alasan.
  class InquiryRevise extends InquiryDerived {
    init() {
      this.on('click', '[data-scope]', (e, opt) => { e.preventDefault(); if (!this.locked) this.toggle(opt.dataset.scope); });
      super.init();
      this.apply();
    }

    scope() { return (this.value('revise_scope') || '').split(',').filter(Boolean); }

    toggle(key) {
      const s = this.scope(), next = s.includes(key) ? s.filter(k => k !== key) : [...s, key];
      this.input('revise_scope').value = next.join(',');
      this.apply();
    }

    apply() {
      const s = this.scope();
      $$(this.el, '[data-scope]').forEach(o => { const on = s.includes(o.dataset.scope); o.classList.toggle('is-on', on); $(o, 'input').checked = on; });
      $$(this.el, '[data-scope-block]').forEach(b => {
        const was = b.hidden; b.hidden = !s.includes(b.dataset.scopeBlock);
        if (was && !b.hidden) $$(b, '[data-dl-dieline-x]').forEach(d => d.dlDielineX && d.dlDielineX.redraw());
      });
      const box = $(this.el, '[data-impact-rules]');
      if (box) Object.entries(JSON.parse(box.dataset.impactRules)).forEach(([k, r]) => {
        const row = $(box, `[data-impact="${k}"]`), hit = r.on.some(x => s.includes(x)), flag = $(row, '[data-impact-flag]');
        flag.textContent = hit ? 'YA' : 'TIDAK'; flag.className = `dl-badge dl-badge--pill dl-badge--${hit ? 'warn' : 'neutral'}`;
        $(row, '[data-impact-why]').textContent = hit ? r.yes : r.no;
      });
      $(this.el, '[data-scope-err]')?.classList.remove('is-show');
      this.summary();
    }

    summaryText() { return `Scope: <b>${esc(this.scope().join(', ') || '(belum ada)')}</b> · Doc <b>${esc(this.doc.inquiry_no || '(draft baru)')}</b>`; }

    validate() {
      let bad = 0;
      const err = $(this.el, '[data-scope-err]');
      if (!this.scope().length) { err.textContent = 'Scope revisi wajib dipilih minimal satu.'; err.classList.add('is-show'); bad++; }
      const r = this.input('revise_reason'); fieldErr(r, this.value('revise_reason') ? '' : 'Alasan revisi wajib diisi.'); bad += this.value('revise_reason') ? 0 : 1;
      if (bad) toast(`${bad} isian belum benar.`, 'danger');
      return !bad;
    }
  }
  Devlib.register(InquiryRevise);

  // RECALC: spec & layout 100% reuse (terkunci), yang diisi hanya Qty Order & Deadline Quotation.
  class InquiryRecalc extends InquiryDerived {
    summaryText() { return `Layout reuse · Doc <b>${esc(this.doc.inquiry_no || '(draft baru)')}</b>`; }

    validate() {
      const q = +String(this.value('qty')).replace(/\./g, '').replace(',', '.');
      fieldErr(this.input('qty'), q > 0 ? '' : 'Qty order wajib diisi angka lebih besar dari 0.');
      if (!(q > 0)) toast('Qty order belum benar.', 'danger');
      return q > 0;
    }
  }
  Devlib.register(InquiryRecalc);
})(window.Devlib);
