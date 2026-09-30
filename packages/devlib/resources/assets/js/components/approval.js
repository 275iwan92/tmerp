/* DevLib · Approval */
(function (Devlib) {
  'use strict';
  const {$, esc, toast, confirmBox, fmt} = Devlib.h;
  /* ============================ APPROVAL ============================ */
  class Approval extends Devlib.Component {
    static selector = "[data-dl-approval]";
    init() {
      this.docs = JSON.parse(this.el.dataset.docs || '[]');
      this.cols = JSON.parse(this.el.dataset.cols || '{}');
      this.me = this.el.dataset.me;
      this.cur = this.docs[0] ? this.docs[0].id : null;
      this.log = JSON.parse(this.el.dataset.log || '[]');
      this.form = $(this.el, '[data-a-form]');
      this.icon = {SUBMITTED: 'fa-paper-plane', APPROVED: 'fa-check', REJECTED: 'fa-ban', HOLD: 'fa-pause'};
      this.el.addEventListener('click', e => {
        const it = e.target.closest('[data-a-id]'); if (it) { this.cur = isNaN(+it.dataset.aId) ? it.dataset.aId : +it.dataset.aId; this.list(); this.detail(); return; }
        const b = e.target.closest('[data-a-do]'); if (b) this.decide(b.dataset.aDo);
      });
      this.list();this.detail();this.logs();
    }

    pending() {
      return this.docs.filter(d => !d.decided);
    }

    // Level approval = langkah selain pembuat (step.submitter). {n: jumlah level, at: level berjalan (1..n) atau 0}.
    level(d) {
      const lv = (d.steps || []).filter(s => !s.submitter);
      return {n: lv.length, at: lv.findIndex(s => s.status === 'current') + 1, steps: lv};
    }

    logs() {
      const box = $(this.el, '[data-a-log]'); box.hidden = !this.log.length;
      const tone = {APPROVED: 'success', REJECTED: 'danger', HOLD: 'neutral'};
      $(box, '[data-a-logrows]').innerHTML = this.log.map(l => `<li><span class="dl-badge dl-badge--pill dl-badge--${tone[l.status]}">${l.status}</span><div><b>${esc(l.no)}</b> · Level ${l.level} / ${l.levels}<small>${esc(l.by)} · ${esc(l.at)}</small>${l.note ? `<q>${esc(l.note)}</q>` : ''}</div></li>`).join('');
    }

    rp(v) {
      return fmt.money(+v || 0);
    }

    cell(k, v) {
      return k.endsWith(':money') ? fmt.number(+v || 0) : esc(v ?? '');
    }

    list() {
      const p = this.pending();
      $(this.el, '[data-a-count]').textContent = `${p.length} menunggu`;
      $(this.el, '[data-a-list]').innerHTML = p.map(d => `<button type="button" class="dl-appr__item ${d.id === this.cur ? 'is-active' : ''} ${d.held ? 'is-held' : ''}" data-a-id="${esc(d.id)}"><span class="dl-appr__row"><b>${esc(d.no)}</b><b>${this.rp(d.amount)}</b></span><span class="dl-appr__sub">${esc(d.party || '')} · ${esc(d.date ? fmt.date(d.date + 'T12:00:00+07:00') : '')}</span>${(l => l.at ? `<span class="dl-appr__lv">Level ${l.at} / ${l.n}</span>` : '')(this.level(d))}${d.held ? '<span class="dl-badge dl-badge--pill dl-badge--neutral">Ditahan</span>' : ''}</button>`).join('')
        || '<div class="dl-empty"><span class="dl-empty__icon"><i class="fa-solid fa-circle-check"></i></span><b class="dl-empty__title">Semua sudah diputuskan</b><p class="dl-empty__msg">Tidak ada dokumen yang menunggu approval Anda.</p></div>';
    }

    detail() {
      const d = this.docs.find(x => x.id === this.cur && !x.decided);
      $(this.el, '[data-a-detail]').classList.toggle('is-empty', !d);
      const hold = $(this.el, '[data-a-do=hold]'); if (hold) hold.hidden = !!(d && d.held);
      if (!d) { $(this.el, '[data-a-body]').innerHTML = ''; return; }
      const steps = d.steps || [], at = steps.findIndex(s => s.status === 'current'), lv = this.level(d);
      const lines = d.lines || [], keys = Object.keys(this.cols);
      $(this.el, '[data-a-body]').innerHTML = `
        <div class="dl-appr__title"><div><h3>${esc(d.no)} — ${esc(d.title || '')}${d.url ? ` <a class="dl-appr__open" href="${esc(d.url)}" title="Buka dokumen"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>` : ''}</h3><p>Diajukan oleh: ${esc(d.submitted_by || '')}${d.party ? ' · ' + esc(d.party) : ''}</p></div>${lv.at ? `<div class="dl-appr__level"><span class="dl-badge dl-badge--pill dl-badge--warn">Approval Level ${lv.at} of ${lv.n} — ${esc(steps[at].role)}</span><span class="dl-appr__meter" title="${lv.n} level approval">${lv.steps.map((s, i) => `<i class="is-${esc(s.status)}" title="Level ${i + 1} · ${esc(s.role)}"></i>`).join('')}</span></div>` : ''}</div>
        ${d.held ? `<div class="dl-appr__held"><i class="fa-solid fa-pause"></i><div><b>Ditahan sementara</b><small>Oleh ${esc(d.held.by)} · ${esc(d.held.at)}${d.held.note ? ` — “${esc(d.held.note)}”` : ''}</small><small>Masih bisa diputuskan: Setujui atau Tolak.</small></div></div>` : ''}
        <div class="dl-appr__summary">${Object.entries(d.summary || {}).map(([k, v]) => `<div><small>${esc(k)}</small><b>${esc(v)}</b></div>`).join('')}</div>
        ${lines.length ? `<table class="dl-appr__lines"><thead><tr>${keys.map(k => `<th class="${k.endsWith(':money') ? 'num' : ''}">${esc(this.cols[k])}</th>`).join('')}</tr></thead><tbody>${lines.map(r => `<tr>${keys.map(k => `<td class="${k.endsWith(':money') ? 'num' : ''}">${this.cell(k, r[k.replace(':money', '')])}</td>`).join('')}</tr>`).join('')}</tbody></table>` : ''}
        <div class="dl-appr__cols">
          <div><p class="dl-appr__cap">Alur Approval</p><ol class="dl-appr__steps">${steps.map(s => `<li class="is-${esc(s.status)}"><span class="dl-appr__dot">${s.status === 'done' ? '<i class="fa-solid fa-check"></i>' : s.status === 'rejected' ? '<i class="fa-solid fa-xmark"></i>' : ''}</span><b>${s.submitter ? '' : `<span class="dl-appr__lvtag">Level ${lv.steps.indexOf(s) + 1}</span> `}${esc(s.role)}</b>${s.plt ? ' <span class="dl-badge dl-badge--pill dl-badge--violet">PLT</span>' : ''}<small>${esc(s.by || (s.status === 'current' ? 'Menunggu Anda' : 'Menunggu'))}${s.at ? ' · ' + esc(s.at) : ''}</small></li>`).join('')}</ol></div>
          <div><p class="dl-appr__cap">Riwayat Dokumen</p><ul class="dl-appr__hist">${(d.history || []).map(h => `<li><i class="fa-solid ${this.icon[h.action] || 'fa-circle'}"></i><div><b>${esc(h.action)}</b>${h.plt ? ' <span class="dl-badge dl-badge--pill dl-badge--violet">PLT</span>' : ''}<small>Oleh: ${esc(h.by)} · ${esc(h.at)}</small>${h.note ? `<q>${esc(h.note)}</q>` : ''}</div></li>`).join('')}</ul></div>
        </div>`;
    }

    // Layar hanya-baca: tanpa kotak isian. Alasan Tolak/Tahan diminta di dialog konfirmasi (wajib).
    // Approve/Reject = final (dokumen keluar dari daftar, tanpa jalan balik). Hold = sementara: tetap di daftar,
    // tombol Tahan disembunyikan, dan nanti hanya bisa diputuskan Approve atau Reject (user 2026-09-30).
    decide(action) {
      const d = this.docs.find(x => x.id === this.cur && !x.decided); if (!d || (action === 'hold' && d.held)) return;
      const word = {approve: 'Setujui', reject: 'Tolak', hold: 'Tahan'}[action];
      confirmBox({title: `${word} ${d.no}?`, message: action === 'approve' ? 'Dokumen diteruskan ke tahap berikutnya. Keputusan ini final, tidak bisa dibatalkan.' : action === 'reject' ? 'Dokumen dikembalikan ke pembuat. Keputusan ini final, tidak bisa dibatalkan.' : 'Status sementara: dokumen tetap di daftar dan nanti masih bisa Disetujui atau Ditolak.',
        tone: action === 'reject' ? 'danger' : action === 'hold' ? 'warn' : 'success', ok: word, reason: action !== 'approve'}).then(res => {
        if (!res) return;
        const n = typeof res === 'string' ? res : '';
        if (this.form) {
          this.form.elements.id.value = d.id; this.form.elements.action.value = action; this.form.elements._reason.value = n;
          this.el.dispatchEvent(new CustomEvent('dl:approval', {bubbles: true, detail: {id: d.id, no: d.no, action, note: n}}));
          return this.form.submit();
        }
        const now = `${fmt.date()} ${fmt.time()}`, step = (d.steps || []).find(s => s.status === 'current'), lv = this.level(d);
        const status = {approve: 'APPROVED', reject: 'REJECTED', hold: 'HOLD'}[action];
        const log = {id: d.id, no: d.no, action, status, by: this.me, at: now, level: lv.at, levels: lv.n, role: step ? step.role : '', note: n};
        (d.history = d.history || []).push({action: status, by: this.me, at: now, note: n});
        this.log.unshift(log); this.logs();
        if (action === 'hold') { d.held = {by: this.me, at: now, note: n}; toast(`${d.no} ditahan.`, 'warn'); this.detail(); this.list(); }
        else {
          if (step) Object.assign(step, {status: action === 'approve' ? 'done' : 'rejected', by: this.me, at: now});
          d.decided = action;
          toast(`${d.no} ${action === 'approve' ? 'disetujui' : 'ditolak'}.`, action === 'approve' ? 'success' : 'danger');
          const next = this.pending()[0]; this.cur = next ? next.id : null; this.list(); this.detail();
        }
        this.el.dispatchEvent(new CustomEvent('dl:approval', {bubbles: true, detail: log}));
      });
    }
  }
  Devlib.define("Approval", Approval);
})(window.Devlib);
