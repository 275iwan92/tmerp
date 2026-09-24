/* DevLib · SideMenu */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ SIDE MENU (kiri / kanan) ============================ */
  class SideMenu extends Devlib.Component {
    static selector = "[data-dl-side-menu]";
    init() {
      const el = this.el;
      const key = 'dl-sm-' + el.dataset.id;
      const load = (k, d) => { try { return JSON.parse(localStorage.getItem(key + k)) ?? d; } catch (e) { return d; } };
      const save = (k, v) => { try { localStorage.setItem(key + k, JSON.stringify(v)); } catch (e) {} };
      let fav = load('-fav', []), recent = load('-recent', []);
      if (load('-mini', null) != null) el.classList.toggle('is-mini', load('-mini', false));
      const tree = $(el, '[data-sm-tree]'), fly = $(el, '[data-sm-fly]');
      const leaf = k => $(tree, `[data-sm-leaf="${CSS.escape(k)}"]`);
      const labelOf = k => $(leaf(k) || el, '.dl-sm__t')?.textContent || k;
      const openPath = li => { let p = li.parentElement.closest('[data-sm-node]'); while (p) { p.classList.add('is-open'); $(p, ':scope > [data-sm-toggle]').setAttribute('aria-expanded', 'true'); p = p.parentElement.closest('[data-sm-node]'); } };
      const mini = () => el.classList.contains('is-mini');
      function extras() {
        const row = k => { const l = leaf(k); if (!l) return ''; const a = $(l, '.dl-sm__a'); return `<li class="dl-sm__leaf ${l.classList.contains('is-active') ? 'is-active' : ''}"><a href="${esc(a.getAttribute('href'))}" class="dl-sm__a" data-sm-key="${esc(k)}" title="${esc(labelOf(k))}">${$(l, '.dl-sm__ico') ? `<span class="dl-sm__ico">${$(l, '.dl-sm__ico').innerHTML}</span>` : '<span class="dl-sm__dot"></span>'}<span class="dl-sm__t">${esc(labelOf(k))}</span></a></li>`; };
        const fb = $(el, '[data-sm-fav]'), rb = $(el, '[data-sm-recent]');
        if (fb) { $(fb, '[data-sm-favlist]').innerHTML = fav.map(row).join(''); fb.hidden = !fav.some(leaf); }
        if (rb) { $(rb, '[data-sm-recentlist]').innerHTML = recent.map(row).join(''); rb.hidden = !recent.some(leaf); }
        $$(tree, '[data-sm-star]').forEach(s => { const on = fav.includes(s.dataset.smStar); s.classList.toggle('is-on', on); s.innerHTML = `<i class="fa-${on ? 'solid' : 'regular'} fa-star"></i>`; });
      }
      function showFly(node) {
        const btn = $(node, ':scope > [data-sm-toggle]'), sub = $(node, ':scope > .dl-sm__ul');
        fly.innerHTML = `<div class="dl-sm__flyhead">${esc($(btn, '.dl-sm__t').textContent)}</div>` + sub.outerHTML;
        $$(fly, '.dl-sm__star').forEach(s => s.remove());
        const r = btn.getBoundingClientRect(), base = el.getBoundingClientRect();
        fly.style.top = Math.max(0, r.top - base.top) + 'px';
        fly.hidden = false; fly.dataset.for = $(btn, '.dl-sm__t').textContent;
      }
      const hideFly = () => { fly.hidden = true; fly.innerHTML = ''; };
      let flyTimer;
      tree.addEventListener('mouseover', e => {
        if (!mini()) return;
        const n = e.target.closest('[data-sm-node]'); if (!n || n.parentElement.dataset.depth !== '0') return;
        clearTimeout(flyTimer); showFly(n);
      });
      el.addEventListener('mouseleave', () => { if (mini()) flyTimer = setTimeout(hideFly, 200); });
      fly.addEventListener('mouseenter', () => clearTimeout(flyTimer));
      el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-sm-mini]')) { el.classList.toggle('is-mini'); hideFly(); save('-mini', mini()); return; }
        const t = x.closest('[data-sm-toggle]');
        if (t) {
          const node = t.parentElement;
          if (mini() && !fly.contains(t) && node.parentElement.dataset.depth === '0') { fly.hidden || fly.dataset.for !== $(t, '.dl-sm__t').textContent ? showFly(node) : hideFly(); return; }
          const on = !node.classList.contains('is-open'); node.classList.toggle('is-open', on); t.setAttribute('aria-expanded', String(on)); return;
        }
        const s = x.closest('[data-sm-star]');
        if (s) { const k = s.dataset.smStar; fav = fav.includes(k) ? fav.filter(z => z !== k) : [...fav, k]; save('-fav', fav); extras(); toast(fav.includes(k) ? `${labelOf(k)} ditambahkan ke Favorit.` : `${labelOf(k)} dilepas dari Favorit.`); return; }
        const a = x.closest('[data-sm-key]');
        if (a) {
          const k = a.dataset.smKey;
          if (a.getAttribute('href') === '#') e.preventDefault();
          $$(tree, '.dl-sm__leaf.is-active').forEach(l => l.classList.remove('is-active'));
          const l = leaf(k); if (l) { l.classList.add('is-active'); openPath(l); }
          recent = [k, ...recent.filter(z => z !== k)].slice(0, 5); save('-recent', recent); extras(); hideFly();
          if (el.dispatchEvent(new CustomEvent('dl:side-menu', {bubbles: true, cancelable: true, detail: {key: k, label: labelOf(k)}}))) toast(`Buka: ${labelOf(k)} (pratinjau)`);
        }
      });
      el.addEventListener('input', e => {
        if (!e.target.matches('[data-sm-q]')) return;
        const q = e.target.value.trim().toLowerCase();
        $$(tree, 'li').forEach(li => li.classList.remove('is-hidden', 'is-hit'));
        if (!q) return;
        $$(tree, '.dl-sm__cap').forEach(c => c.classList.add('is-hidden'));
        $$(tree, '[data-sm-leaf]').forEach(l => { const hit = $(l, '.dl-sm__t').textContent.toLowerCase().includes(q); l.classList.toggle('is-hidden', !hit); l.classList.toggle('is-hit', hit); if (hit) openPath(l); });
        // Nama grup cocok -> seluruh isinya ikut tampil.
        $$(tree, '[data-sm-node]').forEach(n => { if ($(n, ':scope > [data-sm-toggle] .dl-sm__t').textContent.toLowerCase().includes(q)) { $$(n, 'li').forEach(li => li.classList.remove('is-hidden')); const l = $(n, '[data-sm-leaf]'); if (l) openPath(l); } });
        $$(tree, '[data-sm-node]').reverse().forEach(n => n.classList.toggle('is-hidden', !$(n, '[data-sm-leaf]:not(.is-hidden)')));
      });
      const act = $(tree, '.dl-sm__leaf.is-active'); if (act) openPath(act);
      extras();
    }
  }
  Devlib.define("SideMenu", SideMenu);
})(window.Devlib);
