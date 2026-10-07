/* DevLib · Roster */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  /* ============================ ROSTER BOARD ============================ */
  class Roster extends Devlib.Component {
    static selector = "[data-dl-roster]";
    init() {
      this.shifts = JSON.parse(this.el.dataset.shifts || '[]');
      this.days = JSON.parse(this.el.dataset.days || '[]');
      this.people = JSON.parse(this.el.dataset.people || '[]');
      this.assign = JSON.parse(this.el.dataset.assign || '{}');
      this.locked = this.el.dataset.locked === '1';
      this.el.classList.toggle('is-locked', this.locked);
      this.picked = new Set();
      this.shut = new Set();
      this.q = '';
      this.drag = null;
      this.el.addEventListener('dragstart', e => { const c = e.target.closest('[data-r-person]'); if (!c || this.locked) return; this.drag = c.dataset.rPerson; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', this.drag); } catch (x) {} });
      this.el.addEventListener('dragend', () => { this.drag = null; $$(this.el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
      this.el.addEventListener('dragover', e => { const d = e.target.closest('[data-r-drop],[data-r-pool]'); if (!d || this.drag == null) return; e.preventDefault(); $$(this.el, '.is-over').forEach(x => x.classList.remove('is-over')); d.classList.add('is-over'); });
      this.el.addEventListener('drop', e => {
        const d = e.target.closest('[data-r-drop],[data-r-pool]'); if (!d || this.drag == null) return; e.preventDefault();
        const id = this.drag; this.drag = null;
        if (d.hasAttribute('data-r-pool')) { delete this.assign[id]; this.render(); this.el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...this.assign}}})); }
        else this.put([id], d.dataset.rDrop);
      });
      this.el.addEventListener('click', e => {
        const x = e.target;
        const rm = x.closest('[data-r-remove]'); if (rm && this.locked) return; if (rm) { delete this.assign[rm.closest('[data-r-person]').dataset.rPerson]; this.render(); this.el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...this.assign}}})); return; }
        const ga = x.closest('[data-r-grpall]'); if (ga) { e.stopPropagation(); const list = this.people.filter(p => !this.assign[p.id] && p.role === ga.dataset.rGrpall); list.forEach(p => ga.checked ? this.picked.add(String(p.id)) : this.picked.delete(String(p.id))); return this.pool(); }
        const g = x.closest('[data-r-grp]'); if (g) { const k = g.dataset.rGrp; this.shut.has(k) ? this.shut.delete(k) : this.shut.add(k); return this.pool(); }
        if (x.closest('[data-r-selnone]')) { this.picked.clear(); return this.pool(); }
        const row = x.closest('[data-r-shift]'); if (row && this.picked.size && !x.closest('[data-r-person]')) { const n = this.picked.size; this.put([...this.picked], row.dataset.rShift); toast(Devlib.h.t(':n employees placed in :shift.').replace(':n', n).replace(':shift', this.shifts.find(s => s.key === row.dataset.rShift).label), 'success'); return; }
        const wk = x.closest('[data-r-week]'); if (wk) { this.week(+wk.dataset.rWeek); return; }
        if (x.closest('[data-r-save]')) this.save();
      });
      this.el.addEventListener('change', e => { const p = e.target.closest('[data-r-pick]'); if (p) { p.checked ? this.picked.add(p.dataset.rPick) : this.picked.delete(p.dataset.rPick); this.pool(); } });
      $(this.el, '[data-r-search]').addEventListener('input', e => { this.q = e.target.value.trim().toLowerCase(); this.pool(); });
      this.el.dlRoster = {assign: this.assign, render: this.render, put: this.put};
      this.render();
    }

    ini(n) {
      return String(n).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    hue(s) {
      return [...String(s)].reduce((h, c) => (h * 31 + c.charCodeAt(0)) % 360, 0);
    }

    hrs(a, b) { const [h1, m1] = a.split(':').map(Number), [h2, m2] = b.split(':').map(Number); let d = (h2 * 60 + m2) - (h1 * 60 + m1); if (d <= 0) d += 1440; return d / 60; }

    weekHours(s) {
      return this.days.reduce((t, d) => t + (s.pattern[d.key] ? Math.max(0, this.hrs(...s.pattern[d.key]) - 1) : 0), 0);
    }

    workDays(s) {
      return this.days.filter(d => s.pattern[d.key]).length;
    }

    chip(p) {
      return `<span class="dl-roster__chip" draggable="true" data-r-person="${esc(p.id)}" title="${esc(p.name)} · ${esc(p.code)}"><i style="--h:${this.hue(p.name)}">${esc(this.ini(p.name))}</i><b>${esc(p.name)}</b><small>${esc(p.code)} · ${esc(p.role)}</small><em>${Devlib.h.t(':nd').replace(':n', this.workDays(this.shifts.find(s => s.key === this.assign[p.id])))}</em><button type="button" data-r-remove title="${Devlib.h.t('Remove from shift')}">&times;</button></span>`;
    }

    grid() {
      const head = `<div class="dl-roster__row is-head"><div class="dl-roster__shift">${Devlib.h.t('Shift / Hour Pattern')}</div>${this.days.map(d => `<div class="dl-roster__day ${d.off ? 'is-off' : ''}"><b>${esc(d.key)}</b><small>${esc(d.date || '')}</small></div>`).join('')}</div>`;
      $(this.el, '[data-r-grid]').innerHTML = head + this.shifts.map(s => {
        const list = this.people.filter(p => this.assign[p.id] === s.key), cap = +s.capacity || 0, pct = cap ? Math.min(100, list.length / cap * 100) : 0;
        const pat = this.days.filter(d => s.pattern[d.key]).map(d => d.key);
        return `<div class="dl-roster__row dl-roster--${esc(s.tone || 'accent')}" data-r-shift="${esc(s.key)}">
          <div class="dl-roster__shift"><b><i></i>${esc(s.label)}</b><small>${esc(pat.length === this.days.length ? Devlib.h.t('Every day') : pat[0] + '–' + pat[pat.length - 1])} · ${Devlib.h.t(':d days · :hh/week').replace(':d', this.workDays(s)).replace(':h', Math.round(this.weekHours(s) * 10) / 10)}</small>
            ${cap ? `<span class="dl-roster__cap ${list.length > cap ? 'is-over' : ''}"><span style="width:${pct}%"></span></span><em class="${list.length > cap ? 'is-over' : ''}">${list.length}/${cap}</em>` : ''}</div>
          ${this.days.map(d => `<div class="dl-roster__time ${s.pattern[d.key] ? '' : 'is-off'}">${s.pattern[d.key] ? `${esc(s.pattern[d.key][0])}<br><small>${esc(s.pattern[d.key][1])}${this.hrs(...s.pattern[d.key]) && s.pattern[d.key][1] < s.pattern[d.key][0] ? ' +1' : ''}</small>` : Devlib.h.t('OFF')}</div>`).join('')}
          <div class="dl-roster__drop" data-r-drop="${esc(s.key)}">${list.map(this.chip).join('') || '<span class="dl-roster__hint">' + Devlib.h.t("Drag employees here — they automatically follow this shift's hour pattern") + '</span>'}</div>
        </div>`;
      }).join('');
    }

    pool() {
      const free = this.people.filter(p => !this.assign[p.id] && (!this.q || (p.name + ' ' + p.code).toLowerCase().includes(this.q)));
      const groups = [...new Set(free.map(p => p.role))];
      $(this.el, '[data-r-poolcount]').textContent = Devlib.h.t(':n of :total employees not scheduled yet').replace(':n', free.length).replace(':total', this.people.length);
      $(this.el, '[data-r-pool]').innerHTML = groups.map(g => { const list = free.filter(p => p.role === g), all = list.every(p => this.picked.has(String(p.id))); return `<div class="dl-roster__grp"><button type="button" class="dl-roster__grphead" data-r-grp="${esc(g)}"><input type="checkbox" data-r-grpall="${esc(g)}" ${all && list.length ? 'checked' : ''} aria-label="${Devlib.h.t('Select all')} ${esc(g)}"><i class="fa-solid fa-caret-${this.shut.has(g) ? 'right' : 'down'}"></i><b>${esc(g)}</b><small>${Devlib.h.t(':n ppl').replace(':n', list.length)}</small></button>
        ${this.shut.has(g) ? '' : list.map(p => `<label class="dl-roster__person ${this.picked.has(String(p.id)) ? 'is-picked' : ''}" draggable="true" data-r-person="${esc(p.id)}"><input type="checkbox" data-r-pick="${esc(p.id)}" ${this.picked.has(String(p.id)) ? 'checked' : ''}><i style="--h:${this.hue(p.name)}">${esc(this.ini(p.name))}</i><span><b>${esc(p.name)}</b><small>${esc(p.code)} · ${p.skills ?? 0} skill</small></span><em>${esc(p.role)}</em></label>`).join('')}</div>`; }).join('') || '<div class="dl-empty"><b class="dl-empty__title">' + Devlib.h.t('All employees are scheduled') + '</b></div>';
      $(this.el, '[data-r-selbar]').hidden = !this.picked.size; $(this.el, '[data-r-selcount]').textContent = this.picked.size;
      const sched = Object.values(this.assign).filter(Boolean).length;
      $(this.el, '[data-r-stats]').innerHTML = `<span><b>${free.length}</b>${Devlib.h.t('Pool shown')}</span><span class="is-good"><b>${sched}</b>${Devlib.h.t('Scheduled')}</span><span class="is-warn"><b>${this.people.length - sched}</b>${Devlib.h.t('Not yet')}</span>`;
    }

    render() { this.grid(); this.pool(); }

    // Pindah minggu: dengan data-week-url layar dimuat ulang ke minggu itu, tanpa itu hanya event untuk layar pemakai.
    week(step) {
      this.el.dispatchEvent(new CustomEvent('dl:roster-week', {bubbles: true, detail: {step}}));
      if (this.el.dataset.weekUrl) { window.location.href = this.el.dataset.weekUrl.replace('{step}', step); return; }
      toast(Devlib.h.t("Week changed (the host screen loads that week's data)."));
    }

    // Simpan: dengan data-save-url dikirim ke server (JSON {assign}); gagal = pesan galat, papan tidak ditandai tersimpan.
    async save() {
      const detail = {assign: {...this.assign}};
      const url = this.el.dataset.saveUrl;
      if (url) {
        const r = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.DL_CSRF || ''}, body: JSON.stringify(detail)});
        const body = await r.json().catch(() => ({}));
        if (!r.ok) { toast(body.message || r.statusText, 'danger'); return; }
        this.el.dispatchEvent(new CustomEvent('dl:roster-save', {bubbles: true, detail}));
        toast(body.message || Devlib.h.t("This week's roster saved."), body.tone || 'success');
        return;
      }
      this.el.dispatchEvent(new CustomEvent('dl:roster-save', {bubbles: true, detail}));
      toast(Devlib.h.t("This week's roster saved."), 'success');
    }

    put(ids, key) {
      if (this.locked) return;
      const s = this.shifts.find(x => x.key === key); ids.forEach(id => { this.assign[id] = key; this.picked.delete(String(id)); });
      this.render(); this.el.dispatchEvent(new CustomEvent('dl:roster', {bubbles: true, detail: {assign: {...this.assign}}}));
      const n = this.people.filter(p => this.assign[p.id] === key).length; if (s.capacity && n > s.capacity) toast(Devlib.h.t(':shift exceeds capacity (:n/:cap).').replace(':shift', s.label).replace(':n', n).replace(':cap', s.capacity), 'warn');
    }
  }
  Devlib.define("Roster", Roster);
})(window.Devlib);
