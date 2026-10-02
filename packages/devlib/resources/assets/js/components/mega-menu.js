/* DevLib · MegaMenu */
(function (Devlib) {
  'use strict';
  const {$, $$, toast} = Devlib.h;
  /* ============================ MEGA MENU (ScriptCase) ============================ */
  class MegaMenu extends Devlib.Component {
    static selector = "[data-dl-mega]";
    init() {
      new ResizeObserver(() => { this.el.classList.toggle('is-narrow', this.el.clientWidth < 760); if (!this.narrow()) this.el.classList.remove('is-mobile'); }).observe(this.el);
      this.timer = undefined;
      this.tops().forEach(t => {
        if (!$(t, '[data-mm-open]')) return;
        t.addEventListener('mouseenter', () => { if (this.narrow() || !matchMedia('(hover: hover)').matches) return; clearTimeout(this.timer); this.open(t); });
        t.addEventListener('mouseleave', () => { if (this.narrow()) return; this.timer = setTimeout(() => t.classList.remove('is-open'), 180); });
      });
      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-mm-burger]')) { this.el.classList.toggle('is-mobile'); return; }
        const o = x.closest('[data-mm-open]'); if (o) { const t = o.closest('.dl-mm__top'); t.classList.contains('is-open') ? this.close() : this.open(t); return; }
        const s = x.closest('[data-mm-sub]'); if (s) { s.parentElement.classList.toggle('is-open'); return; }
        const k = x.closest('[data-mm-key]');
        if (k) {
          e.preventDefault();
          this.tops().forEach(t => t.classList.toggle('is-current', t.contains(k)));
          this.close(); this.el.classList.remove('is-mobile');
          const label = k.textContent.trim();
          if (this.el.dispatchEvent(new CustomEvent('dl:menu', {bubbles: true, cancelable: true, detail: {key: k.dataset.mmKey, label}}))) toast(Devlib.h.Tf('open_preview', {x: label}));
        }
      });
      this.el.addEventListener('keydown', e => {
        if (e.key === 'Escape') { this.close(); return; }
        const cur = e.target.closest('.dl-mm__tl'); if (!cur || !['ArrowLeft', 'ArrowRight', 'ArrowDown'].includes(e.key)) return;
        e.preventDefault();
        const all = $$(this.el, '.dl-mm__tl'), i = all.indexOf(cur);
        if (e.key === 'ArrowDown') { const t = cur.closest('.dl-mm__top'); if ($(t, '.dl-mm__panel')) { this.open(t); $(t, '.dl-mm__panel a, .dl-mm__panel button')?.focus(); } return; }
        all[(i + (e.key === 'ArrowRight' ? 1 : all.length - 1)) % all.length].focus();
      });
      document.addEventListener('click', e => { if (!this.el.contains(e.target)) { this.close(); this.el.classList.remove('is-mobile'); } });
    }

    tops() {
      return $$(this.el, '.dl-mm__top');
    }

    close(except) {
      return this.tops().forEach(t => { if (t !== except) { t.classList.remove('is-open'); $(t, '[data-mm-open]')?.setAttribute('aria-expanded', 'false'); } });
    }

    open(t) { this.close(t); t.classList.add('is-open'); $(t, '[data-mm-open]')?.setAttribute('aria-expanded', 'true'); }

    narrow() {
      return this.el.classList.contains('is-narrow');
    }
  }
  Devlib.define("MegaMenu", MegaMenu);
})(window.Devlib);
