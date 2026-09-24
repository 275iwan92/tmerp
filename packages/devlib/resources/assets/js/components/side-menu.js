/* DevLib · SideMenu */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ SIDE MENU (kiri / kanan) ============================ */
  class SideMenu extends Devlib.Component {
    static selector = "[data-dl-side-menu]";
    init() {
      this.key = 'dl-sm-' + this.el.dataset.id;
      this.fav = this.load('-fav', []);
      this.recent = this.load('-recent', []);
      if (this.load('-mini', null) != null) this.el.classList.toggle('is-mini', this.load('-mini', false));
      this.tree = $(this.el, '[data-sm-tree]');
      this.fly = $(this.el, '[data-sm-fly]');
      this.flyTimer = undefined;
      this.tree.addEventListener('mouseover', e => {
        if (!this.mini()) return;
        const n = e.target.closest('[data-sm-node]'); if (!n || n.parentElement.dataset.depth !== '0') return;
        clearTimeout(this.flyTimer); this.showFly(n);
      });
      this.el.addEventListener('mouseleave', () => { if (this.mini()) this.flyTimer = setTimeout(this.hideFly, 200); });
      this.fly.addEventListener('mouseenter', () => clearTimeout(this.flyTimer));
      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-sm-mini]')) { this.el.classList.toggle('is-mini'); this.hideFly(); this.save('-mini', this.mini()); return; }
        const t = x.closest('[data-sm-toggle]');
        if (t) {
          const node = t.parentElement;
          if (this.mini() && !this.fly.contains(t) && node.parentElement.dataset.depth === '0') { this.fly.hidden || this.fly.dataset.for !== $(t, '.dl-sm__t').textContent ? this.showFly(node) : this.hideFly(); return; }
          const on = !node.classList.contains('is-open'); node.classList.toggle('is-open', on); t.setAttribute('aria-expanded', String(on)); return;
        }
        const s = x.closest('[data-sm-star]');
        if (s) { const k = s.dataset.smStar; this.fav = this.fav.includes(k) ? this.fav.filter(z => z !== k) : [...this.fav, k]; this.save('-fav', this.fav); this.extras(); toast(this.fav.includes(k) ? `${this.labelOf(k)} ditambahkan ke Favorit.` : `${this.labelOf(k)} dilepas dari Favorit.`); return; }
        const a = x.closest('[data-sm-key]');
        if (a) {
          const k = a.dataset.smKey;
          if (a.getAttribute('href') === '#') e.preventDefault();
          $$(this.tree, '.dl-sm__leaf.is-active').forEach(l => l.classList.remove('is-active'));
          const l = this.leaf(k); if (l) { l.classList.add('is-active'); this.openPath(l); }
          this.recent = [k, ...this.recent.filter(z => z !== k)].slice(0, 5); this.save('-recent', this.recent); this.extras(); this.hideFly();
          if (this.el.dispatchEvent(new CustomEvent('dl:side-menu', {bubbles: true, cancelable: true, detail: {key: k, label: this.labelOf(k)}}))) toast(`Buka: ${this.labelOf(k)} (pratinjau)`);
        }
      });
      this.el.addEventListener('input', e => {
        if (!e.target.matches('[data-sm-q]')) return;
        const q = e.target.value.trim().toLowerCase();
        $$(this.tree, 'li').forEach(li => li.classList.remove('is-hidden', 'is-hit'));
        if (!q) return;
        $$(this.tree, '.dl-sm__cap').forEach(c => c.classList.add('is-hidden'));
        $$(this.tree, '[data-sm-leaf]').forEach(l => { const hit = $(l, '.dl-sm__t').textContent.toLowerCase().includes(q); l.classList.toggle('is-hidden', !hit); l.classList.toggle('is-hit', hit); if (hit) this.openPath(l); });
        // Nama grup cocok -> seluruh isinya ikut tampil.
        $$(this.tree, '[data-sm-node]').forEach(n => { if ($(n, ':scope > [data-sm-toggle] .dl-sm__t').textContent.toLowerCase().includes(q)) { $$(n, 'li').forEach(li => li.classList.remove('is-hidden')); const l = $(n, '[data-sm-leaf]'); if (l) this.openPath(l); } });
        $$(this.tree, '[data-sm-node]').reverse().forEach(n => n.classList.toggle('is-hidden', !$(n, '[data-sm-leaf]:not(.is-hidden)')));
      });
      this.act = $(this.tree, '.dl-sm__leaf.is-active');
      if (this.act) this.openPath(this.act);
      this.extras();
    }
    load(k, d) { try { return JSON.parse(localStorage.getItem(this.key + k)) ?? d; } catch (e) { return d; } }
    save(k, v) { try { localStorage.setItem(this.key + k, JSON.stringify(v)); } catch (e) {} }

    leaf(k) {
      return $(this.tree, `[data-sm-leaf="${CSS.escape(k)}"]`);
    }

    labelOf(k) {
      return $(this.leaf(k) || this.el, '.dl-sm__t')?.textContent || k;
    }

    openPath(li) { let p = li.parentElement.closest('[data-sm-node]'); while (p) { p.classList.add('is-open'); $(p, ':scope > [data-sm-toggle]').setAttribute('aria-expanded', 'true'); p = p.parentElement.closest('[data-sm-node]'); } }

    mini() {
      return this.el.classList.contains('is-mini');
    }

    extras() {
      const row = k => { const l = this.leaf(k); if (!l) return ''; const a = $(l, '.dl-sm__a'); return `<li class="dl-sm__leaf ${l.classList.contains('is-active') ? 'is-active' : ''}"><a href="${esc(a.getAttribute('href'))}" class="dl-sm__a" data-sm-key="${esc(k)}" title="${esc(this.labelOf(k))}">${$(l, '.dl-sm__ico') ? `<span class="dl-sm__ico">${$(l, '.dl-sm__ico').innerHTML}</span>` : '<span class="dl-sm__dot"></span>'}<span class="dl-sm__t">${esc(this.labelOf(k))}</span></a></li>`; };
      const fb = $(this.el, '[data-sm-fav]'), rb = $(this.el, '[data-sm-recent]');
      if (fb) { $(fb, '[data-sm-favlist]').innerHTML = this.fav.map(row).join(''); fb.hidden = !this.fav.some(this.leaf); }
      if (rb) { $(rb, '[data-sm-recentlist]').innerHTML = this.recent.map(row).join(''); rb.hidden = !this.recent.some(this.leaf); }
      $$(this.tree, '[data-sm-star]').forEach(s => { const on = this.fav.includes(s.dataset.smStar); s.classList.toggle('is-on', on); s.innerHTML = `<i class="fa-${on ? 'solid' : 'regular'} fa-star"></i>`; });
    }

    showFly(node) {
      const btn = $(node, ':scope > [data-sm-toggle]'), sub = $(node, ':scope > .dl-sm__ul');
      this.fly.innerHTML = `<div class="dl-sm__flyhead">${esc($(btn, '.dl-sm__t').textContent)}</div>` + sub.outerHTML;
      $$(this.fly, '.dl-sm__star').forEach(s => s.remove());
      const r = btn.getBoundingClientRect(), base = this.el.getBoundingClientRect();
      this.fly.style.top = Math.max(0, r.top - base.top) + 'px';
      this.fly.hidden = false; this.fly.dataset.for = $(btn, '.dl-sm__t').textContent;
    }

    hideFly() { this.fly.hidden = true; this.fly.innerHTML = ''; }
  }
  Devlib.define("SideMenu", SideMenu);
})(window.Devlib);
