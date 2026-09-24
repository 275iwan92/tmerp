/* DevLib · Calendar */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, T, TZ, emit} = Devlib.h;
  /* ============================ CALENDAR ============================ */
  class Calendar extends Devlib.Component {
    static selector = "[data-dl-calendar]";
    init() {
      const el = this.el;
      const events = JSON.parse(el.dataset.events || '[]'), ws = +el.dataset.weekStart, main = $(el, '[data-c-main]');
      const MON = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      const DAY = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], DAYL = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const TONES = ['accent', 'success', 'warn', 'danger', 'violet', 'neutral'];
      const pad = n => String(n).padStart(2, '0');
      const nowW = () => new Date(new Date().toLocaleString('en-US', {timeZone: TZ()}));
      const parse = s => { const [d, t = '00:00'] = String(s).split(/[ T]/); const [y, m, dd] = d.split('-').map(Number), [hh, mi] = t.split(':').map(Number); return new Date(y, m - 1, dd, hh || 0, mi || 0); };
      const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
      const str = d => `${ymd(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
      const same = (a, b) => ymd(a) === ymd(b);
      const addD = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
      const hhmm = d => `${pad(d.getHours())}.${pad(d.getMinutes())}`;
      let seq = Date.now(), view = el.dataset.view || 'week', cur = el.dataset.date ? parse(el.dataset.date) : nowW(), sel = new Date(cur), q = '', drag = null;
      events.forEach(e => { e.s = parse(e.start); e.e = e.end ? parse(e.end) : e.allDay ? new Date(e.s) : new Date(e.s.getTime() + 3600000); e.tone = e.tone || 'accent'; });
      const onDay = d0 => { const d = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate()); return events.filter(e => { const s0 = new Date(e.s.getFullYear(), e.s.getMonth(), e.s.getDate()), e0 = new Date(e.e.getFullYear(), e.e.getMonth(), e.e.getDate()); return d >= s0 && d <= e0; }).sort((a, b) => (b.allDay - a.allDay) || a.s - b.s); };
      const when = e => e.allDay ? 'Sepanjang hari' : `${hhmm(e.s)} – ${hhmm(e.e)} WIB`;
      const match = e => !q || (e.title + ' ' + (e.desc || '')).toLowerCase().includes(q);
      const emit = (type, e) => el.dispatchEvent(new CustomEvent('dl:calendar', {bubbles: true, detail: {type, event: {id: e.id, title: e.title, start: e.allDay ? ymd(e.s) : str(e.s), end: e.allDay ? ymd(e.e) : str(e.e), allDay: !!e.allDay, tone: e.tone, desc: e.desc || ''}}}));
      const weekStartOf = d => addD(new Date(d.getFullYear(), d.getMonth(), d.getDate()), -((d.getDay() - ws + 7) % 7));
      const chip = e => `<div class="dl-cal2__ev dl-cal2--${esc(e.tone)} ${match(e) ? '' : 'is-dim'}" draggable="true" data-c-ev="${esc(e.id)}" title="${esc(e.title)} · ${esc(when(e))}">${e.allDay ? '' : `<small>${hhmm(e.s)}</small> `}${esc(e.title)}</div>`;

      function mini() {
        const box = $(el, '[data-c-mini]'); if (!box) return;
        const first = new Date(sel.getFullYear(), sel.getMonth(), 1), start = weekStartOf(first), today = nowW();
        box.innerHTML = `<div class="dl-cal2__minihead"><b>${MON[sel.getMonth()]} ${sel.getFullYear()}</b><span><button type="button" data-c-mini="-1" aria-label="Bulan sebelumnya">‹</button><button type="button" data-c-mini="1" aria-label="Bulan berikutnya">›</button></span></div>
          <div class="dl-cal2__minigrid">${[0, 1, 2, 3, 4, 5, 6].map(i => `<span class="is-dow">${DAY[(i + ws) % 7][0]}</span>`).join('')}${Array.from({length: 42}, (_, i) => { const d = addD(start, i); return `<button type="button" data-c-go="${ymd(d)}" class="${d.getMonth() !== sel.getMonth() ? 'is-out' : ''} ${same(d, today) ? 'is-today' : ''} ${same(d, cur) ? 'is-sel' : ''} ${onDay(d).length ? 'has-ev' : ''}">${d.getDate()}</button>`; }).join('')}</div>`;
      }
      function title() {
        const t = $(el, '[data-c-title]');
        if (view === 'day') t.textContent = `${DAYL[cur.getDay()]}, ${cur.getDate()} ${MON[cur.getMonth()]} ${cur.getFullYear()}`;
        else if (view === 'week') { const a = weekStartOf(cur), b = addD(a, 6); t.textContent = a.getMonth() === b.getMonth() ? `${a.getDate()} – ${b.getDate()} ${MON[b.getMonth()]} ${b.getFullYear()}` : `${a.getDate()} ${MON[a.getMonth()].slice(0, 3)} – ${b.getDate()} ${MON[b.getMonth()].slice(0, 3)} ${b.getFullYear()}`; }
        else t.textContent = `${MON[cur.getMonth()]} ${cur.getFullYear()}`;
        $$(el, '[data-c-views] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === view));
      }
      function monthView() {
        const first = new Date(cur.getFullYear(), cur.getMonth(), 1), start = weekStartOf(first), today = nowW();
        main.innerHTML = `<div class="dl-cal2__month"><div class="dl-cal2__dows">${[0, 1, 2, 3, 4, 5, 6].map(i => `<span>${DAY[(i + ws) % 7]}</span>`).join('')}</div><div class="dl-cal2__mgrid">${Array.from({length: 42}, (_, i) => {
          const d = addD(start, i), list = onDay(d);
          return `<div class="dl-cal2__cell ${d.getMonth() !== cur.getMonth() ? 'is-out' : ''} ${same(d, today) ? 'is-today' : ''} ${same(d, sel) ? 'is-sel' : ''}" data-c-day="${ymd(d)}" data-c-drop="${ymd(d)}"><span class="dl-cal2__num">${d.getDate()}</span>${list.slice(0, 3).map(chip).join('')}${list.length > 3 ? `<button type="button" class="dl-cal2__more" data-c-go="${ymd(d)}" data-c-toview="day">+${list.length - 3} lagi</button>` : ''}</div>`;
        }).join('')}</div></div>`;
      }
      const HPX = 44;
      function timeView(days) {
        const today = nowW(), n = nowW();
        const lanes = d => { // acara bertumpuk dibagi kolom berdampingan
          // Kelompok = acara yang saling bertumpuk (berantai); lebar dibagi hanya di dalam kelompoknya.
          const list = onDay(d).filter(e => !e.allDay && same(e.s, d));
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
        main.innerHTML = `<div class="dl-cal2__time" style="--dl-cal-cols:${days.length}">
          <div class="dl-cal2__thead"><span class="dl-cal2__tz">WIB</span>${days.map(d => `<button type="button" class="dl-cal2__dh ${same(d, today) ? 'is-today' : ''}" data-c-go="${ymd(d)}" data-c-toview="day"><small>${DAY[d.getDay()]}</small><b>${d.getDate()}</b></button>`).join('')}</div>
          <div class="dl-cal2__allday"><span class="dl-cal2__tz">Sepanjang<br>hari</span>${days.map(d => `<div data-c-drop="${ymd(d)}" data-c-allday data-c-day="${ymd(d)}">${onDay(d).filter(e => e.allDay || !same(e.s, e.e)).map(chip).join('')}</div>`).join('')}</div>
          <div class="dl-cal2__scroll" data-c-scroll><div class="dl-cal2__grid" style="height:${24 * HPX}px">
            <div class="dl-cal2__hours">${Array.from({length: 24}, (_, h) => `<span style="top:${h * HPX}px">${h ? pad(h) + '.00' : ''}</span>`).join('')}</div>
            ${days.map(d => `<div class="dl-cal2__col ${same(d, today) ? 'is-today' : ''}" data-c-col="${ymd(d)}" data-c-drop="${ymd(d)}">${lanes(d).map(e => { const top = (e.s.getHours() + e.s.getMinutes() / 60) * HPX, hgt = Math.max(HPX / 2, (e.e - e.s) / 3600000 * HPX); return `<div class="dl-cal2__blk dl-cal2--${esc(e.tone)} ${match(e) ? '' : 'is-dim'}" draggable="true" data-c-ev="${esc(e.id)}" style="top:${top}px;height:${hgt - 2}px;left:calc(${e._c} * 100% / ${e._n});width:calc(100% / ${e._n} - 4px)"><b>${esc(e.title)}</b><small>${esc(when(e))}</small></div>`; }).join('')}
              ${same(d, n) ? `<div class="dl-cal2__now" style="top:${(n.getHours() + n.getMinutes() / 60) * HPX}px"></div>` : ''}</div>`).join('')}
          </div></div></div>`;
        const sc = $(main, '[data-c-scroll]'); sc.scrollTop = +el.dataset.scroll * HPX;
      }
      function side() {
        const tl = $(el, '[data-c-today-list]'), dl = $(el, '[data-c-day-list]'); if (!tl) return;
        const t = nowW(), item = e => `<div class="dl-cal2__item dl-cal2--${esc(e.tone)}" data-c-ev="${esc(e.id)}"><b>${esc(e.title)}</b><small><i class="fa-regular fa-clock"></i> ${esc(when(e))}</small>${e.desc ? `<p>${esc(e.desc)}</p>` : ''}</div>`;
        tl.innerHTML = `<p class="dl-cal2__cap"><span class="dl-cal2__dot"></span>Acara hari ini</p>${onDay(t).map(item).join('') || '<p class="dl-cal2__none">Tidak ada acara hari ini.</p>'}`;
        const list = onDay(sel);
        dl.innerHTML = `<h3 class="dl-cal2__selh">${DAYL[sel.getDay()]}, ${sel.getDate()} ${MON[sel.getMonth()]}</h3><small class="dl-cal2__selc">${list.length} acara</small>${list.map(item).join('') || '<p class="dl-cal2__none">Klik 2x slot kosong untuk membuat acara.</p>'}`;
        const f = $(el, '[data-c-found]'); if (f) f.innerHTML = q ? `<p class="dl-cal2__cap">${events.filter(match).length} ditemukan</p>` + events.filter(match).slice(0, 8).map(e => `<button type="button" class="dl-cal2__hit" data-c-go="${ymd(e.s)}"><b>${esc(e.title)}</b><small>${e.s.getDate()} ${MON[e.s.getMonth()].slice(0, 3)} · ${esc(when(e))}</small></button>`).join('') : '';
      }
      function render() {
        title(); mini(); side();
        if (view === 'month') monthView();
        else if (view === 'week') { const a = weekStartOf(cur); timeView(Array.from({length: 7}, (_, i) => addD(a, i))); }
        else timeView([new Date(cur.getFullYear(), cur.getMonth(), cur.getDate())]);
      }
      const byId = id => events.find(e => String(e.id) === String(id));

      function editor(e, isNew) {
        const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
        const v = d => e.allDay ? ymd(d) : `${ymd(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        const draw = () => {
          ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>${isNew ? 'Acara baru' : 'Ubah acara'}</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
          <div class="dl-modal__body dl-gantt__edit">
            <label><span>Judul *</span><input data-e-title value="${esc(e.title || '')}" placeholder="Judul acara"></label>
            <label class="dl-choice"><input type="checkbox" data-e-allday ${e.allDay ? 'checked' : ''}> Sepanjang hari</label>
            <label><span>Mulai</span><input type="${e.allDay ? 'date' : 'datetime-local'}" data-e-start value="${v(e.s)}"></label>
            <label><span>Selesai</span><input type="${e.allDay ? 'date' : 'datetime-local'}" data-e-end value="${v(e.e)}"></label>
            <div><span class="dl-kpop__lbl">Warna</span><div class="dl-cal2__tones">${TONES.map(t => `<button type="button" class="dl-cal2--${t} ${t === e.tone ? 'is-on' : ''}" data-e-tone="${t}" aria-label="${t}"></button>`).join('')}</div></div>
            <label><span>Deskripsi</span><textarea rows="3" data-e-desc>${esc(e.desc || '')}</textarea></label>
            <p class="dl-lines__err" data-e-err></p>
          </div>
          <div class="dl-modal__foot">${isNew ? '' : '<button type="button" class="dl-btn dl-btn--danger dl-btn--md" data-e-del><i class="fa-solid fa-trash-can"></i> Hapus</button><span class="dl-kcard__spacer"></span>'}<button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save><i class="fa-solid fa-floppy-disk"></i> Simpan</button></div></div>`;
        };
        const read = () => { e.title = $(ov, '[data-e-title]').value.trim(); e.desc = $(ov, '[data-e-desc]').value.trim(); e.allDay = $(ov, '[data-e-allday]').checked; const a = $(ov, '[data-e-start]').value, b = $(ov, '[data-e-end]').value; if (a) e.s = parse(a); if (b) e.e = parse(b); };
        draw();
        ov.addEventListener('click', ev => {
          const x = ev.target;
          if (x === ov || x.closest('[data-x]')) return ov.remove();
          const t = x.closest('[data-e-tone]'); if (t) { read(); e.tone = t.dataset.eTone; return draw(); }
          if (x.closest('[data-e-save]')) {
            read(); const err = !e.title ? 'Judul wajib diisi.' : e.e < e.s ? 'Waktu selesai tidak boleh sebelum mulai.' : '';
            if (err) { $(ov, '[data-e-err]').textContent = err; return; }
            if (isNew) events.push(e);
            ov.remove(); sel = new Date(e.s); render(); emit(isNew ? 'create' : 'update', e); toast(`Acara "${e.title}" ${isNew ? 'dibuat' : 'disimpan'}.`, 'success');
          }
          if (x.closest('[data-e-del]')) confirmBox({message: `Hapus acara "${e.title}"?`, tone: 'danger'}).then(ok => { if (!ok) return; events.splice(events.indexOf(e), 1); ov.remove(); render(); emit('delete', e); });
        });
        ov.addEventListener('change', ev => { if (ev.target.matches('[data-e-allday]')) { read(); draw(); } });
        document.body.appendChild(ov); $(ov, '[data-e-title]').focus();
      }
      const blank = (d, h = 9, allDay = false) => { const s = new Date(d.getFullYear(), d.getMonth(), d.getDate(), allDay ? 0 : h, 0); return {id: 'n' + (++seq), title: '', s, e: allDay ? new Date(s) : new Date(s.getTime() + 3600000), allDay, tone: 'accent', desc: ''}; };

      el.addEventListener('click', e => {
        const x = e.target;
        if (x.closest('[data-c-today]')) { cur = nowW(); sel = new Date(cur); return render(); }
        const nav = x.closest('[data-c-prev],[data-c-next]');
        if (nav) { const k = nav.hasAttribute('data-c-next') ? 1 : -1; if (view === 'month') cur = new Date(cur.getFullYear(), cur.getMonth() + k, 1); else cur = addD(cur, k * (view === 'week' ? 7 : 1)); return render(); }
        const v = x.closest('[data-c-views] .dl-segment__opt'); if (v) { view = v.dataset.value; return render(); }
        const m = x.closest('[data-c-mini]'); if (m && m.tagName === 'BUTTON') { sel = new Date(sel.getFullYear(), sel.getMonth() + +m.dataset.cMini, 1); return mini(); }
        const go = x.closest('[data-c-go]'); if (go) { cur = parse(go.dataset.cGo); sel = new Date(cur); if (go.dataset.cToview) view = go.dataset.cToview; return render(); }
        if (x.closest('[data-c-create]')) return editor(blank(sel, nowW().getHours() + 1), true);
        const ev = x.closest('[data-c-ev]'); if (ev) { sel = new Date(byId(ev.dataset.cEv).s); mini(); side(); return; }
        const day = x.closest('[data-c-day],[data-c-col]'); if (day) { sel = parse(day.dataset.cDay || day.dataset.cCol); mini(); side(); $$(el, '.dl-cal2__cell.is-sel').forEach(c => c.classList.remove('is-sel')); if (day.classList.contains('dl-cal2__cell')) day.classList.add('is-sel'); }
      });
      el.addEventListener('dblclick', e => {
        const ev = e.target.closest('[data-c-ev]'); if (ev) return editor(byId(ev.dataset.cEv), false);
        const col = e.target.closest('[data-c-col]');
        if (col) { const r = col.getBoundingClientRect(), hr = Math.floor((e.clientY - r.top) / HPX); return editor(blank(parse(col.dataset.cCol), Math.min(23, Math.max(0, hr))), true); }
        const cell = e.target.closest('[data-c-day]'); if (cell) editor(blank(parse(cell.dataset.cDay), 9, cell.hasAttribute('data-c-allday')), true);
      });
      // Drag: ke sel hari (bulan / sepanjang hari) = pindah tanggal; ke kolom jam = pindah tanggal + jam (snap 30 menit).
      el.addEventListener('dragstart', e => { const ev = e.target.closest('[data-c-ev]'); if (!ev) return; drag = byId(ev.dataset.cEv); ev.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag.id); } catch (x) {} });
      el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
      el.addEventListener('dragover', e => { const t = e.target.closest('[data-c-drop]'); if (!t || !drag) return; e.preventDefault(); $$(el, '.is-over').forEach(x => x.classList.remove('is-over')); t.classList.add('is-over'); });
      el.addEventListener('drop', e => {
        const t = e.target.closest('[data-c-drop]'); if (!t || !drag) return;
        e.preventDefault();
        const d = parse(t.dataset.cDrop), dur = drag.e - drag.s;
        if (t.hasAttribute('data-c-col')) { const r = t.getBoundingClientRect(), mins = Math.max(0, Math.min(23.5 * 60, Math.round((e.clientY - r.top) / HPX * 2) * 30)); d.setHours(0, mins); drag.allDay = false; drag.s = d; drag.e = new Date(d.getTime() + (dur || 3600000)); }
        else { const s = new Date(d); if (!drag.allDay) s.setHours(drag.s.getHours(), drag.s.getMinutes()); drag.s = s; drag.e = new Date(s.getTime() + dur); if (t.hasAttribute('data-c-allday')) drag.allDay = true; }
        const moved = drag; drag = null; sel = new Date(moved.s); render(); emit('move', moved); toast(`"${moved.title}" dipindah ke ${moved.s.getDate()} ${MON[moved.s.getMonth()]}${moved.allDay ? '' : ', ' + hhmm(moved.s)}.`, 'success');
      });
      const sIn = $(el, '[data-c-search]'); if (sIn) sIn.addEventListener('input', () => { q = sIn.value.trim().toLowerCase(); render(); });
      el.dlCalendar = {events, render, go: (d, v) => { cur = parse(d); sel = new Date(cur); if (v) view = v; render(); }};
      render();
      setInterval(() => { const nl = $(main, '.dl-cal2__now'), n = nowW(); if (nl) nl.style.top = (n.getHours() + n.getMinutes() / 60) * HPX + 'px'; }, 60000);
    }
  }
  Devlib.define("Calendar", Calendar);
})(window.Devlib);
