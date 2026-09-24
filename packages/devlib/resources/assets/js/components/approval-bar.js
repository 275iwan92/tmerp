/* DevLib · ApprovalBar */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, fmt, fieldErr, hasValue, at} = Devlib.h;
  /* ============================ APPROVAL BAR ============================ */
  class ApprovalBar extends Devlib.Component {
    static selector = "[data-dl-apbar]";
    init() {
      this.form = this.el.closest('form');
      this.fs = this.form && $(this.form, '[data-f-fs]');
      this.note = $(this.el, '[name=approval_note]');
      // Bilah dipindah ke luar fieldset form supaya tetap bisa diklik saat isian dikunci.
      if (this.fs && this.fs.contains(this.el)) this.fs.after(this.el);
      this.hist = JSON.parse(this.el.dataset.history || '[]');
      this.me = this.el.dataset.me;
      this.STEPS = [['DRAFT', 'Draft'], ['SUBMITTED', 'Diajukan'], ['APPROVED', 'Disetujui']];
      this.WORD = {submit: 'Ajukan', approve: 'Setujui', reject: 'Tolak', revise: 'Revisi'};
      this.NEXT = {submit: 'SUBMITTED', approve: 'APPROVED', reject: 'REJECTED', revise: 'DRAFT'};
      this.ICON = {SUBMITTED: 'fa-paper-plane', APPROVED: 'fa-check', REJECTED: 'fa-ban', DRAFT: 'fa-pen'};
      this.el.addEventListener('click', e => {
        const b = e.target.closest('[data-ab-do]'); if (!b) return;
        e.preventDefault();
        const a = b.dataset.abDo, n = this.note.value.trim();
        if (a === 'reject' && !n) { this.fieldErr(this.note, 'Alasan penolakan wajib diisi.'); this.note.focus(); return; }
        if (a === 'submit' && this.form) { const req = $$(this.form, '.dl-field').filter(f => !this.el.contains(f) && $(f, '.dl-field__req') && !hasValue(f)); if (req.length) { req.forEach(f => { f.classList.add('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = 'Wajib diisi'; }); toast(`${req.length} isian wajib belum diisi sebelum diajukan.`, 'danger'); return; } }
        confirmBox({message: `${this.WORD[a]} dokumen ini?`, tone: a === 'reject' ? 'danger' : 'warn'}).then(ok => {
          if (!ok) return;
          const status = this.NEXT[a];
          this.hist.push({status, label: {submit: 'DIAJUKAN', approve: 'DISETUJUI', reject: 'DITOLAK', revise: 'DIREVISI'}[a], by: a === 'approve' || a === 'reject' ? this.el.dataset.approver : this.me, at: `${fmt.date()} ${fmt.time()}`, note: n});
          this.el.dataset.status = status; this.note.value = ''; this.fieldErr(this.note, ''); this.draw();
          this.el.dispatchEvent(new CustomEvent('dl:approval-status', {bubbles: true, detail: {status, action: a, note: n}}));
          toast(`Dokumen ${status === 'DRAFT' ? 'kembali ke Draft' : {SUBMITTED: 'diajukan', APPROVED: 'disetujui', REJECTED: 'ditolak'}[status]}.`, a === 'reject' ? 'danger' : 'success');
        });
      });
      this.draw();
    }
    fieldErr(inp, msg) { const f = inp.closest('.dl-field'); f.classList.toggle('is-invalid', !!msg); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = msg; }

    draw() {
      const s = this.el.dataset.status, at = s === 'REJECTED' ? 1 : this.STEPS.findIndex(x => x[0] === s);
      $(this.el, '[data-ab-pipe]').innerHTML = this.STEPS.map(([k, l], i) => `<span class="dl-master__step ${i < at ? 'is-done' : i === at ? (s === 'REJECTED' ? 'is-rejected' : 'is-active') : ''}">${i === at && s === 'REJECTED' ? 'Ditolak' : esc(l)}</span>`).join('');
      const last = this.hist[this.hist.length - 1];
      $(this.el, '[data-ab-banner]').innerHTML = s === 'APPROVED' ? `<i class="fa-solid fa-lock"></i> Disetujui${last ? ` oleh <b>${esc(last.by)}</b> · ${esc(last.at)}` : ''} — dokumen terkunci.` : s === 'SUBMITTED' ? '<i class="fa-solid fa-hourglass-half"></i> Menunggu persetujuan — isian dikunci.' : s === 'REJECTED' ? `<i class="fa-solid fa-circle-xmark"></i> Ditolak${last?.note ? `: “${esc(last.note)}”` : ''} — klik Revisi untuk memperbaiki.` : '';
      this.el.dataset.tone = s;
      const show = {submit: s === 'DRAFT', approve: s === 'SUBMITTED', reject: s === 'SUBMITTED', revise: s === 'REJECTED'};
      $$(this.el, '[data-ab-do]').forEach(b => { b.hidden = !show[b.dataset.abDo]; });
      $(this.el, '[data-ab-act]').hidden = s === 'APPROVED';
      this.note.closest('.dl-field').hidden = s !== 'SUBMITTED';
      $(this.el, '[data-ab-hist]').innerHTML = this.hist.slice().reverse().map(h => `<li><i class="fa-solid ${this.ICON[h.status] || 'fa-circle'}"></i><div><b>${esc(h.label)}</b><small>Oleh: ${esc(h.by)} · ${esc(h.at)}</small>${h.note ? `<q>${esc(h.note)}</q>` : ''}</div></li>`).join('');
      if (this.fs) { this.fs.disabled = s !== 'DRAFT'; this.form.classList.toggle('is-view', s !== 'DRAFT'); }
    }
  }
  Devlib.define("ApprovalBar", ApprovalBar);
})(window.Devlib);
