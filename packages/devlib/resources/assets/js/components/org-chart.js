/* DevLib · OrgChart */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ ORG CHART ============================ */
  class OrgChart extends Devlib.Component {
    static selector = "[data-dl-org]";
    init() {
      const el = this.el;
      const nodes = JSON.parse(el.dataset.nodes || '[]'), edit = el.dataset.editable === '1', tree = $(el, '[data-o-tree]'), canvas = $(el, '[data-o-canvas]');
      const undo = [], collapsed = new Set();
      let zoom = 1, q = '', drag = null;
      const ppl = n => (n.people || []).map(p => typeof p === 'string' ? {name: p} : p);
      const kids = id => nodes.filter(n => String(n.parent ?? '') === String(id ?? ''));
      const byId = id => nodes.find(n => String(n.id) === String(id));
      const isUnder = (id, anc) => { for (let n = byId(id); n && n.parent != null; n = byId(n.parent)) if (String(n.parent) === String(anc)) return true; return false; };
      const hit = n => q && (n.title + ' ' + (n.dept || '') + ' ' + ppl(n).map(p => p.name).join(' ')).toLowerCase().includes(q);
      const ini = s => String(s).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const hue = s => [...String(s)].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 0);
      const av = p => p.photo ? `<img src="${esc(p.photo)}" alt="">` : esc(ini(p.name));
      function card(n) {
        const P = ppl(n), c = kids(n.id);
        return `<div class="dl-org__card ${P.length ? '' : 'is-vacant'} ${hit(n) ? 'is-hit' : ''}" data-o-node="${esc(n.id)}" ${edit ? 'data-o-drop' : ''}>
          ${edit ? `<span class="dl-org__grip" draggable="true" data-o-grip title="Tarik ke atasan baru"><i class="fa-solid fa-grip-vertical"></i></span>` : ''}
          <div class="dl-org__who">${P.length ? `<span class="dl-org__avs">${P.slice(0, 3).map(p => `<span class="dl-org__av" style="--h:${hue(p.name)}" title="${esc(p.name)}">${av(p)}</span>`).join('')}</span>` : '<span class="dl-org__av is-empty"><i class="fa-solid fa-user-plus"></i></span>'}
            <span class="dl-org__txt"><b>${P.length ? esc(P.map(p => p.name).slice(0, 2).join(', ')) + (P.length > 2 ? ` +${P.length - 2}` : '') : 'Lowong'}</b><small>${esc(n.title)}</small>${n.dept ? `<em>${esc(n.dept)}</em>` : ''}</span></div>
          ${c.length ? `<button type="button" class="dl-org__fold" data-o-fold title="${collapsed.has(String(n.id)) ? 'Buka' : 'Lipat'} cabang">${collapsed.has(String(n.id)) ? `+${c.length}` : '−'}</button>` : ''}
        </div>`;
      }
      const branch = n => { const c = kids(n.id), open = !collapsed.has(String(n.id)); return `<li>${card(n)}${c.length && open ? `<ul>${c.map(branch).join('')}</ul>` : ''}</li>`; };
      function render() {
        tree.innerHTML = `<ul>${kids(null).map(branch).join('')}</ul>`;
        tree.style.transform = `scale(${zoom})`; $(el, '[data-o-zoomval]').textContent = Math.round(zoom * 100) + '%';
        const u = $(el, '[data-o-undo]'); if (u) u.disabled = !undo.length;
      }
      function move(id, to) {
        const n = byId(id);
        if (!n || String(n.id) === String(to) || String(n.parent) === String(to)) return;
        if (isUnder(to, id)) { toast('Tidak bisa dipindah ke bawahannya sendiri.', 'danger'); return; }
        undo.push([n.id, n.parent]); const from = n.parent; n.parent = to; collapsed.delete(String(to));
        render(); toast(`${n.title} kini di bawah ${byId(to).title}.`, 'success');
        el.dispatchEvent(new CustomEvent('dl:orgchart', {bubbles: true, detail: {id: n.id, from, to}}));
      }
      el.addEventListener('dragstart', e => { const g = e.target.closest('[data-o-grip]'); if (!g) return; drag = g.closest('[data-o-node]').dataset.oNode; g.closest('.dl-org__card').classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); e.dataTransfer.setDragImage(g.closest('.dl-org__card'), 20, 20); } catch (x) {} });
      el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-target,.is-deny').forEach(x => x.classList.remove('is-drag', 'is-target', 'is-deny')); });
      el.addEventListener('dragover', e => { const t = e.target.closest('[data-o-drop]'); if (!t || drag == null) return; e.preventDefault(); $$(el, '.is-target,.is-deny').forEach(x => x.classList.remove('is-target', 'is-deny')); const bad = t.dataset.oNode === drag || isUnder(t.dataset.oNode, drag); t.classList.add(bad ? 'is-deny' : 'is-target'); e.dataTransfer.dropEffect = bad ? 'none' : 'move'; });
      el.addEventListener('drop', e => { const t = e.target.closest('[data-o-drop]'); if (!t || drag == null) return; e.preventDefault(); const d = drag; drag = null; move(d, t.dataset.oNode); });
      el.addEventListener('click', e => {
        const x = e.target;
        const f = x.closest('[data-o-fold]'); if (f) { const id = f.closest('[data-o-node]').dataset.oNode; collapsed.has(id) ? collapsed.delete(id) : collapsed.add(id); return render(); }
        if (x.closest('[data-o-undo]')) { const u = undo.pop(); if (u) { byId(u[0]).parent = u[1]; render(); toast('Perubahan terakhir dibatalkan.'); } return; }
        if (x.closest('[data-o-save]')) { el.dispatchEvent(new CustomEvent('dl:orgchart-save', {bubbles: true, detail: {nodes: nodes.map(n => ({id: n.id, parent: n.parent ?? null}))}})); toast(`Struktur disimpan (${undo.length} perubahan).`, 'success'); undo.length = 0; return render(); }
        const z = x.closest('[data-o-zoom]');
        if (z) { const k = +z.dataset.oZoom; if (k) zoom = Math.min(1.6, Math.max(0.4, Math.round((zoom + k * 0.1) * 10) / 10)); else { tree.style.transform = 'scale(1)'; zoom = Math.max(0.4, Math.min(1, canvas.clientWidth / tree.scrollWidth)); } return render(); }
        if (x.closest('[data-o-html]')) {
          const css = [...document.querySelectorAll('link[rel=stylesheet]')].map(l => `<link rel="stylesheet" href="${l.href}">`).join('');
          const html = `<!doctype html><html lang="id"><head><meta charset="utf-8"><title>${esc(el.dataset.title)}</title>${css}<style>body{margin:24px;font-family:sans-serif}.dl-org__grip,.dl-org__fold{display:none}</style></head><body><h1>${esc(el.dataset.title)}</h1><div class="dl-org"><div class="dl-org__tree">${tree.innerHTML}</div></div></body></html>`;
          const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([html], {type: 'text/html'})); a.download = 'struktur-organisasi.html'; a.click();
        }
      });
      $(el, '[data-o-search]').addEventListener('input', e => {
        q = e.target.value.trim().toLowerCase();
        if (q) nodes.filter(hit).forEach(n => { for (let p = byId(n.parent); p; p = byId(p.parent)) collapsed.delete(String(p.id)); });
        render();
        const first = $(tree, '.is-hit'); if (first) first.scrollIntoView({block: 'nearest', inline: 'center', behavior: 'smooth'});
      });
      el.dlOrg = {nodes, move, render};
      render();
      canvas.scrollLeft = (canvas.scrollWidth - canvas.clientWidth) / 2;  // mulai dari puncak (tengah)
    }
  }
  Devlib.define("OrgChart", OrgChart);
})(window.Devlib);
