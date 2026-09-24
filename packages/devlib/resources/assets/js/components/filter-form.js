/* DevLib · FilterForm */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, setField, fillForm, fieldText} = Devlib.h;
  /* ============================ FILTER FORM ============================ */
  class FilterForm extends Devlib.Component {
    static selector = "[data-dl-filter]";
    init() {
      this.key = 'dl-filter-' + this.el.dataset.id;
      this.el.addEventListener('submit', e => { e.preventDefault(); this.apply(); toast(`Filter diterapkan (${Object.keys(this.values()).length} kriteria).`, 'success'); });
      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-fl-toggle]')) { this.el.classList.toggle('is-closed'); return; }
        if (x.closest('[data-fl-reset]')) { fillForm(this.el, {}, true); this.apply(); return; }
        const ch = x.closest('[data-fl-chip]'); if (ch) { setField(this.el, ch.dataset.flChip, ''); this.apply(); return; }
        if (x.closest('[data-fl-save]')) { $(this.el, '[data-fl-savebox]').hidden = false; $(this.el, '[data-fl-name]').focus(); return; }
        if (x.closest('[data-fl-saveok]')) { const n = $(this.el, '[data-fl-name]').value.trim(); if (!n) return; const P = this.load(); P[n] = this.values(); try { localStorage.setItem(this.key, JSON.stringify(P)); } catch (er) {} $(this.el, '[data-fl-savebox]').hidden = true; $(this.el, '[data-fl-name]').value = ''; this.presets(); toast(`Filter "${n}" disimpan.`, 'success'); }
      });
      this.el.addEventListener('change', e => { if (e.target.matches('[data-fl-presets]') && e.target.value) { fillForm(this.el, this.load()[e.target.value] || {}, true); this.apply(); e.target.value = ''; } });
      this.el.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-fl-name]')) { e.preventDefault(); $(this.el, '[data-fl-saveok]').click(); } });
      this.presets();this.chips();
    }
    load() { try { return JSON.parse(localStorage.getItem(this.key) || '{}'); } catch (e) { return {}; } }
    presets() { const P = this.load(); $(this.el, '[data-fl-presets]').innerHTML = '<option value="">Filter tersimpan…</option>' + Object.keys(P).map(n => `<option>${esc(n)}</option>`).join(''); }
    values() { const v = {}, aux = new Set($$(this.el, '[data-dl-prefix-value]').map(i => i.name)); new FormData(this.el).forEach((x, k) => { if (!aux.has(k) && String(x).trim() !== '') v[k] = x; }); return v; }
    labelOf(k) { const f = this.el.querySelector(`[name="${CSS.escape(k)}"]`)?.closest('.dl-field'); return f ? ($(f, '.dl-field__label')?.textContent || k) : k; }

    chips() {
      const v = this.values();
      const seen = new Set();
      $(this.el, '[data-fl-chips]').innerHTML = Object.keys(v).map(k => { const f = this.el.querySelector(`[name="${CSS.escape(k)}"]`)?.closest('.dl-field'); if (f && seen.has(f)) return ''; if (f) seen.add(f); return `<span class="dl-tags__chip">${esc(this.labelOf(k))}: ${esc(f ? fieldText(f) || v[k] : v[k])}<button type="button" data-fl-chip="${esc(k)}" aria-label="Lepas">&times;</button></span>`; }).join('') || '<span class="dl-filter__none">Belum ada kriteria</span>';
    }

    apply() { this.chips(); this.el.dispatchEvent(new CustomEvent('dl:filter', {bubbles: true, detail: {values: this.values()}})); }
  }
  Devlib.define("FilterForm", FilterForm);
})(window.Devlib);
