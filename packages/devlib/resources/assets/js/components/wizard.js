/* DevLib · Wizard */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, hasValue, fieldText, at} = Devlib.h;

  class Wizard extends Devlib.Component {
    static selector = "[data-dl-wizard]";
    init() {
      const w = this.el;
      const steps = $$(w, '[data-w-step]'), bar = $(w, '[data-w-steps]');
      let at = 0, reached = 0;
      bar.innerHTML = steps.map((s, i) => `<li data-w-go="${i}"><span class="dl-wizard__num">${s.dataset.icon ? `<i class="fa-solid ${esc(s.dataset.icon)}"></i>` : i + 1}</span><span class="dl-wizard__lbl"><small>Langkah ${i + 1}</small>${esc(s.dataset.title)}</span></li>`).join('');
      const invalid = s => $$(s, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
      function summary() {
        const box = $(w, '[data-w-summary]'); if (!box) return;
        box.innerHTML = steps.filter(s => !s.hasAttribute('data-w-review')).map((s, i) => `<div class="dl-wizard__sum"><div class="dl-wizard__sumhead"><b>${esc(s.dataset.title)}</b><button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-w-go="${i}">Ubah</button></div><dl>${$$(s, '.dl-field').map(f => `<dt>${esc($(f, '.dl-field__label')?.textContent || '')}</dt><dd>${esc(fieldText(f)) || '<span class="dl-wizard__none">—</span>'}</dd>`).join('')}</dl></div>`).join('');
      }
      function go(i) {
        at = Math.max(0, Math.min(i, steps.length - 1)); reached = Math.max(reached, at);
        steps.forEach((s, k) => { s.hidden = k !== at; });
        $$(bar, 'li').forEach((li, k) => { li.className = k < at ? 'is-done' : k === at ? 'is-active' : k <= reached ? 'is-reached' : ''; });
        const last = at === steps.length - 1;
        $(w, '[data-w-prev]').disabled = at === 0;
        $(w, '[data-w-next]').hidden = last; $(w, '[data-w-finish]').hidden = !last;
        $(w, '[data-w-pos]').textContent = `${at + 1} / ${steps.length}`;
        if (steps[at].hasAttribute('data-w-review')) summary();
        w.dispatchEvent(new CustomEvent('dl:wizard-step', {bubbles: true, detail: {index: at, title: steps[at].dataset.title}}));
      }
      function check(i) {
        const bad = invalid(steps[i]);
        bad.forEach(f => { f.classList.add('is-invalid'); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = 'Wajib diisi'; });
        if (bad.length) { toast(`${bad.length} isian wajib di langkah "${steps[i].dataset.title}" belum diisi.`, 'danger'); $(bad[0], 'input:not([type=hidden]), textarea, button')?.focus(); }
        return !bad.length;
      }
      const clearOk = e => { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } };
      w.addEventListener('input', clearOk, true); w.addEventListener('change', clearOk, true);
      w.addEventListener('click', e => {
        if (e.target.closest('[data-w-next]')) { if (check(at)) go(at + 1); return; }
        if (e.target.closest('[data-w-prev]')) return go(at - 1);
        const g = e.target.closest('[data-w-go]');
        if (g) { const i = +g.dataset.wGo; if (i <= reached || (i === at + 1 && check(at))) go(i); }
      });
      w.addEventListener('submit', e => {
        for (let i = 0; i < steps.length; i++) if (!check(i)) { e.preventDefault(); go(i); return; }
        if (!w.getAttribute('action')) {
          e.preventDefault();
          const data = {}; new FormData(w).forEach((v, k) => { if (k !== '_token') data[k] = v; });
          toast('Selesai: data terkumpul (pratinjau, tidak dikirim).', 'success');
          w.dispatchEvent(new CustomEvent('dl:wizard-finish', {bubbles: true, detail: data}));
        }
      });
      go(0);
    }
  }
  Devlib.define("Wizard", Wizard);
})(window.Devlib);
