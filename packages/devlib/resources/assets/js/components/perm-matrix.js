/* DevLib · PermMatrix */
(function (Devlib) {
  'use strict';
  const {$, esc, toast} = Devlib.h;
  /* ============================ PERMISSION MATRIX ============================ */
  class PermMatrix extends Devlib.Component {
    static selector = "[data-dl-perm]";
    init() {
      const el = this.el;
      const OBJ = JSON.parse(el.dataset.objects || '[]'), LV = JSON.parse(el.dataset.levels || '[]'), DEF = +el.dataset.default || 0;
      const saved = JSON.parse(el.dataset.perms || '{}'), work = JSON.parse(JSON.stringify(saved));
      const gSel = $(el, '[data-pm-group]'), mSel = $(el, '[data-pm-module]'), q = $(el, '[data-pm-q]');
      const get = (src, g, k) => src[g]?.[k] ?? DEF;
      const shown = () => OBJ.filter(o => (!mSel.value || o.module === mSel.value) && (!q.value.trim() || o.name.toLowerCase().includes(q.value.trim().toLowerCase())));
      const dirtyCount = () => Object.keys(work).reduce((n, g) => n + OBJ.filter(o => get(work, g, o.key) !== get(saved, g, o.key)).length, 0);
      function draw() {
        const g = gSel.value;
        $(el, '[data-pm-body]').innerHTML = shown().map(o => { const v = get(work, g, o.key), ch = v !== get(saved, g, o.key); return `<tr class="${ch ? 'is-dirty' : ''}"><td>${esc(o.name)}</td><td><span class="dl-perm__pill is-${esc(String(o.type).toLowerCase())}">${esc(o.type)}</span></td><td>${esc(o.module)}</td><td><select data-pm-obj="${esc(o.key)}" aria-label="Hak akses ${esc(o.name)}" class="is-l${v}">${LV.map((l, i) => `<option value="${i}" ${i === v ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></td></tr>`; }).join('') || '<tr><td colspan="4" class="dl-perm__none">Tidak ada objek.</td></tr>';
        const n = dirtyCount(); $(el, '[data-pm-dirty]').textContent = n ? `${n} perubahan belum disimpan` : 'Tidak ada perubahan';
        el.classList.toggle('is-dirty', n > 0);
      }
      const set = (k, v) => { (work[gSel.value] ||= {})[k] = +v; };
      el.addEventListener('change', e => {
        if (e.target.matches('[data-pm-obj]')) { set(e.target.dataset.pmObj, e.target.value); return draw(); }
        if (e.target.matches('[data-pm-bulk]') && e.target.value !== '') { shown().forEach(o => set(o.key, e.target.value)); toast(`${shown().length} objek diset ke: ${LV[+e.target.value]}`); e.target.value = ''; return draw(); }
        if (e.target === gSel || e.target === mSel) draw();
      });
      q.addEventListener('input', draw);
      el.addEventListener('click', e => {
        if (e.target.closest('[data-pm-reset]')) { Object.keys(work).forEach(k => delete work[k]); Object.assign(work, JSON.parse(JSON.stringify(saved))); return draw(); }
        if (e.target.closest('[data-pm-save]')) {
          const perms = Object.fromEntries(OBJ.map(o => [o.key, get(work, gSel.value, o.key)]));
          Object.keys(work).forEach(g => { saved[g] = {...(saved[g] || {}), ...work[g]}; });
          el.dispatchEvent(new CustomEvent('dl:permissions-save', {bubbles: true, detail: {group: gSel.value, perms, all: work}}));
          toast('Hak akses grup disimpan.', 'success'); draw();
        }
      });
      draw();
    }
  }
  Devlib.define("PermMatrix", PermMatrix);
})(window.Devlib);
