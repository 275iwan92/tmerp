/* DevLib · ApprovalBar */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, fmt, fieldErr, hasValue, at} = Devlib.h;
  /* ============================ APPROVAL BAR ============================ */
  class ApprovalBar extends Devlib.Component {
    static selector = "[data-dl-apbar]";
    init() {
      const el = this.el;
      const fieldErr = (inp, msg) => { const f = inp.closest('.dl-field'); f.classList.toggle('is-invalid', !!msg); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = msg; };
      const form = el.closest('form'), fs = form && $(form, '[data-f-fs]'), note = $(el, '[name=approval_note]');
      // Bilah dipindah ke luar fieldset form supaya tetap bisa diklik saat isian dikunci.
      if (fs && fs.contains(el)) fs.after(el);
      const hist = JSON.parse(el.dataset.history || '[]'), me = el.dataset.me;
      const STEPS = [['DRAFT', 'Draft'], ['SUBMITTED', 'Diajukan'], ['APPROVED', 'Disetujui']];
      const WORD = {submit: 'Ajukan', approve: 'Setujui', reject: 'Tolak', revise: 'Revisi'}, NEXT = {submit: 'SUBMITTED', approve: 'APPROVED', reject: 'REJECTED', revise: 'DRAFT'};
      const ICON = {SUBMITTED: 'fa-paper-plane', APPROVED: 'fa-check', REJECTED: 'fa-ban', DRAFT: 'fa-pen'};
      function draw() {
        const s = el.dataset.status, at = s === 'REJECTED' ? 1 : STEPS.findIndex(x => x[0] === s);
        $(el, '[data-ab-pipe]').innerHTML = STEPS.map(([k, l], i) => `<span class="dl-master__step ${i < at ? 'is-done' : i === at ? (s === 'REJECTED' ? 'is-rejected' : 'is-active') : ''}">${i === at && s === 'REJECTED' ? 'Ditolak' : esc(l)}</span>`).join('');
        const last = hist[hist.length - 1];
        $(el, '[data-ab-banner]').innerHTML = s === 'APPROVED' ? `<i class="fa-solid fa-lock"></i> Disetujui${last ? ` oleh <b>${esc(last.by)}</b> · ${esc(last.at)}` : ''} — dokumen terkunci.` : s === 'SUBMITTED' ? '<i class="fa-solid fa-hourglass-half"></i> Menunggu persetujuan — isian dikunci.' : s === 'REJECTED' ? `<i class="fa-solid fa-circle-xmark"></i> Ditolak${last?.note ? `: “${esc(last.note)}”` : ''} — klik Revisi untuk memperbaiki.` : '';
        el.dataset.tone = s;
        const show = {submit: s === 'DRAFT', approve: s === 'SUBMITTED', reject: s === 'SUBMITTED', revise: s === 'REJECTED'};
        $$(el, '[data-ab-do]').forEach(b => { b.hidden = !show[b.dataset.abDo]; });
        $(el, '[data-ab-act]').hidden = s === 'APPROVED';
        note.closest('.dl-field').hidden = s !== 'SUBMITTED';
        $(el, '[data-ab-hist]').innerHTML = hist.slice().reverse().map(h => `<li><i class="fa-solid ${ICON[h.status] || 'fa-circle'}"></i><div><b>${esc(h.label)}</b><small>Oleh: ${esc(h.by)} · ${esc(h.at)}</small>${h.note ? `<q>${esc(h.note)}</q>` : ''}</div></li>`).join('');
        if (fs) { fs.disabled = s !== 'DRAFT'; form.classList.toggle('is-view', s !== 'DRAFT'); }
      }
      el.addEventListener('click', e => {
        const b = e.target.closest('[data-ab-do]'); if (!b) return;
        e.preventDefault();
        const a = b.dataset.abDo, n = note.value.trim();
        if (a === 'reject' && !n) { fieldErr(note, 'Alasan penolakan wajib diisi.'); note.focus(); return; }
        if (a === 'submit' && form) { const req = $$(form, '.dl-field').filter(f => !el.contains(f) && $(f, '.dl-field__req') && !hasValue(f)); if (req.length) { req.forEach(f => { f.classList.add('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = 'Wajib diisi'; }); toast(`${req.length} isian wajib belum diisi sebelum diajukan.`, 'danger'); return; } }
        confirmBox({message: `${WORD[a]} dokumen ini?`, tone: a === 'reject' ? 'danger' : 'warn'}).then(ok => {
          if (!ok) return;
          const status = NEXT[a];
          hist.push({status, label: {submit: 'DIAJUKAN', approve: 'DISETUJUI', reject: 'DITOLAK', revise: 'DIREVISI'}[a], by: a === 'approve' || a === 'reject' ? el.dataset.approver : me, at: `${fmt.date()} ${fmt.time()}`, note: n});
          el.dataset.status = status; note.value = ''; fieldErr(note, ''); draw();
          el.dispatchEvent(new CustomEvent('dl:approval-status', {bubbles: true, detail: {status, action: a, note: n}}));
          toast(`Dokumen ${status === 'DRAFT' ? 'kembali ke Draft' : {SUBMITTED: 'diajukan', APPROVED: 'disetujui', REJECTED: 'ditolak'}[status]}.`, a === 'reject' ? 'danger' : 'success');
        });
      });
      draw();
    }
  }
  Devlib.define("ApprovalBar", ApprovalBar);
})(window.Devlib);
