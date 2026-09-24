/* DevLib · OrgChart */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ ORG CHART ============================ */
  class OrgChart extends Devlib.Component {
    static selector = "[data-dl-org]";
    init() {
      this.nodes = JSON.parse(this.el.dataset.nodes || '[]');
      this.edit = this.el.dataset.editable === '1';
      this.tree = $(this.el, '[data-o-tree]');
      this.canvas = $(this.el, '[data-o-canvas]');
      this.undo = [];
      this.collapsed = new Set();
      this.zoom = 1;
      this.q = '';
      this.drag = null;
      this.el.addEventListener('dragstart', e => { const g = e.target.closest('[data-o-grip]'); if (!g) return; this.drag = g.closest('[data-o-node]').dataset.oNode; g.closest('.dl-org__card').classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', this.drag); e.dataTransfer.setDragImage(g.closest('.dl-org__card'), 20, 20); } catch (x) {} });
      this.el.addEventListener('dragend', () => { this.drag = null; $$(this.el, '.is-drag,.is-target,.is-deny').forEach(x => x.classList.remove('is-drag', 'is-target', 'is-deny')); });
      this.el.addEventListener('dragover', e => { const t = e.target.closest('[data-o-drop]'); if (!t || this.drag == null) return; e.preventDefault(); $$(this.el, '.is-target,.is-deny').forEach(x => x.classList.remove('is-target', 'is-deny')); const bad = t.dataset.oNode === this.drag || this.isUnder(t.dataset.oNode, this.drag); t.classList.add(bad ? 'is-deny' : 'is-target'); e.dataTransfer.dropEffect = bad ? 'none' : 'move'; });
      this.el.addEventListener('drop', e => { const t = e.target.closest('[data-o-drop]'); if (!t || this.drag == null) return; e.preventDefault(); const d = this.drag; this.drag = null; this.move(d, t.dataset.oNode); });
      this.el.addEventListener('click', e => {
        const x = e.target;
        const f = x.closest('[data-o-fold]'); if (f) { const id = f.closest('[data-o-node]').dataset.oNode; this.collapsed.has(id) ? this.collapsed.delete(id) : this.collapsed.add(id); return this.render(); }
        if (x.closest('[data-o-undo]')) { const u = this.undo.pop(); if (u) { this.byId(u[0]).parent = u[1]; this.render(); toast('Perubahan terakhir dibatalkan.'); } return; }
        if (x.closest('[data-o-save]')) { this.el.dispatchEvent(new CustomEvent('dl:orgchart-save', {bubbles: true, detail: {nodes: this.nodes.map(n => ({id: n.id, parent: n.parent ?? null}))}})); toast(`Struktur disimpan (${this.undo.length} perubahan).`, 'success'); this.undo.length = 0; return this.render(); }
        const z = x.closest('[data-o-zoom]');
        if (z) { const k = +z.dataset.oZoom; if (k) this.zoom = Math.min(1.6, Math.max(0.4, Math.round((this.zoom + k * 0.1) * 10) / 10)); else { this.tree.style.transform = 'scale(1)'; this.zoom = Math.max(0.4, Math.min(1, this.canvas.clientWidth / this.tree.scrollWidth)); } return this.render(); }
        if (x.closest('[data-o-html]')) {
          const css = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => `<link rel="stylesheet" href="${l.href}">`).join('');
          const html = `<!doctype html><html lang="id"><head><meta charset="utf-8"><title>${esc(this.el.dataset.title)}</title>${css}<style>body{margin:24px;font-family:sans-serif}.dl-org__grip,.dl-org__fold{display:none}</style></head><body><h1>${esc(this.el.dataset.title)}</h1><div class="dl-org"><div class="dl-org__tree">${this.tree.innerHTML}</div></div></body></html>`;
          const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([html], {type: 'text/html'})); a.download = 'struktur-organisasi.html'; a.click();
        }
      });
      $(this.el, '[data-o-search]').addEventListener('input', e => {
        this.q = e.target.value.trim().toLowerCase();
        if (this.q) this.nodes.filter(this.hit).forEach(n => { for (let p = this.byId(n.parent); p; p = this.byId(p.parent)) this.collapsed.delete(String(p.id)); });
        this.render();
        const first = $(this.tree, '.is-hit'); if (first) first.scrollIntoView({block: 'nearest', inline: 'center', behavior: 'smooth'});
      });
      this.el.dlOrg = {nodes: this.nodes, move: this.move, render: this.render};
      this.render();
      this.canvas.scrollLeft = (this.canvas.scrollWidth - this.canvas.clientWidth) / 2;  // mulai dari puncak (tengah)
    }

    ppl(n) {
      return (n.people || []).map(p => typeof p === 'string' ? {name: p} : p);
    }

    kids(id) {
      return this.nodes.filter(n => String(n.parent ?? '') === String(id ?? ''));
    }

    byId(id) {
      return this.nodes.find(n => String(n.id) === String(id));
    }

    isUnder(id, anc) { for (let n = this.byId(id); n && n.parent != null; n = this.byId(n.parent)) if (String(n.parent) === String(anc)) return true; return false; }

    hit(n) {
      return this.q && (n.title + ' ' + (n.dept || '') + ' ' + this.ppl(n).map(p => p.name).join(' ')).toLowerCase().includes(this.q);
    }

    ini(s) {
      return String(s).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    hue(s) {
      return [...String(s)].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 0);
    }

    av(p) {
      return p.photo ? `<img src="${esc(p.photo)}" alt="">` : esc(this.ini(p.name));
    }

    card(n) {
      const P = this.ppl(n), c = this.kids(n.id);
      return `<div class="dl-org__card ${P.length ? '' : 'is-vacant'} ${this.hit(n) ? 'is-hit' : ''}" data-o-node="${esc(n.id)}" ${this.edit ? 'data-o-drop' : ''}>
        ${this.edit ? `<span class="dl-org__grip" draggable="true" data-o-grip title="Tarik ke atasan baru"><i class="fa-solid fa-grip-vertical"></i></span>` : ''}
        <div class="dl-org__who">${P.length ? `<span class="dl-org__avs">${P.slice(0, 3).map(p => `<span class="dl-org__av" style="--h:${this.hue(p.name)}" title="${esc(p.name)}">${this.av(p)}</span>`).join('')}</span>` : '<span class="dl-org__av is-empty"><i class="fa-solid fa-user-plus"></i></span>'}
          <span class="dl-org__txt"><b>${P.length ? esc(P.map(p => p.name).slice(0, 2).join(', ')) + (P.length > 2 ? ` +${P.length - 2}` : '') : 'Lowong'}</b><small>${esc(n.title)}</small>${n.dept ? `<em>${esc(n.dept)}</em>` : ''}</span></div>
        ${c.length ? `<button type="button" class="dl-org__fold" data-o-fold title="${this.collapsed.has(String(n.id)) ? 'Buka' : 'Lipat'} cabang">${this.collapsed.has(String(n.id)) ? `+${c.length}` : '−'}</button>` : ''}
      </div>`;
    }

    branch(n) { const c = this.kids(n.id), open = !this.collapsed.has(String(n.id)); return `<li>${this.card(n)}${c.length && open ? `<ul>${c.map(this.branch).join('')}</ul>` : ''}</li>`; }

    render() {
      this.tree.innerHTML = `<ul>${this.kids(null).map(this.branch).join('')}</ul>`;
      this.tree.style.transform = `scale(${this.zoom})`; $(this.el, '[data-o-zoomval]').textContent = Math.round(this.zoom * 100) + '%';
      const u = $(this.el, '[data-o-undo]'); if (u) u.disabled = !this.undo.length;
    }

    move(id, to) {
      const n = this.byId(id);
      if (!n || String(n.id) === String(to) || String(n.parent) === String(to)) return;
      if (this.isUnder(to, id)) { toast('Tidak bisa dipindah ke bawahannya sendiri.', 'danger'); return; }
      this.undo.push([n.id, n.parent]); const from = n.parent; n.parent = to; this.collapsed.delete(String(to));
      this.render(); toast(`${n.title} kini di bawah ${this.byId(to).title}.`, 'success');
      this.el.dispatchEvent(new CustomEvent('dl:orgchart', {bubbles: true, detail: {id: n.id, from, to}}));
    }
  }
  Devlib.define("OrgChart", OrgChart);
})(window.Devlib);
