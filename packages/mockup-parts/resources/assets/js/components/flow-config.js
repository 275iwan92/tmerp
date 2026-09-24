/* DevLib · FlowConfig */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ FLOW CONFIG ============================ */
  class FlowConfig extends Devlib.Component {
    static selector = "[data-dl-flowcfg]";
    init() {
      this.roles = JSON.parse(this.el.dataset.roles || '{}');
      $$(this.el, '[data-fc-doc]').forEach(panel => {
        const s = () => setTimeout(() => this.sim(panel));
        panel.addEventListener('input', e => { if (e.target.matches('[data-fc-value]')) { const n = this.num(e.target.value); e.target.value = n ? n.toLocaleString('id-ID') : ''; } s(); });
        panel.addEventListener('change', s); panel.addEventListener('click', s);
        this.sim(panel);
      });
      this.el.addEventListener('click', e => {
        if (!e.target.closest('[data-fc-save]')) return;
        const flows = {}; let bad = 0;
        $$(this.el, '[data-fc-doc]').forEach(p => { const l = $(p, '[data-dl-lines]'); bad += l.dlLines.validate(); flows[p.dataset.fcDoc] = l.dlLines.rows; });
        if (bad) return toast(`${bad} isian wajib di alur approval belum diisi.`, 'danger');
        this.el.dispatchEvent(new CustomEvent('dl:flowcfg-save', {bubbles: true, detail: {flows}}));
        toast('Konfigurasi alur approval disimpan.', 'success');
      });
    }

    num(v) {
      return +String(v ?? '').replace(/\D/g, '') || 0;
    }

    sim(panel) {
      const lines = $(panel, '[data-dl-lines]'); if (!lines || !lines.dlLines) return;
      const v = this.num($(panel, '[data-fc-value]').value), rows = lines.dlLines.rows;
      const act = {KNOW: 'Mengetahui', APPROVE: 'Menyetujui'};
      $(panel, '[data-fc-path]').innerHTML = `<li class="is-done"><span class="dl-appr__dot"><i class="fa-solid fa-check"></i></span><b>Pembuat mengajukan</b><small>Langkah 0 · selalu</small></li>`
        + rows.map((r, i) => { const on = !+r.min || v >= +r.min; return `<li class="${on ? 'is-current' : 'is-skip'}"><span class="dl-appr__dot">${on ? i + 1 : ''}</span><b>${esc(r.name || 'Langkah ' + (i + 1))} — ${esc(act[r.action] || '')}</b><small>${esc(this.roles[r.role] || r.role || '—')} · ${+r.min ? (on ? `aktif: nilai ≥ Rp ${(+r.min).toLocaleString('id-ID')}` : `dilewati: nilai < Rp ${(+r.min).toLocaleString('id-ID')}`) : 'selalu aktif'}</small></li>`; }).join('');
    }
  }
  Devlib.define("FlowConfig", FlowConfig);
})(window.Devlib);
