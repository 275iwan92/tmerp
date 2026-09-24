/* DevLib · FilterForm */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, setField, fillForm, fieldText} = Devlib.h;
  /* ============================ FILTER FORM ============================ */
  class FilterForm extends Devlib.Component {
    static selector = "[data-dl-filter]";
    init() {
      const form = this.el;
      const key = 'dl-filter-' + form.dataset.id;
      const load = () => { try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; } };
      const presets = () => { const P = load(); $(form, '[data-fl-presets]').innerHTML = '<option value="">Filter tersimpan…</option>' + Object.keys(P).map(n => `<option>${esc(n)}</option>`).join(''); };
      const values = () => { const v = {}, aux = new Set($$(form, '[data-dl-prefix-value]').map(i => i.name)); new FormData(form).forEach((x, k) => { if (!aux.has(k) && String(x).trim() !== '') v[k] = x; }); return v; };
      const labelOf = k => { const f = form.querySelector(`[name="${CSS.escape(k)}"]`)?.closest('.dl-field'); return f ? ($(f, '.dl-field__label')?.textContent || k) : k; };
      function chips() {
        const v = values();
        const seen = new Set();
        $(form, '[data-fl-chips]').innerHTML = Object.keys(v).map(k => { const f = form.querySelector(`[name="${CSS.escape(k)}"]`)?.closest('.dl-field'); if (f && seen.has(f)) return ''; if (f) seen.add(f); return `<span class="dl-tags__chip">${esc(labelOf(k))}: ${esc(f ? fieldText(f) || v[k] : v[k])}<button type="button" data-fl-chip="${esc(k)}" aria-label="Lepas">&times;</button></span>`; }).join('') || '<span class="dl-filter__none">Belum ada kriteria</span>';
      }
      const apply = () => { chips(); form.dispatchEvent(new CustomEvent('dl:filter', {bubbles: true, detail: {values: values()}})); };
      form.addEventListener('submit', e => { e.preventDefault(); apply(); toast(`Filter diterapkan (${Object.keys(values()).length} kriteria).`, 'success'); });
      form.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-fl-toggle]')) { form.classList.toggle('is-closed'); return; }
        if (x.closest('[data-fl-reset]')) { fillForm(form, {}, true); apply(); return; }
        const ch = x.closest('[data-fl-chip]'); if (ch) { setField(form, ch.dataset.flChip, ''); apply(); return; }
        if (x.closest('[data-fl-save]')) { $(form, '[data-fl-savebox]').hidden = false; $(form, '[data-fl-name]').focus(); return; }
        if (x.closest('[data-fl-saveok]')) { const n = $(form, '[data-fl-name]').value.trim(); if (!n) return; const P = load(); P[n] = values(); try { localStorage.setItem(key, JSON.stringify(P)); } catch (er) {} $(form, '[data-fl-savebox]').hidden = true; $(form, '[data-fl-name]').value = ''; presets(); toast(`Filter "${n}" disimpan.`, 'success'); }
      });
      form.addEventListener('change', e => { if (e.target.matches('[data-fl-presets]') && e.target.value) { fillForm(form, load()[e.target.value] || {}, true); apply(); e.target.value = ''; } });
      form.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-fl-name]')) { e.preventDefault(); $(form, '[data-fl-saveok]').click(); } });
      presets(); chips();
    }
  }
  Devlib.define("FilterForm", FilterForm);
})(window.Devlib);
