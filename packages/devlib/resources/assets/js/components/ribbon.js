/* DevLib · Ribbon */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ RIBBON (Excel) ============================ */
  class Ribbon extends Devlib.Component {
    static selector = "[data-dl-ribbon]";
    init() {
      const el = this.el;
      const closeMenus = except => $$(el, '.dl-rb__drop.is-open, .dl-rb__split.is-open, .dl-rb__mi.is-open').forEach(x => { if (!except || !x.contains(except)) x.classList.remove('is-open'); });
      const panelOf = b => b.closest('[data-rb-panel]');
      const labelOf = b => (b.querySelector('.dl-rb__lbl, span:not(.dl-rb__ico):not(.dl-rb__mico)')?.textContent || b.title || b.dataset.rbKey).trim();
      const tabs = () => $$(el, '[data-rb-tab]');
      const setTab = key => { tabs().forEach(t => t.classList.toggle('is-active', t.dataset.rbTab === key)); $$(el, '[data-rb-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.rbPanel === key)); };
      function run(b) {
        let on = null;
        if (b.hasAttribute('data-rb-toggle')) {
          const r = b.dataset.rbRadio;
          on = r ? true : b.getAttribute('aria-pressed') !== 'true';
          if (r) $$(el, `[data-rb-radio="${r}"]`).forEach(x => { x.setAttribute('aria-pressed', 'false'); x.classList.remove('is-on'); });
          b.setAttribute('aria-pressed', String(on)); b.classList.toggle('is-on', on);
        }
        closeMenus(); el.classList.remove('is-float'); tips(false);
        const ev = new CustomEvent('dl:ribbon', {bubbles: true, cancelable: true, detail: {key: b.dataset.rbKey, label: labelOf(b), on}});
        if (el.dispatchEvent(ev)) toast(`${labelOf(b)}${on == null ? '' : on ? ' — aktif' : ' — mati'} (pratinjau)`);
      }
      el.addEventListener('click', e => {
        const x = e.target;
        const tab = x.closest('[data-rb-tab]');
        if (tab) { const was = tab.classList.contains('is-active'); setTab(tab.dataset.rbTab); if (el.classList.contains('is-min')) el.classList.toggle('is-float', !(was && el.classList.contains('is-float'))); return; }
        if (x.closest('[data-rb-pin]')) { el.classList.toggle('is-min'); el.classList.remove('is-float'); return; }
        if (x.closest('[data-rb-file]')) { $(el, '[data-rb-stage]').hidden = false; return; }
        if (x.closest('[data-rb-stagex]')) { $(el, '[data-rb-stage]').hidden = true; return; }
        const drop = x.closest('[data-rb-drop]');
        if (drop) {
          const box = drop.parentElement, open = !box.classList.contains('is-open'); closeMenus(); box.classList.toggle('is-open', open);
          // Panel ribbon bisa di-scroll horizontal -> menu dipasang fixed supaya tidak terpotong.
          if (open) { const m = $(box, ':scope > .dl-rb__menu'), r = box.getBoundingClientRect(); m.style.position = 'fixed'; m.style.top = r.bottom + 'px'; m.style.left = Math.max(8, Math.min(r.left, innerWidth - m.offsetWidth - 8)) + 'px'; }
          return;
        }
        const sub = x.closest('[data-rb-sub]');
        if (sub) { sub.parentElement.classList.toggle('is-open'); return; }
        const f = x.closest('[data-rb-run]'); if (f) { const b = $(el, `[data-rb-key="${CSS.escape(f.dataset.rbRun)}"]`); $(el, '[data-rb-found]').hidden = true; $(el, '[data-rb-find]').value = ''; if (b) run(b); return; }
        const k = x.closest('[data-rb-key]');
        if (k) { if ($(el, '[data-rb-stage]') && k.closest('[data-rb-stage]')) $(el, '[data-rb-stage]').hidden = true; return run(k); }
      });
      el.addEventListener('dblclick', e => { if (e.target.closest('[data-rb-tab]')) { el.classList.toggle('is-min'); el.classList.remove('is-float'); } });
      document.addEventListener('click', e => { if (!el.contains(e.target)) { closeMenus(); el.classList.remove('is-float'); } });
      window.addEventListener('scroll', e => { if (!(e.target instanceof Element && e.target.closest('.dl-rb__menu'))) closeMenus(); }, true);
      // Cari perintah (seperti "Tell me"): semua tombol ber-key dari semua tab + jalurnya.
      el.addEventListener('input', e => {
        if (!e.target.matches('[data-rb-find]')) return;
        const q = e.target.value.trim().toLowerCase(), box = $(el, '[data-rb-found]');
        if (!q) { box.hidden = true; return; }
        const hits = $$(el, '[data-rb-panel] [data-rb-key]').map(b => { const p = panelOf(b), g = b.closest('.dl-rb__group'); return {key: b.dataset.rbKey, label: labelOf(b), path: `${$(el, `[data-rb-tab="${p.dataset.rbPanel}"]`).textContent} › ${g ? $(g, '.dl-rb__glabel').textContent : ''}`}; })
          .filter((h, i, a) => h.label.toLowerCase().includes(q) && a.findIndex(z => z.key === h.key) === i).slice(0, 8);
        box.innerHTML = hits.map(h => `<button type="button" data-rb-run="${esc(h.key)}"><b>${esc(h.label)}</b><small>${esc(h.path)}</small></button>`).join('') || '<p>Tidak ada perintah.</p>';
        box.hidden = false;
      });
      // KeyTips: tekan Alt (lepas) -> huruf muncul di tab & tombol ber-tip; tekan hurufnya = klik.
      let tipOn = false;
      function tips(on) {
        tipOn = on; $$(el, '.dl-rb__kt').forEach(t => t.remove());
        if (!on) return;
        const scope = [...tabs(), ...$$(el, '[data-rb-file]'), ...$$(el, '.dl-rb__panel.is-active [data-rb-tip]')];
        scope.filter(b => b.dataset.rbTip).forEach(b => b.insertAdjacentHTML('beforeend', `<span class="dl-rb__kt">${esc(b.dataset.rbTip)}</span>`));
      }
      let hot = false, altAlone = false;
      el.addEventListener('pointerenter', () => { hot = true; });
      document.addEventListener('pointerdown', e => { hot = el.contains(e.target); });
      document.addEventListener('keydown', e => {
        if (!hot) return;
        if (e.key === 'Alt') { altAlone = true; return; }
        altAlone = false;
        if (e.ctrlKey && e.key === 'F1') { e.preventDefault(); el.classList.toggle('is-min'); return; }
        if (e.key === 'Escape') { closeMenus(); tips(false); el.classList.remove('is-float'); const st = $(el, '[data-rb-stage]'); if (st) st.hidden = true; return; }
        if (tipOn && /^[a-z0-9]$/i.test(e.key)) {
          const t = [...tabs(), ...$$(el, '[data-rb-file]'), ...$$(el, '.dl-rb__panel.is-active [data-rb-tip]')].find(b => (b.dataset.rbTip || '').toLowerCase() === e.key.toLowerCase());
          if (t) { e.preventDefault(); t.click(); if (t.matches('[data-rb-tab]')) tips(true); else tips(false); }
        }
      });
      document.addEventListener('keyup', e => { if (hot && e.key === 'Alt' && altAlone) { e.preventDefault(); tips(!tipOn); } });
    }
  }
  Devlib.define("Ribbon", Ribbon);
})(window.Devlib);
