/* DevLib · LayerMenu */
(function (Devlib) {
  'use strict';
  const {esc, toast} = Devlib.h;
  /* ============ LAYER MENU (panel kiri berlapis: modul -> menu -> sub -> ...) ============ */
  class LayerMenu extends Devlib.Component {
    static selector = "[data-dl-layer-menu]";
    init() {
      this.root = JSON.parse(this.el.dataset.tree || '[]');
      this.track = this.$('[data-lm-track]');
      this.dots = this.$('[data-lm-dots]');
      this.key = 'dl-lm-' + this.el.dataset.id;
      this.path = [];     // cabang yang sedang dibuka, path[d-1] = induk layer d
      this.sel = null;    // key layar akhir terpilih
      this.dots.innerHTML = '<i></i>'.repeat(this.depthOf(this.root));
      this.on('click', 'button, a', this.click);
      this.el.addEventListener('keydown', e => { if ((e.key === 'Escape' || e.key === 'ArrowLeft') && this.path.length) { e.preventDefault(); this.back(); } });
      this.restore();
    }

    depthOf(items) { return 1 + Math.max(0, ...items.map(n => n.items ? this.depthOf(n.items) : 0)); }
    count(n) { return n.items ? n.items.reduce((a, x) => a + this.count(x), 0) : 1; }
    nodes(d) { return d ? this.path[d - 1].items : this.root; }

    // Jalur label ke layar akhir berkunci k (dipakai untuk prop active & posisi yang diingat).
    find(k, items = this.root, trail = []) {
      for (const n of items) {
        if (!n.items && n.key === k) return trail;
        if (n.items) { const r = this.find(k, n.items, [...trail, n]); if (r) return r; }
      }
      return null;
    }

    restore() {
      let saved = {};
      try { saved = JSON.parse(localStorage.getItem(this.key)) || {}; } catch (e) {}
      const k = this.el.dataset.active || saved.sel;
      const trail = k ? this.find(k) : null;
      if (trail) { this.path = trail; this.sel = k; }
      else (saved.path || []).every(lbl => { const n = this.nodes(this.path.length).find(x => x.items && x.label === lbl); return n && this.path.push(n); });
      this.draw(false);
    }

    save() { try { localStorage.setItem(this.key, JSON.stringify({path: this.path.map(n => n.label), sel: this.sel})); } catch (e) {} }

    // Satu panel per layer. Tanpa animasi saat pertama tampil (restore) supaya tidak "terbang" dari layer 1.
    draw(animate = true) {
      const d = this.path.length;
      while (this.track.children.length > d + 1) this.track.lastChild.remove();
      for (let i = this.track.children.length; i <= d; i++) this.track.insertAdjacentHTML('beforeend', '<div class="dl-lm__pane"></div>');
      [...this.track.children].forEach((p, i) => { p.innerHTML = this.pane(i); p.inert = i !== d; });
      this.track.classList.toggle('no-anim', !animate);
      this.track.style.transform = `translateX(${-100 * d}%)`;
      [...this.dots.children].forEach((x, i) => x.classList.toggle('is-on', i <= d));
      if (!animate) requestAnimationFrame(() => this.track.classList.remove('no-anim'));
      this.save();
    }

    pane(d) {
      const counts = this.el.dataset.counts === '1';
      let h = '';
      if (d) {
        const p = this.path[d - 1];
        h += `<button type="button" class="dl-lm__back" data-lm-back><i class="fa-solid fa-arrow-left"></i><span>Back</span></button>`
          + `<div class="dl-lm__title"><i class="${esc(p.icon)}"></i><b>${esc(p.label)}</b></div>`
          + `<div class="dl-lm__crumb">${this.path.slice(0, d).map(n => esc(n.label)).join(' <i class="fa-solid fa-angle-right"></i> ')}</div>`;
      } else h += `<div class="dl-lm__cap">${esc(this.el.dataset.root)}</div>`;
      let g = null;
      this.nodes(d).forEach((n, x) => {
        if (n.group && n.group !== g) { g = n.group; h += `<div class="dl-lm__cap">${esc(g)}</div>`; }
        const badge = n.badge ? `<em class="dl-lm__badge is-${esc(n.tone || 'accent')}">${esc(n.badge)}</em>` : '';
        const lbl = `<span class="dl-lm__ico"><i class="${esc(n.icon)}"></i></span><span class="dl-lm__t">${esc(n.label)}</span>${badge}`;
        h += n.items
          ? `<button type="button" class="dl-lm__a" data-lm-x="${x}" title="${esc(n.label)}">${lbl}${counts ? `<span class="dl-lm__n">${this.count(n)}</span>` : ''}<i class="fa-solid fa-chevron-right dl-lm__chev"></i></button>`
          : `<a href="${esc(n.url || '#')}" class="dl-lm__a${n.key === this.sel ? ' is-active' : ''}" data-lm-x="${x}" title="${esc(n.label)}">${lbl}</a>`;
      });
      return h;
    }

    back() { this.path.pop(); this.draw(); this.track.children[this.path.length]?.querySelector('.dl-lm__a')?.focus({preventScroll: true}); }

    click(e, t) {
      if (t.hasAttribute('data-lm-back')) { this.back(); return; }
      if (!t.hasAttribute('data-lm-x')) return;
      const d = [...this.track.children].indexOf(t.closest('.dl-lm__pane'));
      const n = this.nodes(d)[+t.dataset.lmX];
      if (n.items) { this.path = [...this.path.slice(0, d), n]; this.draw(); this.track.children[d + 1].querySelector('[data-lm-back]').focus({preventScroll: true}); return; }
      if (!n.url || n.url === '#') e.preventDefault();
      this.sel = n.key;
      this.$$('.dl-lm__a.is-active').forEach(a => a.classList.remove('is-active'));
      t.classList.add('is-active');
      this.save();
      const detail = {key: n.key, label: n.label, url: n.url || null, path: [...this.path.map(p => p.label), n.label]};
      if (!this.emit('dl:layer-menu', detail, true)) { e.preventDefault(); return; }
      if (!n.url || n.url === '#') toast(`Buka: ${detail.path.join(' › ')} (pratinjau)`);
    }
  }
  Devlib.define("LayerMenu", LayerMenu);
})(window.Devlib);
