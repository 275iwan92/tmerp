/* DevLib · Roster */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ ROSTER BOARD ============================ */
  class Roster extends Devlib.Component {
    static selector = "[data-dl-roster]";
    init() {
      const el = this.el;
      const shifts = JSON.parse(el.dataset.shifts || '[]'), days = JSON.parse(el.dataset.days || '[]'), people = JSON.parse(el.dataset.people || '[]');
      const assign = JSON.parse(el.dataset.assign || '{}'), picked = new Set(), shut = new Set();
      let q = '', drag = null;
      const ini = n => String(n).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const hue = s => [...String(s)].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 0);
      const hrs = (a, b) => { const [h1, m1] = a.split(':').map(Number), [h2, m2] = b.split(':').map(Number); let d = (h2 * 60 + m2) - (h1 * 60 + m1); if (d <= 0) d += 1440; return d / 60; };
      const weekHours = s => days.reduce((t, d) => t + (s.pattern[d.key] ? Math.max(0, hrs(...s.pattern[d.key]) - 1) : 0), 0);  // dipotong 1 jam istirahat
      const workDays = s => days.filter(d => s.pattern[d.key]).length;
      const chip = p => `<span class="dl-roster__chip" draggable="true" data-r-person="${esc(p.id)}" title="${esc(p.name)} · ${esc(p.code)}"><i style="--h:${hue(p.name)}">${esc(ini(p.name))}</i><b>${esc(p.name)}</b><small>${esc(p.code)} · ${esc(p.role)}</small><em>${workDays(shifts.find(s => s.key === assign[p.id]))}h</em><button type="button" data-r-remove title="Lepas dari shift">&times;</button></span>`;
      function grid() {
        const head = `<div class="dl-roster__row is-head"><div class="dl-roster__shift">Shift / Pola Jam</div>${days.map(d => `<div class="dl-roster__day ${d.off ? 'is-off' : ''}"><b>${esc(d.key)}</b><small>${esc(d.date || '')}</small></div>`).join('')}</div>`;
        $(el, '[data-r-grid]').innerHTML = head + shifts.map(s => {
          const list = people.filter(p => assign[p.id] === s.key), cap = +s.capacity || 0, pct = cap ? Math.min(100, list.length / cap * 100) : 0;
          const pat = days.filter(d => s.pattern[d.key]).map(d => d.key);
          return `<div class="dl-roster__row dl-roster--${esc(s.tone || 'accent')}" data-r-shift="${esc(s.key)}">
            <div class="dl-roster__shift"><b><i></i>${esc(s.label)}</b><small>${esc(pat.length === days.length ? 'Setiap hari' : pat[0] + '–' + pat[pat.length - 1])} · ${workDays(s)} hari · ${Math.round(weekHours(s) * 10) / 10}j/minggu</small>
              ${cap ? `<span class="dl-roster__cap ${list.length > cap ? 'is-over' : ''}"><span style="width:${pct}%"></span></span><em class="${list.length > cap ? 'is-over' : ''}">${list.length}/${cap}</em>` : ''}</div>
            ${days.map(d => `<div class="dl-roster__time ${s.pattern[d.key] ? '' : 'is-off'}">${s.pattern[d.key] ? `${esc(s.pattern[d.key][0])}<br><small>${esc(s.pattern[d.key][1])}${hrs(...s.pattern[d.key]) && s.pattern[d.key][1] < s.pattern[d.key][0] ? ' +1' : ''}</small>` : 'LIBUR'}</div>`).join('')}
            <div class="dl-roster__drop" data-r-drop="${esc(s.key)}">${list.map(chip).join('') || '<span class="dl-roster__hint">Tarik pegawai ke sini — otomatis mengikuti pola jam shift ini</span>'}</div>
          </div>`;
        }).join('');
      }
      function pool() {
        const free = people.filter(p => !assign[p.id] && (!q || (p.name + ' ' + p.code).toLowerCase().includes(q)));
        const groups = [...new Set(free.map(p => p.role))];
        $(el, '[data-r-poolcount]').textContent = `${free.length} dari ${people.length} pegawai belum terjadwal`;
        $(el, '[data-r-pool]').innerHTML = groups.map(g => { const list = free.filter(p => p.role === g), all = list.every(p => picked.has(String(p.id))); return `<div class="dl-roster__grp"><button type="button" class="dl-roster__grphead" data-r-grp="${esc(g)}"><input type="checkbox" data-r-grpall="${esc(g)}" ${all && list.length ? 'checked' : ''} aria-label="Pilih semua ${esc(g)}"><i class="fa-solid fa-caret-${shut.has(g) ? 'right' : 'down'}"></i><b>${esc(g)}</b><small>${list.length} org</small></button>
          ${shut.has(g) ? '' : list.map(p => `<label class="dl-roster__person ${picked.has(String(p.id)) ? 'is-picked' : ''}" draggable="true" data-r-person="${esc(p.id)}"><input type="checkbox" data-r-pick="${esc(p.id)}" ${picked.has(String(p.id)) ? 'checked' : ''}><i style="--h:${hue(p.name)}">${esc(ini(p.name))}</i><span><b>${esc(p.name)}</b><small>${esc(p.code)} · ${p.skills ?? 0} skill</small></span><em>${esc(p.role)}</em></label>`).join('')}</div>`; }).join('') || '<div class="dl-empty"><b class="dl-empty__title">Semua pegawai sudah terjadwal</b></div>';
        $(el, '[data-r-selbar]').hidden = !picked.size; $(el, '[data-r-selcount]').textContent = picked.size;
        const sched = Object.values(assign).filter(Boolean).length;
        $(el, '[data-r-stats]').innerHTML = `<span><b>${free.length}</b>Pool tampil</span><span class="is-good"><b>${sched}</b>Terjadwal</span><span class="is-warn"><b>${people.length - sched}</b>Belum</span>`;
      }
      const render = () => { grid(); pool(); };
      const put = (ids, key) => {
        const s = shifts.find(x => x.key === key); ids.forEach(id => { assign[id] = key; picked.delete(String(id)); });
        render(); el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...assign}}}));
        const n = people.filter(p => assign[p.id] === key).length; if (s.capacity && n > s.capacity) toast(`${s.label} melebihi kapasitas (${n}/${s.capacity}).`, 'warn');
      };
      el.addEventListener('dragstart', e => { const c = e.target.closest('[data-r-person]'); if (!c) return; drag = c.dataset.rPerson; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); } catch (x) {} });
      el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
      el.addEventListener('dragover', e => { const d = e.target.closest('[data-r-drop],[data-r-pool]'); if (!d || drag == null) return; e.preventDefault(); $$(el, '.is-over').forEach(x => x.classList.remove('is-over')); d.classList.add('is-over'); });
      el.addEventListener('drop', e => {
        const d = e.target.closest('[data-r-drop],[data-r-pool]'); if (!d || drag == null) return; e.preventDefault();
        const id = drag; drag = null;
        if (d.hasAttribute('data-r-pool')) { delete assign[id]; render(); el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...assign}}})); }
        else put([id], d.dataset.rDrop);
      });
      el.addEventListener('click', e => {
        const x = e.target;
        const rm = x.closest('[data-r-remove]'); if (rm) { delete assign[rm.closest('[data-r-person]').dataset.rPerson]; render(); el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...assign}}})); return; }
        const ga = x.closest('[data-r-grpall]'); if (ga) { e.stopPropagation(); const list = people.filter(p => !assign[p.id] && p.role === ga.dataset.rGrpall); list.forEach(p => ga.checked ? picked.add(String(p.id)) : picked.delete(String(p.id))); return pool(); }
        const g = x.closest('[data-r-grp]'); if (g) { const k = g.dataset.rGrp; shut.has(k) ? shut.delete(k) : shut.add(k); return pool(); }
        if (x.closest('[data-r-selnone]')) { picked.clear(); return pool(); }
        const row = x.closest('[data-r-shift]'); if (row && picked.size && !x.closest('[data-r-person]')) { const n = picked.size; put([...picked], row.dataset.rShift); toast(`${n} pegawai ditempatkan di ${shifts.find(s => s.key === row.dataset.rShift).label}.`, 'success'); return; }
        const wk = x.closest('[data-r-week]'); if (wk) { el.dispatchEvent(new CustomEvent('dl:roster-week', {bubbles: true, detail: {step: +wk.dataset.rWeek}})); toast('Pindah minggu (layar pemakai memuat data minggu itu).'); return; }
        if (x.closest('[data-r-save]')) { el.dispatchEvent(new CustomEvent('dl:roster-save', {bubbles: true, detail: {assign: {...assign}}})); toast('Roster minggu ini disimpan.', 'success'); }
      });
      el.addEventListener('change', e => { const p = e.target.closest('[data-r-pick]'); if (p) { p.checked ? picked.add(p.dataset.rPick) : picked.delete(p.dataset.rPick); pool(); } });
      $(el, '[data-r-search]').addEventListener('input', e => { q = e.target.value.trim().toLowerCase(); pool(); });
      el.dlRoster = {assign, render, put};
      render();
    }
  }
  Devlib.define("Roster", Roster);
})(window.Devlib);
