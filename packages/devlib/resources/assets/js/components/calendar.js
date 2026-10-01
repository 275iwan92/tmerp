/* DevLib · Calendar */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, T, Tf, TZ, emit, NUM} = Devlib.h;
  /* ============================ CALENDAR ============================ */
  class Calendar extends Devlib.Component {
    static selector = "[data-dl-calendar]";
    init() {
      this.events = JSON.parse(this.el.dataset.events || '[]');
      this.ws = +this.el.dataset.weekStart;
      this.main = $(this.el, '[data-c-main]');
      // Nama bulan/hari ikut bahasa tampilan (Intl, locale dari sys_languages); 2 Jan 2000 = Minggu.
      this.MON = Array.from({length: 12}, (_, i) => new Date(2000, i, 1).toLocaleString(NUM.intl, {month: 'long'}));
      this.DAY = Array.from({length: 7}, (_, i) => new Date(2000, 0, 2 + i).toLocaleString(NUM.intl, {weekday: 'short'}).replace('.', ''));
      this.DAYL = Array.from({length: 7}, (_, i) => new Date(2000, 0, 2 + i).toLocaleString(NUM.intl, {weekday: 'long'}));
      this.TONES = ['accent', 'success', 'warn', 'danger', 'violet', 'neutral'];
      this.seq = Date.now();
      this.view = this.el.dataset.view || 'week';
      this.cur = this.el.dataset.date ? this.parse(this.el.dataset.date) : this.nowW();
      this.sel = new Date(this.cur);
      this.q = '';
      this.drag = null;
      this.events.forEach(e => { e.s = this.parse(e.start); e.e = e.end ? this.parse(e.end) : e.allDay ? new Date(e.s) : new Date(e.s.getTime() + 3600000); e.tone = e.tone || 'accent'; });
      this.HPX = 44;

      this.el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-c-today]')) { this.cur = this.nowW(); this.sel = new Date(this.cur); return this.render(); }
        const nav = x.closest('[data-c-prev],[data-c-next]');
        if (nav) { const k = nav.hasAttribute('data-c-next') ? 1 : -1; if (this.view === 'month') this.cur = new Date(this.cur.getFullYear(), this.cur.getMonth() + k, 1); else this.cur = this.addD(this.cur, k * (this.view === 'week' ? 7 : 1)); return this.render(); }
        const v = x.closest('[data-c-views] .dl-segment__opt'); if (v) { this.view = v.dataset.value; return this.render(); }
        const m = x.closest('[data-c-mini]'); if (m && m.tagName === 'BUTTON') { this.sel = new Date(this.sel.getFullYear(), this.sel.getMonth() + +m.dataset.cMini, 1); return this.mini(); }
        const go = x.closest('[data-c-go]'); if (go) { this.cur = this.parse(go.dataset.cGo); this.sel = new Date(this.cur); if (go.dataset.cToview) this.view = go.dataset.cToview; return this.render(); }
        if (x.closest('[data-c-create]')) return this.editor(this.blank(this.sel, this.nowW().getHours() + 1), true);
        const ev = x.closest('[data-c-ev]'); if (ev) { this.sel = new Date(this.byId(ev.dataset.cEv).s); this.mini(); this.side(); return; }
        const day = x.closest('[data-c-day],[data-c-col]'); if (day) { this.sel = this.parse(day.dataset.cDay || day.dataset.cCol); this.mini(); this.side(); $$(this.el, '.dl-cal2__cell.is-sel').forEach(c => c.classList.remove('is-sel')); if (day.classList.contains('dl-cal2__cell')) day.classList.add('is-sel'); }
      });
      this.el.addEventListener('dblclick', e => {
        const ev = e.target.closest('[data-c-ev]'); if (ev) return this.editor(this.byId(ev.dataset.cEv), false);
        const col = e.target.closest('[data-c-col]');
        if (col) { const r = col.getBoundingClientRect(), hr = Math.floor((e.clientY - r.top) / this.HPX); return this.editor(this.blank(this.parse(col.dataset.cCol), Math.min(23, Math.max(0, hr))), true); }
        const cell = e.target.closest('[data-c-day]'); if (cell) this.editor(this.blank(this.parse(cell.dataset.cDay), 9, cell.hasAttribute('data-c-allday')), true);
      });
      // Drag: ke sel hari (bulan / sepanjang hari) = pindah tanggal; ke kolom jam = pindah tanggal + jam (snap 30 menit).
      this.el.addEventListener('dragstart', e => { const ev = e.target.closest('[data-c-ev]'); if (!ev) return; this.drag = this.byId(ev.dataset.cEv); ev.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', this.drag.id); } catch (x) {} });
      this.el.addEventListener('dragend', () => { this.drag = null; $$(this.el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
      this.el.addEventListener('dragover', e => { const t = e.target.closest('[data-c-drop]'); if (!t || !this.drag) return; e.preventDefault(); $$(this.el, '.is-over').forEach(x => x.classList.remove('is-over')); t.classList.add('is-over'); });
      this.el.addEventListener('drop', e => {
        const t = e.target.closest('[data-c-drop]'); if (!t || !this.drag) return;
        e.preventDefault();
        const d = this.parse(t.dataset.cDrop), dur = this.drag.e - this.drag.s;
        if (t.hasAttribute('data-c-col')) { const r = t.getBoundingClientRect(), mins = Math.max(0, Math.min(23.5 * 60, Math.round((e.clientY - r.top) / this.HPX * 2) * 30)); d.setHours(0, mins); this.drag.allDay = false; this.drag.s = d; this.drag.e = new Date(d.getTime() + (dur || 3600000)); }
        else { const s = new Date(d); if (!this.drag.allDay) s.setHours(this.drag.s.getHours(), this.drag.s.getMinutes()); this.drag.s = s; this.drag.e = new Date(s.getTime() + dur); if (t.hasAttribute('data-c-allday')) this.drag.allDay = true; }
        const moved = this.drag; this.drag = null; this.sel = new Date(moved.s); this.render(); this._emit('move', moved); toast(Tf('c_moved', {title: moved.title, when: `${moved.s.getDate()} ${this.MON[moved.s.getMonth()]}${moved.allDay ? '' : ', ' + this.hhmm(moved.s)}`}), 'success');
      });
      this.sIn = $(this.el, '[data-c-search]');
      if (this.sIn) this.sIn.addEventListener('input', () => { this.q = this.sIn.value.trim().toLowerCase(); this.render(); });
      this.el.dlCalendar = {events: this.events, render: this.render, go: (d, v) => { this.cur = this.parse(d); this.sel = new Date(this.cur); if (v) this.view = v; this.render(); }};
      this.render();
      setInterval(() => { const nl = $(this.main, '.dl-cal2__now'), n = this.nowW(); if (nl) nl.style.top = (n.getHours() + n.getMinutes() / 60) * this.HPX + 'px'; }, 60000);
    }

    pad(n) {
      return String(n).padStart(2, '0');
    }

    nowW() {
      return new Date(new Date().toLocaleString('en-US', {timeZone: TZ()}));
    }

    parse(s) { const [d, t = '00:00'] = String(s).split(/[ T]/); const [y, m, dd] = d.split('-').map(Number), [hh, mi] = t.split(':').map(Number); return new Date(y, m - 1, dd, hh || 0, mi || 0); }

    ymd(d) {
      return `${d.getFullYear()}-${this.pad(d.getMonth() + 1)}-${this.pad(d.getDate())}`;
    }

    str(d) {
      return `${this.ymd(d)} ${this.pad(d.getHours())}:${this.pad(d.getMinutes())}`;
    }

    same(a, b) {
      return this.ymd(a) === this.ymd(b);
    }

    addD(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x; }

    hhmm(d) {
      return `${this.pad(d.getHours())}.${this.pad(d.getMinutes())}`;
    }

    onDay(d0) { const d = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate()); return this.events.filter(e => { const s0 = new Date(e.s.getFullYear(), e.s.getMonth(), e.s.getDate()), e0 = new Date(e.e.getFullYear(), e.e.getMonth(), e.e.getDate()); return d >= s0 && d <= e0; }).sort((a, b) => (b.allDay - a.allDay) || a.s - b.s); }

    when(e) {
      return e.allDay ? T('c_all_day') : `${this.hhmm(e.s)} – ${this.hhmm(e.e)} WIB`;
    }

    match(e) {
      return !this.q || (e.title + ' ' + (e.desc || '')).toLowerCase().includes(this.q);
    }

    _emit(type, e) {
      return this.el.dispatchEvent(new CustomEvent('dl:calendar', {bubbles: true, detail: {type, event: {id: e.id, title: e.title, start: e.allDay ? this.ymd(e.s) : this.str(e.s), end: e.allDay ? this.ymd(e.e) : this.str(e.e), allDay: !!e.allDay, tone: e.tone, desc: e.desc || ''}}}));
    }

    weekStartOf(d) {
      return this.addD(new Date(d.getFullYear(), d.getMonth(), d.getDate()), -((d.getDay() - this.ws + 7) % 7));
    }

    chip(e) {
      return `<div class="dl-cal2__ev dl-cal2--${esc(e.tone)} ${this.match(e) ? '' : 'is-dim'}" draggable="true" data-c-ev="${esc(e.id)}" title="${esc(e.title)} · ${esc(this.when(e))}">${e.allDay ? '' : `<small>${this.hhmm(e.s)}</small> `}${esc(e.title)}</div>`;
    }

    mini() {
      const box = $(this.el, '[data-c-mini]'); if (!box) return;
      const first = new Date(this.sel.getFullYear(), this.sel.getMonth(), 1), start = this.weekStartOf(first), today = this.nowW();
      box.innerHTML = `<div class="dl-cal2__minihead"><b>${this.MON[this.sel.getMonth()]} ${this.sel.getFullYear()}</b><span><button type="button" data-c-mini="-1" aria-label="Bulan sebelumnya">‹</button><button type="button" data-c-mini="1" aria-label="Bulan berikutnya">›</button></span></div>
        <div class="dl-cal2__minigrid">${[0, 1, 2, 3, 4, 5, 6].map(i => `<span class="is-dow">${this.DAY[(i + this.ws) % 7][0]}</span>`).join('')}${Array.from({length: 42}, (_, i) => { const d = this.addD(start, i); return `<button type="button" data-c-go="${this.ymd(d)}" class="${d.getMonth() !== this.sel.getMonth() ? 'is-out' : ''} ${this.same(d, today) ? 'is-today' : ''} ${this.same(d, this.cur) ? 'is-sel' : ''} ${this.onDay(d).length ? 'has-ev' : ''}">${d.getDate()}</button>`; }).join('')}</div>`;
    }

    title() {
      const t = $(this.el, '[data-c-title]');
      if (this.view === 'day') t.textContent = `${this.DAYL[this.cur.getDay()]}, ${this.cur.getDate()} ${this.MON[this.cur.getMonth()]} ${this.cur.getFullYear()}`;
      else if (this.view === 'week') { const a = this.weekStartOf(this.cur), b = this.addD(a, 6); t.textContent = a.getMonth() === b.getMonth() ? `${a.getDate()} – ${b.getDate()} ${this.MON[b.getMonth()]} ${b.getFullYear()}` : `${a.getDate()} ${this.MON[a.getMonth()].slice(0, 3)} – ${b.getDate()} ${this.MON[b.getMonth()].slice(0, 3)} ${b.getFullYear()}`; }
      else t.textContent = `${this.MON[this.cur.getMonth()]} ${this.cur.getFullYear()}`;
      $$(this.el, '[data-c-views] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === this.view));
    }

    monthView() {
      const first = new Date(this.cur.getFullYear(), this.cur.getMonth(), 1), start = this.weekStartOf(first), today = this.nowW();
      this.main.innerHTML = `<div class="dl-cal2__month"><div class="dl-cal2__dows">${[0, 1, 2, 3, 4, 5, 6].map(i => `<span>${this.DAY[(i + this.ws) % 7]}</span>`).join('')}</div><div class="dl-cal2__mgrid">${Array.from({length: 42}, (_, i) => {
        const d = this.addD(start, i), list = this.onDay(d);
        return `<div class="dl-cal2__cell ${d.getMonth() !== this.cur.getMonth() ? 'is-out' : ''} ${this.same(d, today) ? 'is-today' : ''} ${this.same(d, this.sel) ? 'is-sel' : ''}" data-c-day="${this.ymd(d)}" data-c-drop="${this.ymd(d)}"><span class="dl-cal2__num">${d.getDate()}</span>${list.slice(0, 3).map(this.chip).join('')}${list.length > 3 ? `<button type="button" class="dl-cal2__more" data-c-go="${this.ymd(d)}" data-c-toview="day">+${list.length - 3} lagi</button>` : ''}</div>`;
      }).join('')}</div></div>`;
    }

    timeView(days) {
      const today = this.nowW(), n = this.nowW();
      const lanes = d => { // acara bertumpuk dibagi kolom berdampingan
        // Kelompok = acara yang saling bertumpuk (berantai); lebar dibagi hanya di dalam kelompoknya.
        const list = this.onDay(d).filter(e => !e.allDay && this.same(e.s, d));
        let group = [], cols = [], end = null;
        const flush = () => { group.forEach(e => { e._n = cols.length; }); group = []; cols = []; end = null; };
        list.forEach(e => {
          if (end && e.s >= end) flush();
          let c = cols.findIndex(col => col[col.length - 1].e <= e.s); if (c < 0) { cols.push([e]); c = cols.length - 1; } else cols[c].push(e);
          e._c = c; group.push(e); end = !end || e.e > end ? e.e : end;
        });
        flush();
        return list;
      };
      this.main.innerHTML = `<div class="dl-cal2__time" style="--dl-cal-cols:${days.length}">
        <div class="dl-cal2__thead"><span class="dl-cal2__tz">WIB</span>${days.map(d => `<button type="button" class="dl-cal2__dh ${this.same(d, today) ? 'is-today' : ''}" data-c-go="${this.ymd(d)}" data-c-toview="day"><small>${this.DAY[d.getDay()]}</small><b>${d.getDate()}</b></button>`).join('')}</div>
        <div class="dl-cal2__allday"><span class="dl-cal2__tz">Sepanjang<br>hari</span>${days.map(d => `<div data-c-drop="${this.ymd(d)}" data-c-allday data-c-day="${this.ymd(d)}">${this.onDay(d).filter(e => e.allDay || !this.same(e.s, e.e)).map(this.chip).join('')}</div>`).join('')}</div>
        <div class="dl-cal2__scroll" data-c-scroll><div class="dl-cal2__grid" style="height:${24 * this.HPX}px">
          <div class="dl-cal2__hours">${Array.from({length: 24}, (_, h) => `<span style="top:${h * this.HPX}px">${h ? this.pad(h) + '.00' : ''}</span>`).join('')}</div>
          ${days.map(d => `<div class="dl-cal2__col ${this.same(d, today) ? 'is-today' : ''}" data-c-col="${this.ymd(d)}" data-c-drop="${this.ymd(d)}">${lanes(d).map(e => { const top = (e.s.getHours() + e.s.getMinutes() / 60) * this.HPX, hgt = Math.max(this.HPX / 2, (e.e - e.s) / 3600000 * this.HPX); return `<div class="dl-cal2__blk dl-cal2--${esc(e.tone)} ${this.match(e) ? '' : 'is-dim'}" draggable="true" data-c-ev="${esc(e.id)}" style="top:${top}px;height:${hgt - 2}px;left:calc(${e._c} * 100% / ${e._n});width:calc(100% / ${e._n} - 4px)"><b>${esc(e.title)}</b><small>${esc(this.when(e))}</small></div>`; }).join('')}
            ${this.same(d, n) ? `<div class="dl-cal2__now" style="top:${(n.getHours() + n.getMinutes() / 60) * this.HPX}px"></div>` : ''}</div>`).join('')}
        </div></div></div>`;
      const sc = $(this.main, '[data-c-scroll]'); sc.scrollTop = +this.el.dataset.scroll * this.HPX;
    }

    side() {
      const tl = $(this.el, '[data-c-today-list]'), dl = $(this.el, '[data-c-day-list]'); if (!tl) return;
      const t = this.nowW(), item = e => `<div class="dl-cal2__item dl-cal2--${esc(e.tone)}" data-c-ev="${esc(e.id)}"><b>${esc(e.title)}</b><small><i class="fa-regular fa-clock"></i> ${esc(this.when(e))}</small>${e.desc ? `<p>${esc(e.desc)}</p>` : ''}</div>`;
      tl.innerHTML = `<p class="dl-cal2__cap"><span class="dl-cal2__dot"></span>${esc(T('c_today_events'))}</p>${this.onDay(t).map(item).join('') || '<p class="dl-cal2__none">' + esc(T('c_no_events_today')) + '</p>'}`;
      const list = this.onDay(this.sel);
      dl.innerHTML = `<h3 class="dl-cal2__selh">${this.DAYL[this.sel.getDay()]}, ${this.sel.getDate()} ${this.MON[this.sel.getMonth()]}</h3><small class="dl-cal2__selc">${list.length} acara</small>${list.map(item).join('') || '<p class="dl-cal2__none">Klik 2x slot kosong untuk membuat acara.</p>'}`;
      const f = $(this.el, '[data-c-found]'); if (f) f.innerHTML = this.q ? `<p class="dl-cal2__cap">${this.events.filter(this.match).length} ditemukan</p>` + this.events.filter(this.match).slice(0, 8).map(e => `<button type="button" class="dl-cal2__hit" data-c-go="${this.ymd(e.s)}"><b>${esc(e.title)}</b><small>${e.s.getDate()} ${this.MON[e.s.getMonth()].slice(0, 3)} · ${esc(this.when(e))}</small></button>`).join('') : '';
    }

    render() {
      this.title(); this.mini(); this.side();
      if (this.view === 'month') this.monthView();
      else if (this.view === 'week') { const a = this.weekStartOf(this.cur); this.timeView(Array.from({length: 7}, (_, i) => this.addD(a, i))); }
      else this.timeView([new Date(this.cur.getFullYear(), this.cur.getMonth(), this.cur.getDate())]);
    }

    byId(id) {
      return this.events.find(e => String(e.id) === String(id));
    }

    editor(e, isNew) {
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
      const v = d => e.allDay ? this.ymd(d) : `${this.ymd(d)}T${this.pad(d.getHours())}:${this.pad(d.getMinutes())}`;
      const draw = () => {
        ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>${esc(T(isNew ? 'c_new_event' : 'c_edit_event'))}</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
        <div class="dl-modal__body dl-gantt__edit">
          <label><span>Judul *</span><input data-e-title value="${esc(e.title || '')}" placeholder="Judul acara"></label>
          <label class="dl-choice"><input type="checkbox" data-e-allday ${e.allDay ? 'checked' : ''}> ${esc(T('c_all_day'))}</label>
          <label><span>Mulai</span><input type="${e.allDay ? 'date' : 'datetime-local'}" data-e-start value="${v(e.s)}"></label>
          <label><span>Selesai</span><input type="${e.allDay ? 'date' : 'datetime-local'}" data-e-end value="${v(e.e)}"></label>
          <div><span class="dl-kpop__lbl">Warna</span><div class="dl-cal2__tones">${this.TONES.map(t => `<button type="button" class="dl-cal2--${t} ${t === e.tone ? 'is-on' : ''}" data-e-tone="${t}" aria-label="${t}"></button>`).join('')}</div></div>
          <label><span>Deskripsi</span><textarea rows="3" data-e-desc>${esc(e.desc || '')}</textarea></label>
          <p class="dl-lines__err" data-e-err></p>
        </div>
        <div class="dl-modal__foot">${isNew ? '' : '<button type="button" class="dl-btn dl-btn--danger dl-btn--md" data-e-del><i class="fa-solid fa-trash-can"></i> Delete</button><span class="dl-kcard__spacer"></span>'}<button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Cancel</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save><i class="fa-solid fa-floppy-disk"></i> Save</button></div></div>`;
      };
      const read = () => { e.title = $(ov, '[data-e-title]').value.trim(); e.desc = $(ov, '[data-e-desc]').value.trim(); e.allDay = $(ov, '[data-e-allday]').checked; const a = $(ov, '[data-e-start]').value, b = $(ov, '[data-e-end]').value; if (a) e.s = this.parse(a); if (b) e.e = this.parse(b); };
      draw();
      ov.addEventListener('click', ev => {
        const x = ev.target;
        if (x === ov || x.closest('[data-x]')) return ov.remove();
        const t = x.closest('[data-e-tone]'); if (t) { read(); e.tone = t.dataset.eTone; return draw(); }
        if (x.closest('[data-e-save]')) {
          read(); const err = !e.title ? T('c_title_required') : e.e < e.s ? T('c_end_before_start') : '';
          if (err) { $(ov, '[data-e-err]').textContent = err; return; }
          if (isNew) this.events.push(e);
          ov.remove(); this.sel = new Date(e.s); this.render(); this._emit(isNew ? 'create' : 'update', e); toast(`Acara "${e.title}" ${isNew ? 'dibuat' : 'disimpan'}.`, 'success');
        }
        if (x.closest('[data-e-del]')) confirmBox({message: Tf('c_delete_event', {title: e.title}), tone: 'danger'}).then(ok => { if (!ok) return; this.events.splice(this.events.indexOf(e), 1); ov.remove(); this.render(); this._emit('delete', e); });
      });
      ov.addEventListener('change', ev => { if (ev.target.matches('[data-e-allday]')) { read(); draw(); } });
      document.body.appendChild(ov); $(ov, '[data-e-title]').focus();
    }

    blank(d, h = 9, allDay = false) { const s = new Date(d.getFullYear(), d.getMonth(), d.getDate(), allDay ? 0 : h, 0); return {id: 'n' + (++this.seq), title: '', s, e: allDay ? new Date(s) : new Date(s.getTime() + 3600000), allDay, tone: 'accent', desc: ''}; }
  }
  Devlib.define("Calendar", Calendar);
})(window.Devlib);
