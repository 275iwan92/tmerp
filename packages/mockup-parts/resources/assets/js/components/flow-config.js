/* DevLib · FlowConfig */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, t: _t} = Devlib.h;
  const _tf = (s, r) => { let x = _t(s); Object.keys(r).forEach(k => { x = x.split(':' + k).join(r[k]); }); return x; };
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
        if (bad) return toast(_tf(':n required fields in the approval flow are not filled in yet.', {n: bad}), 'danger');
        this.el.dispatchEvent(new CustomEvent('dl:flowcfg-save', {bubbles: true, detail: {flows}}));
        toast(_t('Approval flow configuration saved.'), 'success');
      });
    }

    num(v) {
      return +String(v ?? '').replace(/\D/g, '') || 0;
    }

    sim(panel) {
      const lines = $(panel, '[data-dl-lines]'); if (!lines || !lines.dlLines) return;
      const v = this.num($(panel, '[data-fc-value]').value), rows = lines.dlLines.rows;
      const act = {KNOW: _t('Acknowledges'), APPROVE: _t('Approves')};
      $(panel, '[data-fc-path]').innerHTML = `<li class="is-done"><span class="dl-appr__dot"><i class="fa-solid fa-check"></i></span><b>${esc(_t('Creator submits'))}</b><small>${esc(_t('Step 0 · always'))}</small></li>`
        + rows.map((r, i) => { const on = !+r.min || v >= +r.min; return `<li class="${on ? 'is-current' : 'is-skip'}"><span class="dl-appr__dot">${on ? i + 1 : ''}</span><b>${esc(r.name || _t('Step') + ' ' + (i + 1))} — ${esc(act[r.action] || '')}</b><small>${esc(this.roles[r.role] || r.role || '—')} · ${esc(+r.min ? (on ? _tf('active: value ≥ Rp :v', {v: (+r.min).toLocaleString('id-ID')}) : _tf('skipped: value < Rp :v', {v: (+r.min).toLocaleString('id-ID')})) : _t('always active'))}</small></li>`; }).join('');
    }
  }
  Devlib.define("FlowConfig", FlowConfig);
})(window.Devlib);
