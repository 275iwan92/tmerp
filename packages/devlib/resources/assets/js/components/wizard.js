/* DevLib · Wizard */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, hasValue, fieldText, at} = Devlib.h;

  class Wizard extends Devlib.Component {
    static selector = "[data-dl-wizard]";
    init() {
      this.steps = $$(this.el, '[data-w-step]');
      this.bar = $(this.el, '[data-w-steps]');
      this.at = 0;
      this.reached = 0;
      this.bar.innerHTML = this.steps.map((s, i) => `<li data-w-go="${i}"><span class="dl-wizard__num">${s.dataset.icon ? `<i class="fa-solid ${esc(s.dataset.icon)}"></i>` : i + 1}</span><span class="dl-wizard__lbl"><small>Langkah ${i + 1}</small>${esc(s.dataset.title)}</span></li>`).join('');
      this.el.addEventListener('input', this.clearOk, true);this.el.addEventListener('change', this.clearOk, true);
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-w-next]')) { if (this.check(this.at)) this.go(this.at + 1); return; }
        if (e.target.closest('[data-w-prev]')) return this.go(this.at - 1);
        const g = e.target.closest('[data-w-go]');
        if (g) { const i = +g.dataset.wGo; if (i <= this.reached || (i === this.at + 1 && this.check(this.at))) this.go(i); }
      });
      this.el.addEventListener('submit', e => {
        for (let i = 0; i < this.steps.length; i++) if (!this.check(i)) { e.preventDefault(); this.go(i); return; }
        if (!this.el.getAttribute('action')) {
          e.preventDefault();
          const data = {}; new FormData(this.el).forEach((v, k) => { if (k !== '_token') data[k] = v; });
          toast('Selesai: data terkumpul (pratinjau, tidak dikirim).', 'success');
          this.el.dispatchEvent(new CustomEvent('dl:wizard-finish', {bubbles: true, detail: data}));
        }
      });
      this.go(0);
    }

    invalid(s) {
      return $$(s, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
    }

    summary() {
      const box = $(this.el, '[data-w-summary]'); if (!box) return;
      box.innerHTML = this.steps.filter(s => !s.hasAttribute('data-w-review')).map((s, i) => `<div class="dl-wizard__sum"><div class="dl-wizard__sumhead"><b>${esc(s.dataset.title)}</b><button type="button" class="dl-btn dl-btn--link dl-btn--sm" data-w-go="${i}">Ubah</button></div><dl>${$$(s, '.dl-field').map(f => `<dt>${esc($(f, '.dl-field__label')?.textContent || '')}</dt><dd>${esc(fieldText(f)) || '<span class="dl-wizard__none">—</span>'}</dd>`).join('')}</dl></div>`).join('');
    }

    go(i) {
      this.at = Math.max(0, Math.min(i, this.steps.length - 1)); this.reached = Math.max(this.reached, this.at);
      this.steps.forEach((s, k) => { s.hidden = k !== this.at; });
      $$(this.bar, 'li').forEach((li, k) => { li.className = k < this.at ? 'is-done' : k === this.at ? 'is-active' : k <= this.reached ? 'is-reached' : ''; });
      const last = this.at === this.steps.length - 1;
      $(this.el, '[data-w-prev]').disabled = this.at === 0;
      $(this.el, '[data-w-next]').hidden = last; $(this.el, '[data-w-finish]').hidden = !last;
      $(this.el, '[data-w-pos]').textContent = `${this.at + 1} / ${this.steps.length}`;
      if (this.steps[this.at].hasAttribute('data-w-review')) this.summary();
      this.el.dispatchEvent(new CustomEvent('dl:wizard-step', {bubbles: true, detail: {index: this.at, title: this.steps[this.at].dataset.title}}));
    }

    check(i) {
      const bad = this.invalid(this.steps[i]);
      bad.forEach(f => { f.classList.add('is-invalid'); const e = $(f, '[data-dl-field-error]'); if (e) e.textContent = 'Wajib diisi'; });
      if (bad.length) { toast(`${bad.length} isian wajib di langkah "${this.steps[i].dataset.title}" belum diisi.`, 'danger'); $(bad[0], 'input:not([type=hidden]), textarea, button')?.focus(); }
      return !bad.length;
    }

    clearOk(e) { const f = e.target.closest('.dl-field.is-invalid'); if (f && hasValue(f)) { f.classList.remove('is-invalid'); const x = $(f, '[data-dl-field-error]'); if (x) x.textContent = ''; } }
  }
  Devlib.define("Wizard", Wizard);
})(window.Devlib);
