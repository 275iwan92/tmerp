/* DevLib · Coverage */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;
  class Coverage extends Devlib.Component {
    static selector = "[data-dl-cov]";
    init() {
      this.q = $(this.el, '[data-cv-q]'); this.flag = '';
      this.q.addEventListener('input', () => this.apply());
      this.el.addEventListener('click', e => {
        const f = e.target.closest('[data-cv-filter]');
        if (f) { this.flag = f.dataset.cvFilter; $$(this.el, '[data-cv-filter]').forEach(b => b.classList.toggle('is-active', b === f)); this.apply(); return; }
        const t = e.target.closest('[data-cv-toggle]');
        if (t) this.open(t.closest('[data-cv-row]'), t.getAttribute('aria-expanded') !== 'true');
      });
    }
    open(row, on) {
      const t = $(row, '[data-cv-toggle]'), d = row.nextElementSibling;
      if (!t || !d || !d.matches('[data-cv-detail]')) return;
      t.setAttribute('aria-expanded', on ? 'true' : 'false'); row.classList.toggle('is-open', on); d.hidden = !on;
    }
    apply() {
      const q = this.q.value.trim().toLowerCase(); let n = 0;
      $$(this.el, '[data-cv-row]').forEach(r => {
        const show = (!q || r.dataset.text.includes(q)) && (!this.flag || r.dataset.flags.split(' ').includes(this.flag));
        r.hidden = !show; if (show) n++; else this.open(r, false);
      });
      $(this.el, '[data-cv-empty]').hidden = n > 0;
    }
  }
  Devlib.define("Coverage", Coverage);
})(window.Devlib);
