/* DevLib · Approval */
(function (Devlib) {
  'use strict';
  const {$, esc, toast, confirmBox, fmt, fieldErr, at} = Devlib.h;
  /* ============================ APPROVAL ============================ */
  class Approval extends Devlib.Component {
    static selector = "[data-dl-approval]";
    init() {
      const el = this.el;
      const docs = JSON.parse(el.dataset.docs || '[]'), cols = JSON.parse(el.dataset.cols || '{}'), me = el.dataset.me;
      let cur = docs[0] ? docs[0].id : null;
      const pending = () => docs.filter(d => !d.decided);
      const note = $(el, '[name=approval_note]'), noteField = note.closest('.dl-field');
      const rp = v => 'Rp ' + (+v || 0).toLocaleString('id-ID');
      const cell = (k, v) => k.endsWith(':money') ? (+v || 0).toLocaleString('id-ID') : esc(v ?? '');
      const icon = {SUBMITTED: 'fa-paper-plane', APPROVED: 'fa-check', REJECTED: 'fa-ban', HOLD: 'fa-pause'};

      function list() {
        const p = pending();
        $(el, '[data-a-count]').textContent = `${p.length} menunggu`;
        $(el, '[data-a-list]').innerHTML = p.map(d => `<button type="button" class="dl-appr__item ${d.id === cur ? 'is-active' : ''} ${d.held ? 'is-held' : ''}" data-a-id="${esc(d.id)}"><span class="dl-appr__row"><b>${esc(d.no)}</b><b>${rp(d.amount)}</b></span><span class="dl-appr__sub">${esc(d.party || '')} · ${esc(d.date ? fmt.date(d.date + 'T12:00:00+07:00') : '')}</span>${d.held ? '<span class="dl-badge dl-badge--pill dl-badge--neutral">Ditahan</span>' : ''}</button>`).join('')
          || '<div class="dl-empty"><span class="dl-empty__icon"><i class="fa-solid fa-circle-check"></i></span><b class="dl-empty__title">Semua sudah diputuskan</b><p class="dl-empty__msg">Tidak ada dokumen yang menunggu approval Anda.</p></div>';
      }
      function detail() {
        const d = docs.find(x => x.id === cur && !x.decided);
        $(el, '[data-a-detail]').classList.toggle('is-empty', !d);
        if (!d) { $(el, '[data-a-body]').innerHTML = ''; return; }
        const steps = d.steps || [], at = steps.findIndex(s => s.status === 'current');
        const lines = d.lines || [], keys = Object.keys(cols);
        $(el, '[data-a-body]').innerHTML = `
          <div class="dl-appr__title"><div><h3>${esc(d.no)} — ${esc(d.title || '')}</h3><p>Diajukan oleh: ${esc(d.submitted_by || '')}${d.party ? ' · ' + esc(d.party) : ''}</p></div>${at >= 0 ? `<span class="dl-badge dl-badge--pill dl-badge--warn">Step ${at + 1}/${steps.length} — ${esc(steps[at].role)}</span>` : ''}</div>
          <div class="dl-appr__summary">${Object.entries(d.summary || {}).map(([k, v]) => `<div><small>${esc(k)}</small><b>${esc(v)}</b></div>`).join('')}</div>
          ${lines.length ? `<table class="dl-appr__lines"><thead><tr>${keys.map(k => `<th class="${k.endsWith(':money') ? 'num' : ''}">${esc(cols[k])}</th>`).join('')}</tr></thead><tbody>${lines.map(r => `<tr>${keys.map(k => `<td class="${k.endsWith(':money') ? 'num' : ''}">${cell(k, r[k.replace(':money', '')])}</td>`).join('')}</tr>`).join('')}</tbody></table>` : ''}
          <div class="dl-appr__cols">
            <div><p class="dl-appr__cap">Alur Approval</p><ol class="dl-appr__steps">${steps.map(s => `<li class="is-${esc(s.status)}"><span class="dl-appr__dot">${s.status === 'done' ? '<i class="fa-solid fa-check"></i>' : s.status === 'rejected' ? '<i class="fa-solid fa-xmark"></i>' : ''}</span><b>${esc(s.role)}</b>${s.plt ? ' <span class="dl-badge dl-badge--pill dl-badge--violet">PLT</span>' : ''}<small>${esc(s.by || (s.status === 'current' ? 'Menunggu Anda' : 'Menunggu'))}${s.at ? ' · ' + esc(s.at) : ''}</small></li>`).join('')}</ol></div>
            <div><p class="dl-appr__cap">Riwayat Dokumen</p><ul class="dl-appr__hist">${(d.history || []).map(h => `<li><i class="fa-solid ${icon[h.action] || 'fa-circle'}"></i><div><b>${esc(h.action)}</b>${h.plt ? ' <span class="dl-badge dl-badge--pill dl-badge--violet">PLT</span>' : ''}<small>Oleh: ${esc(h.by)} · ${esc(h.at)}</small>${h.note ? `<q>${esc(h.note)}</q>` : ''}</div></li>`).join('')}</ul></div>
          </div>`;
        note.value = ''; fieldErr(note, '');
      }
      function decide(action) {
        const d = docs.find(x => x.id === cur && !x.decided); if (!d) return;
        const n = note.value.trim();
        if (action !== 'approve' && !n) { fieldErr(note, action === 'reject' ? 'Alasan penolakan wajib diisi.' : 'Alasan menahan wajib diisi.'); note.focus(); return; }
        const word = {approve: 'Setujui', reject: 'Tolak', hold: 'Tahan'}[action];
        confirmBox({message: `${word} ${d.no}?`, tone: action === 'reject' ? 'danger' : action === 'hold' ? 'warn' : 'success'}).then(ok => {
          if (!ok) return;
          const now = `${fmt.date()} ${fmt.time()}`, step = (d.steps || []).find(s => s.status === 'current');
          (d.history = d.history || []).push({action: {approve: 'APPROVED', reject: 'REJECTED', hold: 'HOLD'}[action], by: me, at: now, note: n});
          if (action === 'hold') { d.held = true; toast(`${d.no} ditahan.`, 'warn'); detail(); list(); }
          else {
            if (step) Object.assign(step, {status: action === 'approve' ? 'done' : 'rejected', by: me, at: now});
            d.decided = action;
            toast(`${d.no} ${action === 'approve' ? 'disetujui' : 'ditolak'}.`, action === 'approve' ? 'success' : 'danger');
            const next = pending()[0]; cur = next ? next.id : null; list(); detail();
          }
          el.dispatchEvent(new CustomEvent('dl:approval', {bubbles: true, detail: {id: d.id, action, note: n}}));
        });
      }
      el.addEventListener('click', e => {
        const it = e.target.closest('[data-a-id]'); if (it) { cur = isNaN(+it.dataset.aId) ? it.dataset.aId : +it.dataset.aId; list(); detail(); return; }
        const b = e.target.closest('[data-a-do]'); if (b) decide(b.dataset.aDo);
      });
      list(); detail();
    }
  }
  Devlib.define("Approval", Approval);
})(window.Devlib);
