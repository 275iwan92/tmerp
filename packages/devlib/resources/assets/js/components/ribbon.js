/* DevLib · Ribbon */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ RIBBON (Excel) ============================ */
  class Ribbon extends Devlib.Component {
    static selector = "[data-dl-ribbon]";
    init() {
      this.el.addEventListener('click', e => {
        const x = e.target;
        const tab = x.closest('[data-rb-tab]');
        if (tab) { const was = tab.classList.contains('is-active'); this.setTab(tab.dataset.rbTab); if (this.el.classList.contains('is-min')) this.el.classList.toggle('is-float', !(was && this.el.classList.contains('is-float'))); return; }
        if (x.closest('[data-rb-pin]')) { this.el.classList.toggle('is-min'); this.el.classList.remove('is-float'); return; }
        if (x.closest('[data-rb-file]')) { $(this.el, '[data-rb-stage]').hidden = false; return; }
        if (x.closest('[data-rb-stagex]')) { $(this.el, '[data-rb-stage]').hidden = true; return; }
        const drop = x.closest('[data-rb-drop]');
        if (drop) {
          const box = drop.parentElement, open = !box.classList.contains('is-open'); this.closeMenus(); box.classList.toggle('is-open', open);
          // Panel ribbon bisa di-scroll horizontal -> menu dipasang fixed supaya tidak terpotong.
          if (open) { const m = $(box, ':scope > .dl-rb__menu'), r = box.getBoundingClientRect(); m.style.position = 'fixed'; m.style.top = r.bottom + 'px'; m.style.left = Math.max(8, Math.min(r.left, innerWidth - m.offsetWidth - 8)) + 'px'; }
          return;
        }
        const sub = x.closest('[data-rb-sub]');
        if (sub) { sub.parentElement.classList.toggle('is-open'); return; }
        const f = x.closest('[data-rb-run]'); if (f) { const b = $(this.el, `[data-rb-key="${CSS.escape(f.dataset.rbRun)}"]`); $(this.el, '[data-rb-found]').hidden = true; $(this.el, '[data-rb-find]').value = ''; if (b) this.run(b); return; }
        const k = x.closest('[data-rb-key]');
        if (k) { if ($(this.el, '[data-rb-stage]') && k.closest('[data-rb-stage]')) $(this.el, '[data-rb-stage]').hidden = true; return this.run(k); }
      });
      this.el.addEventListener('dblclick', e => { if (e.target.closest('[data-rb-tab]')) { this.el.classList.toggle('is-min'); this.el.classList.remove('is-float'); } });
      document.addEventListener('click', e => { if (!this.el.contains(e.target)) { this.closeMenus(); this.el.classList.remove('is-float'); } });
      window.addEventListener('scroll', e => { if (!(e.target instanceof Element && e.target.closest('.dl-rb__menu'))) this.closeMenus(); }, true);
      // Cari perintah (seperti "Tell me"): semua tombol ber-key dari semua tab + jalurnya.
      this.el.addEventListener('input', e => {
        if (!e.target.matches('[data-rb-find]')) return;
        const q = e.target.value.trim().toLowerCase(), box = $(this.el, '[data-rb-found]');
        if (!q) { box.hidden = true; return; }
        const hits = $$(this.el, '[data-rb-panel] [data-rb-key]').map(b => { const p = this.panelOf(b), g = b.closest('.dl-rb__group'); return {key: b.dataset.rbKey, label: this.labelOf(b), path: `${$(this.el, `[data-rb-tab="${p.dataset.rbPanel}"]`).textContent} › ${g ? $(g, '.dl-rb__glabel').textContent : ''}`}; })
          .filter((h, i, a) => h.label.toLowerCase().includes(q) && a.findIndex(z => z.key === h.key) === i).slice(0, 8);
        box.innerHTML = hits.map(h => `<button type="button" data-rb-run="${esc(h.key)}"><b>${esc(h.label)}</b><small>${esc(h.path)}</small></button>`).join('') || '<p>Tidak ada perintah.</p>';
        box.hidden = false;
      });
      this.tipOn = false;
      this.hot = false;
      this.altAlone = false;
      this.el.addEventListener('pointerenter', () => { this.hot = true; });
      document.addEventListener('pointerdown', e => { this.hot = this.el.contains(e.target); });
      document.addEventListener('keydown', e => {
        if (!this.hot) return;
        if (e.key === 'Alt') { this.altAlone = true; return; }
        this.altAlone = false;
        if (e.ctrlKey && e.key === 'F1') { e.preventDefault(); this.el.classList.toggle('is-min'); return; }
        if (e.key === 'Escape') { this.closeMenus(); this.tips(false); this.el.classList.remove('is-float'); const st = $(this.el, '[data-rb-stage]'); if (st) st.hidden = true; return; }
        if (this.tipOn && /^[a-z0-9]$/i.test(e.key)) {
          const t = [...this.tabs(), ...$$(this.el, '[data-rb-file]'), ...$$(this.el, '.dl-rb__panel.is-active [data-rb-tip]')].find(b => (b.dataset.rbTip || '').toLowerCase() === e.key.toLowerCase());
          if (t) { e.preventDefault(); t.click(); if (t.matches('[data-rb-tab]')) this.tips(true); else this.tips(false); }
        }
      });
      document.addEventListener('keyup', e => { if (this.hot && e.key === 'Alt' && this.altAlone) { e.preventDefault(); this.tips(!this.tipOn); } });
    }

    closeMenus(except) {
      return $$(this.el, '.dl-rb__drop.is-open, .dl-rb__split.is-open, .dl-rb__mi.is-open').forEach(x => { if (!except || !x.contains(except)) x.classList.remove('is-open'); });
    }

    panelOf(b) {
      return b.closest('[data-rb-panel]');
    }

    labelOf(b) {
      return (b.querySelector('.dl-rb__lbl, span:not(.dl-rb__ico):not(.dl-rb__mico)')?.textContent || b.title || b.dataset.rbKey).trim();
    }

    tabs() {
      return $$(this.el, '[data-rb-tab]');
    }

    setTab(key) { this.tabs().forEach(t => t.classList.toggle('is-active', t.dataset.rbTab === key)); $$(this.el, '[data-rb-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.rbPanel === key)); }

    run(b) {
      let on = null;
      if (b.hasAttribute('data-rb-toggle')) {
        const r = b.dataset.rbRadio;
        on = r ? true : b.getAttribute('aria-pressed') !== 'true';
        if (r) $$(this.el, `[data-rb-radio="${r}"]`).forEach(x => { x.setAttribute('aria-pressed', 'false'); x.classList.remove('is-on'); });
        b.setAttribute('aria-pressed', String(on)); b.classList.toggle('is-on', on);
      }
      this.closeMenus(); this.el.classList.remove('is-float'); this.tips(false);
      const ev = new CustomEvent('dl:ribbon', {bubbles: true, cancelable: true, detail: {key: b.dataset.rbKey, label: this.labelOf(b), on}});
      if (this.el.dispatchEvent(ev)) toast(`${this.labelOf(b)}${on == null ? '' : on ? ' — aktif' : ' — mati'} (pratinjau)`);
    }

    tips(on) {
      this.tipOn = on; $$(this.el, '.dl-rb__kt').forEach(t => t.remove());
      if (!on) return;
      const scope = [...this.tabs(), ...$$(this.el, '[data-rb-file]'), ...$$(this.el, '.dl-rb__panel.is-active [data-rb-tip]')];
      scope.filter(b => b.dataset.rbTip).forEach(b => b.insertAdjacentHTML('beforeend', `<span class="dl-rb__kt">${esc(b.dataset.rbTip)}</span>`));
    }
  }
  Devlib.define("Ribbon", Ribbon);
})(window.Devlib);
