/* DevLib · Gantt */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, TZ, fmt, setField} = Devlib.h;
  /* ============================ GANTT ============================ */
  class Gantt extends Devlib.Component {
    static selector = "[data-dl-gantt]";
    init() {
      const el = this.el;
      const cfg = JSON.parse(el.dataset.config), rows = cfg.rows, tasks = cfg.tasks, cats = cfg.categories, PX = cfg.px, S = cfg.start, E = cfg.end, SNAP = cfg.snap / 60;
      const board = $(el, '[data-g2-board]'), changed = new Set();
      let hi = null, fRes = '', fProc = '', pop = null;
      const h = t => { const [a, b] = String(t).split(':'); return +a + (+b || 0) / 60; };
      const hm = x => `${String(Math.floor(x + 1e-9)).padStart(2, '0')}:${String(Math.round((x % 1) * 60) % 60).padStart(2, '0')}`;
      tasks.forEach(t => { t.s = h(t.start); t.e = t.end ? h(t.end) : t.s + (+t.duration || 1); });
      const live = () => el.dataset.mode === 'live';
      const catOf = t => cats[t.process] || {label: t.process || '', tone: 'accent'};
      const rowTasks = k => tasks.filter(t => t.row === k).sort((a, b) => a.s - b.s);
      // Celah idle = jeda >= 1 jam di antara dua task pada resource yang sama.
      const gaps = k => { const out = [], list = rowTasks(k); for (let i = 1; i < list.length; i++) { const a = Math.max(...list.slice(0, i).map(t => t.e)), b = list[i].s; if (b - a >= 1 - 1e-9) out.push([a, b]); } return out; };
      const bigIdle = k => gaps(k).some(([a, b]) => b - a >= cfg.idle);
      const conflict = t => tasks.some(o => o !== t && o.row === t.row && o.s < t.e - 1e-9 && o.e > t.s + 1e-9);
      const W = (E - S) * PX;

      function stats() {
        const risk = tasks.filter(t => t.risk).length, idle = rows.filter(r => bigIdle(r.key)).length;
        $(el, '[data-g2-stats]').innerHTML = [
          ['all', 'Total Task Aktif', tasks.length, 'accent', 'klik untuk reset sorotan'],
          ['none', 'Belum Terjadwal', cfg.unscheduled, 'danger', '—'],
          ['risk', 'Berisiko Telat', risk, 'warn', 'klik untuk sorot di board'],
          ['idle', `Resource Kosong >${cfg.idle} jam`, idle, 'info', 'klik untuk sorot di board'],
        ].map(([k, l, v, tone, sub]) => `<button type="button" class="dl-stat dl-stat--${tone} is-filter ${hi === k ? 'is-active' : ''}" data-g2-hi="${k}"><span class="dl-stat__label">${esc(l)}</span><span class="dl-stat__value">${v}</span><span class="dl-stat__sub">${esc(sub)}</span></button>`).join('');
      }
      function legend() {
        $(el, '[data-g2-legend]').innerHTML = Object.entries(cats).map(([k, c]) => `<span><i class="dl-gantt__sw dl-gantt--${esc(c.tone)}"></i>${esc(c.label)}</span>`).join('')
          + '<span class="dl-gantt__lsep"></span><span><i class="dl-gantt__sw dl-gantt--breakdown"></i>Breakdown</span><span><i class="dl-gantt__sw is-qchold"></i>QC Hold</span><span><i class="dl-gantt__sw is-hot"></i>🔥 Hot Job</span><span class="dl-gantt__lsep"></span><span>▬ garis dalam blok = progress</span>';
      }
      function block(t) {
        const c = catOf(t), cls = t.state === 'breakdown' ? 'breakdown' : c.tone;
        const dim = hi && hi !== 'all' && !(hi === 'risk' ? t.risk : hi === 'idle' ? bigIdle(t.row) : false);
        return `<div class="dl-gantt__blk dl-gantt--${esc(cls)} ${t.state === 'hot' ? 'is-hot' : ''} ${t.state === 'qchold' ? 'is-qchold' : ''} ${conflict(t) ? 'is-conflict' : ''} ${dim ? 'is-dim' : ''} ${hi && !dim && hi !== 'all' ? 'is-match' : ''} ${changed.has(t.id) ? 'is-changed' : ''}"
          style="left:${(t.s - S) * PX}px;width:${Math.max(0.25, t.e - t.s) * PX}px" data-g2-task="${esc(t.id)}" title="${esc(t.label)} · ${hm(t.s)}–${hm(t.e)}">
          <b>${t.state === 'hot' ? '🔥 ' : t.state === 'breakdown' ? '⚠ ' : t.state === 'qchold' ? '🔍 ' : ''}${esc(t.label)}${t.state !== 'breakdown' && c.label ? ' · ' + esc(c.label) : ''}</b>
          <small>${hm(t.s)}–${hm(t.e)}</small><span class="dl-gantt__prog"><span style="width:${+t.progress || 0}%"></span></span>
          ${t.state === 'qchold' || live() ? '' : '<span class="dl-gantt__rz" data-g2-rz></span>'}</div>`;
      }
      function render() {
        const vis = rows.filter(r => (!fRes || r.key === fRes) && (!fProc || rowTasks(r.key).some(t => t.process === fProc)));
        let html = `<div class="dl-gantt__row dl-gantt__head"><div class="dl-gantt__res">${esc(cfg.resourceLabel)}</div><div class="dl-gantt__lane" style="width:${W}px">${Array.from({length: E - S + 1}, (_, i) => `<span style="left:${i * PX}px">${String(S + i).padStart(2, '0')}:00</span>`).join('')}</div></div>`;
        let sec = null;
        vis.forEach(r => {
          if (r.section && r.section !== sec) { sec = r.section; html += `<div class="dl-gantt__sec">${esc(sec)}</div>`; }
          const list = rowTasks(r.key).filter(t => !fProc || t.process === fProc);
          html += `<div class="dl-gantt__row" data-g2-row="${esc(r.key)}"><div class="dl-gantt__res"><b>${esc(r.label)}</b>${r.sub ? `<small><i class="fa-solid fa-user"></i> ${esc(r.sub)}</small>` : ''}</div>
            <div class="dl-gantt__lane" style="width:${W}px;background-size:${PX}px 100%">${fProc ? '' : gaps(r.key).map(([a, b]) => `<div class="dl-gantt__idle" style="left:${(a - S) * PX}px;width:${(b - a) * PX}px">idle</div>`).join('')}${list.map(block).join('')}</div></div>`;
        });
        if (!vis.length) html += '<div class="dl-empty"><b class="dl-empty__title">Tidak ada resource yang cocok dengan filter</b></div>';
        const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()})), nh = now.getHours() + now.getMinutes() / 60;
        if (nh >= S && nh <= E) html += `<div class="dl-gantt__now" style="left:calc(var(--dl-gantt-res) + ${(nh - S) * PX}px)" title="Sekarang ${fmt.time()}"></div>`;
        board.innerHTML = html;
        stats();
        const chips = [...(fRes ? [['res', 'Resource: ' + rows.find(r => r.key === fRes).label]] : []), ...(fProc ? [['proc', 'Proses: ' + catOf({process: fProc}).label]] : [])];
        const cb = $(el, '[data-g2-chips]'); cb.hidden = !chips.length;
        cb.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g2-chip="${k}">&times;</button></span>`).join('') + (chips.length ? '<button type="button" class="dl-btn dl-btn--link" data-g2-chip="all">Reset semua filter</button>' : '');
        const sb = $(el, '[data-g2-submit]'); if (sb) { sb.disabled = !changed.size || live(); sb.dataset.count = changed.size; }
        $(el, '[data-g2-modehelp]').textContent = live() ? 'Mode Live: jadwal hanya bisa dilihat.' : `Mode Simulasi: ${changed.size} perubahan belum dikirim ke jadwal Live sampai Submit. QC Hold terkunci sampai QA memutuskan.`;
      }
      const setSel = (name, v) => { const f = $(el, `[name="${name}"]`); if (f) setField(el, name, v); };
      const task = id => tasks.find(t => String(t.id) === String(id));
      const touch = (t, type) => { changed.add(t.id); el.dispatchEvent(new CustomEvent('dl:gantt', {bubbles: true, detail: {type, task: {...t, start: hm(t.s), end: hm(t.e)}}})); if (conflict(t)) toast(`${t.label} bertabrakan dengan task lain di resource yang sama.`, 'warn'); };

      // Drag & resize (pointer events, snap). Klik tanpa gerak = popover.
      let op = null;
      board.addEventListener('pointerdown', e => {
        const b = e.target.closest('[data-g2-task]'); if (!b || e.button !== 0) return;
        const t = task(b.dataset.g2Task);
        closePop();
        if (live()) return;
        if (t.state === 'qchold') { op = {t, b, x0: e.clientX, locked: true}; return; }
        op = {t, b, x0: e.clientX, s0: t.s, e0: t.e, rz: !!e.target.closest('[data-g2-rz]'), moved: false};
        b.setPointerCapture(e.pointerId);
      });
      board.addEventListener('pointermove', e => {
        if (!op || op.locked) return;
        const dh = (e.clientX - op.x0) / PX; if (Math.abs(e.clientX - op.x0) > 3) op.moved = true; if (!op.moved) return;
        const sn = v => Math.round(v / SNAP) * SNAP;
        if (op.rz) op.t.e = Math.min(E, Math.max(op.t.s + SNAP, op.s0 + sn(op.e0 - op.s0 + dh)));
        else { const d = op.e0 - op.s0; op.t.s = Math.min(E - d, Math.max(S, sn(op.s0 + dh))); op.t.e = op.t.s + d; }
        op.b.style.left = (op.t.s - S) * PX + 'px'; op.b.style.width = (op.t.e - op.t.s) * PX + 'px';
        $(op.b, 'small').textContent = `${hm(op.t.s)}–${hm(op.t.e)}`;
      });
      board.addEventListener('pointerup', e => {
        if (!op) { const b = e.target.closest('[data-g2-task]'); if (b && live()) showPop(task(b.dataset.g2Task), b); return; }
        const o = op; op = null;
        if (o.locked) { if (Math.abs(e.clientX - o.x0) > 3) toast('QC Hold terkunci sampai QA memutuskan.', 'warn'); else showPop(o.t, o.b); return; }
        if (!o.moved) return showPop(o.t, o.b);
        if (o.t.s !== o.s0 || o.t.e !== o.e0) touch(o.t, o.rz ? 'resize' : 'move');
        render();
      });
      function closePop() { if (pop) { pop.remove(); pop = null; } }
      function showPop(t, b) {
        closePop();
        pop = document.createElement('div'); pop.className = 'dl-gantt__pop';
        pop.innerHTML = `<button type="button" class="dl-gantt__popx" aria-label="Tutup">&times;</button><h3>${esc(t.label)}</h3><dl><dt>Resource</dt><dd>${esc(rows.find(r => r.key === t.row)?.label || '')}</dd><dt>Proses</dt><dd>${esc(catOf(t).label)}</dd><dt>Waktu</dt><dd>${hm(t.s)}–${hm(t.e)}</dd><dt>Progress</dt><dd>${+t.progress || 0}%</dd><dt>Status</dt><dd>${esc(t.status || {hot: 'Hot Job', qchold: 'QC Hold', breakdown: 'Breakdown'}[t.state] || 'Terjadwal')}</dd></dl>`;
        const r = b.getBoundingClientRect(); pop.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 260)}px;top:${r.bottom + scrollY + 6}px`;
        pop.querySelector('.dl-gantt__popx').addEventListener('click', closePop);
        document.body.appendChild(pop);
      }
      board.addEventListener('dblclick', e => {
        const b = e.target.closest('[data-g2-task]'); if (!b || live()) return;
        closePop();
        const t = task(b.dataset.g2Task);
        if (t.state === 'qchold') { toast('QC Hold terkunci sampai QA memutuskan.', 'warn'); return; }
        const ov = document.createElement('div'); ov.className = 'dl-mov is-on';
        ov.innerHTML = `<div class="dl-modal dl-modal--sm" role="dialog" aria-modal="true"><div class="dl-modal__head"><h3>Ubah Task</h3><button type="button" class="dl-modal__x" data-x>&times;</button></div>
          <div class="dl-modal__body dl-gantt__edit"><label><span>Kode</span><input value="${esc(t.label)}" disabled></label>
          <label><span>Resource</span><select data-e-row>${rows.map(r => `<option value="${esc(r.key)}" ${r.key === t.row ? 'selected' : ''}>${esc(r.label)}${r.section ? ' (' + esc(r.section.split(' ')[0].toLowerCase()) + ')' : ''}</option>`).join('')}</select></label>
          <label><span>Proses</span><select data-e-proc>${Object.entries(cats).map(([k, c]) => `<option value="${esc(k)}" ${k === t.process ? 'selected' : ''}>${esc(c.label)}</option>`).join('')}</select></label></div>
          <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--ghost dl-btn--md" data-x>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-e-save>Simpan</button></div></div>`;
        ov.addEventListener('click', ev => {
          if (ev.target === ov || ev.target.closest('[data-x]')) return ov.remove();
          if (ev.target.closest('[data-e-save]')) { const nr = $(ov, '[data-e-row]').value, np = $(ov, '[data-e-proc]').value; if (nr !== t.row || np !== t.process) { t.row = nr; t.process = np; touch(t, 'reassign'); toast(`${t.label} dipindah ke ${rows.find(r => r.key === nr).label}.`, 'success'); } ov.remove(); render(); }
        });
        document.body.appendChild(ov); $(ov, '[data-e-row]').focus();
      });
      el.addEventListener('click', e => {
        const s = e.target.closest('[data-g2-hi]'); if (s) { const k = s.dataset.g2Hi; hi = k === 'all' || k === 'none' || hi === k ? null : k; return render(); }
        const c = e.target.closest('[data-g2-chip]'); if (c) { if (c.dataset.g2Chip !== 'proc') { fRes = ''; setSel('gantt_resource', ''); } if (c.dataset.g2Chip !== 'res') { fProc = ''; setSel('gantt_process', ''); } return render(); }
        const m = e.target.closest('[data-g2-mode] .dl-segment__opt'); if (m) { el.dataset.mode = m.dataset.value; $$(el, '[data-g2-mode] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === m)); closePop(); return render(); }
        if (e.target.closest('[data-g2-submit]')) confirmBox({message: `Kirim ${changed.size} perubahan simulasi ke Master Schedule?`, tone: 'warn'}).then(ok => { if (!ok) return; el.dispatchEvent(new CustomEvent('dl:gantt-submit', {bubbles: true, detail: {tasks: tasks.filter(t => changed.has(t.id)).map(t => ({...t, start: hm(t.s), end: hm(t.e)}))}})); toast(`${changed.size} perubahan dikirim ke Master Schedule.`, 'success'); changed.clear(); render(); });
      });
      el.addEventListener('change', e => {
        if (e.target.name === 'gantt_resource') { fRes = e.target.value; render(); }
        if (e.target.name === 'gantt_process') { fProc = e.target.value; render(); }
      });
      document.addEventListener('pointerdown', e => { if (pop && !e.target.closest('.dl-gantt__pop,[data-g2-task]')) closePop(); });
      legend(); render();
      el.dlGantt = {tasks, rows, render, changed};
    }
  }
  Devlib.define("Gantt", Gantt);
})(window.Devlib);
