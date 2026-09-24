/* DevLib · MegaMenu */
(function (Devlib) {
  'use strict';
  const {$, $$, toast} = Devlib.h;
  /* ============================ MEGA MENU (ScriptCase) ============================ */
  class MegaMenu extends Devlib.Component {
    static selector = "[data-dl-mega]";
    init() {
      const el = this.el;
      const tops = () => $$(el, '.dl-mm__top');
      const close = except => tops().forEach(t => { if (t !== except) { t.classList.remove('is-open'); $(t, '[data-mm-open]')?.setAttribute('aria-expanded', 'false'); } });
      const open = t => { close(t); t.classList.add('is-open'); $(t, '[data-mm-open]')?.setAttribute('aria-expanded', 'true'); };
      const narrow = () => el.classList.contains('is-narrow');
      new ResizeObserver(() => { el.classList.toggle('is-narrow', el.clientWidth < 760); if (!narrow()) el.classList.remove('is-mobile'); }).observe(el);
      let timer;
      tops().forEach(t => {
        if (!$(t, '[data-mm-open]')) return;
        t.addEventListener('mouseenter', () => { if (narrow() || !matchMedia('(hover: hover)').matches) return; clearTimeout(timer); open(t); });
        t.addEventListener('mouseleave', () => { if (narrow()) return; timer = setTimeout(() => t.classList.remove('is-open'), 180); });
      });
      el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-mm-burger]')) { el.classList.toggle('is-mobile'); return; }
        const o = x.closest('[data-mm-open]'); if (o) { const t = o.closest('.dl-mm__top'); t.classList.contains('is-open') ? close() : open(t); return; }
        const s = x.closest('[data-mm-sub]'); if (s) { s.parentElement.classList.toggle('is-open'); return; }
        const k = x.closest('[data-mm-key]');
        if (k) {
          e.preventDefault();
          tops().forEach(t => t.classList.toggle('is-current', t.contains(k)));
          close(); el.classList.remove('is-mobile');
          const label = k.textContent.trim();
          if (el.dispatchEvent(new CustomEvent('dl:menu', {bubbles: true, cancelable: true, detail: {key: k.dataset.mmKey, label}}))) toast(`Buka: ${label} (pratinjau)`);
        }
      });
      el.addEventListener('keydown', e => {
        if (e.key === 'Escape') { close(); return; }
        const cur = e.target.closest('.dl-mm__tl'); if (!cur || !['ArrowLeft', 'ArrowRight', 'ArrowDown'].includes(e.key)) return;
        e.preventDefault();
        const all = $$(el, '.dl-mm__tl'), i = all.indexOf(cur);
        if (e.key === 'ArrowDown') { const t = cur.closest('.dl-mm__top'); if ($(t, '.dl-mm__panel')) { open(t); $(t, '.dl-mm__panel a, .dl-mm__panel button')?.focus(); } return; }
        all[(i + (e.key === 'ArrowRight' ? 1 : all.length - 1)) % all.length].focus();
      });
      document.addEventListener('click', e => { if (!el.contains(e.target)) { close(); el.classList.remove('is-mobile'); } });
    }
  }
  Devlib.define("MegaMenu", MegaMenu);
})(window.Devlib);
