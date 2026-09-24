/* DevLib · Formation */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, emit} = Devlib.h;
  /* ============================ FORMASI HARIAN ============================ */
  class Formation extends Devlib.Component {
    static selector = "[data-dl-formation]";
    init() {
      const el = this.el;
      const J = k => JSON.parse(el.dataset[k] || 'null');
      const POS = J('positions') || [], SH = J('shifts') || {}, DAYS = J('days') || [], PEOPLE = J('people') || [];
      let asg = (J('assign') || []).map(a => ({...a, days: [...a.days]}));
      const who = Object.fromEntries(PEOPLE.map(p => [String(p.id), p]));
      const N = DAYS.length, ALL = [...Array(N).keys()];
      const st = {mode: 'week', day: +el.dataset.today || 0, filter: null, q: '', edit: null};
      const locked = () => el.dataset.status === 'PUBLISHED';
      const certOk = (p, pos) => !p.certs || p.certs.includes(pos);
      const dayName = i => DAYS[i]?.key || '';
      // Label hari: semua hari -> "Sen–Sab", berurutan -> "Sen–Rab", satu hari -> "Sen saja", acak -> "Sen, Rab".
      const rangeLabel = ds => { const d = [...ds].sort((a, b) => a - b); if (d.length === N) return `${dayName(0)}–${dayName(N - 1)}`; if (d.length === 1) return `${dayName(d[0])} saja`; return d.every((x, i) => !i || x === d[i - 1] + 1) ? `${dayName(d[0])}–${dayName(d[d.length - 1])}` : d.map(dayName).join(', '); };
      const slotOf = (pos, sh) => asg.filter(a => a.pos === pos && a.shift === sh);
      // Titik lain orang ini pada hari yang sama (untuk chip ⇄).
      const elsewhere = (a, days) => asg.find(b => b !== a && String(b.id) === String(a.id) && b.days.some(d => days.includes(d)));
      const posName = code => POS.find(p => p.code === code)?.name || code;
      const onDay = (a, d) => a.days.includes(d);

      function chip(a, days) {
        const p = who[a.id] || {name: a.id}, other = elsewhere(a, days), warn = !certOk(p, a.pos);
        const hit = st.q && p.name.toLowerCase().includes(st.q);
        const cls = ['dl-fm__chip', p.borongan ? 'is-bor' : '', other ? 'is-multi' : '', warn ? 'is-warn' : '', hit ? 'is-hit' : ''].join(' ');
        const tip = [warn ? 'Belum bersertifikat unit ini' : '', other ? `Juga di ${posName(other.pos)} · ${SH[other.shift]?.label || other.shift}` : ''].filter(Boolean).join(' · ');
        return `<span class="${cls}" ${tip ? `title="${esc(tip)}"` : ''}>${warn ? '⚠ ' : ''}${esc(p.name)}${p.borongan ? ' · borongan' : ''}${other ? ` ⇄ ${esc(posName(other.pos))}` : ''}</span>`;
      }

      // Isi sel ringkas seminggu: penuh -> chip; sisanya dikelompokkan per pola hari.
      function weekCell(list) {
        const full = list.filter(a => a.days.length === N), part = list.filter(a => a.days.length && a.days.length < N);
        const groups = [];
        part.forEach(a => { const k = [...a.days].sort((x, y) => x - y).join(','); let g = groups.find(x => x.k === k); if (!g) groups.push(g = {k, days: a.days.slice().sort((x, y) => x - y), items: []}); g.items.push(a); });
        groups.sort((a, b) => a.days[0] - b.days[0]);
        const contiguous = g => g.days.every((x, i) => !i || x === g.days[i - 1] + 1);
        const swap = !full.length && groups.length >= 2 && groups.every(g => contiguous(g) && g.items.every(a => !who[a.id]?.borongan))
          && groups.every((g, i) => !i || g.days[0] === groups[i - 1].days[groups[i - 1].days.length - 1] + 1);
        if (swap) return {swap: true, html: groups.map(g => `<span class="dl-fm__when">${esc(rangeLabel(g.days))}</span> ${g.items.map(a => chip(a, g.days)).join('')}`).join('<i class="fa-solid fa-arrow-right dl-fm__arrow"></i>') + '<span class="dl-fm__badge">BERGANTI</span>'};
        return {html: full.map(a => chip(a, ALL)).join('') + groups.map(g => `<span class="dl-fm__when is-plus">+ ${esc(rangeLabel(g.days))}:</span> ${g.items.map(a => chip(a, g.days)).join('')}`).join('')};
      }

      function rows() {
        const out = [];
        POS.forEach(p => (p.shifts?.length ? p.shifts : ['']).forEach(sh => {
          const list = slotOf(p.code, sh);
          const shown = st.mode === 'day' ? list.filter(a => onDay(a, st.day)) : list.filter(a => a.days.length);
          out.push({p, sh, list, shown});
        }));
        return out;
      }
      const posEmpty = (code, d) => !asg.some(a => a.pos === code && (d == null ? a.days.length : onDay(a, d)));
      const unscheduled = () => PEOPLE.filter(p => !p.borongan && !asg.some(a => String(a.id) === String(p.id) && a.days.length));

      function drawStats() {
        const d = st.day, today = asg.filter(a => onDay(a, d));
        const empty = POS.filter(p => posEmpty(p.code, d));
        const cards = [['all', `Terjadwal · ${dayName(d)}`, new Set(today.map(a => String(a.id))).size, DAYS[d]?.date || '', '']]
          .concat(Object.entries(SH).map(([k, s]) => [k, `${s.label} · ${String(s.time || '').split('–')[0]}`, new Set(today.filter(a => a.shift === k).map(a => String(a.id))).size, '', {blue: '', amber: 'warn', slate: 'neutral'}[s.tone] ?? '']))
          .concat([['bor', 'Borongan dipanggil', new Set(today.filter(a => who[a.id]?.borongan).map(a => String(a.id))).size, '', 'violet'],
            ['empty', 'Unit kosong', empty.length, empty.map(p => p.name).join(', '), 'danger'],
            ['unsched', 'Mingguan belum terjadwal', unscheduled().length, '', 'success']]);
        $(el, '[data-fm-stats]').innerHTML = cards.map(([k, l, v, sub, tone]) => `<button type="button" class="dl-stat is-filter ${tone ? 'dl-stat--' + tone : ''} ${(st.filter || 'all') === k ? 'is-active' : ''}" data-fm-f="${k}"><span class="dl-stat__label">${esc(l)}</span><span class="dl-stat__value">${v}</span>${sub ? `<span class="dl-stat__sub">${esc(sub)}</span>` : ''}</button>`).join('');
      }

      function drawBody() {
        let list = rows();
        const f = st.filter, d = st.mode === 'day' ? st.day : null;
        if (f && SH[f]) list = list.filter(r => r.sh === f);
        if (f === 'bor') list = list.filter(r => r.shown.some(a => who[a.id]?.borongan));
        if (f === 'empty') list = list.filter(r => posEmpty(r.p.code, st.day));
        if (st.q) list = list.filter(r => r.shown.some(a => (who[a.id]?.name || '').toLowerCase().includes(st.q)));
        const byPos = []; list.forEach(r => { const g = byPos[byPos.length - 1]; if (g && g.p === r.p) g.rows.push(r); else byPos.push({p: r.p, rows: [r]}); });
        $(el, '[data-fm-body]').innerHTML = byPos.map((g, gi) => {
          const noIdx = POS.indexOf(g.p) + 1, wholeEmpty = posEmpty(g.p.code, d);
          if (wholeEmpty && g.rows.every(r => !r.shown.length) && g.rows.length > 0 && (!g.p.shifts?.length)) {
            return `<tr class="is-empty"><td class="dl-fm__no">${noIdx}</td><td class="dl-fm__pos">${esc(g.p.name)}</td><td class="dl-fm__sh is-reg">—</td><td class="dl-fm__empty">Kosong ${d == null ? 'minggu ini' : 'hari ini'} <small>· klik ✎ untuk isi</small></td><td class="dl-fm__ed"><button type="button" data-fm-edit="${esc(g.p.code)}|" title="Ubah" aria-label="Ubah"><i class="fa-solid fa-pen"></i></button></td></tr>`;
          }
          return g.rows.map((r, i) => {
            const s = SH[r.sh] || {}, cell = st.mode === 'day' ? {html: r.shown.map(a => chip(a, [st.day])).join('')} : weekCell(r.shown);
            const sel = st.edit && st.edit.pos === g.p.code && st.edit.sh === r.sh;
            return `<tr class="${cell.swap ? 'is-swap' : ''} ${!r.shown.length ? 'is-empty' : ''} ${sel ? 'is-sel' : ''}">${i ? '' : `<td class="dl-fm__no" rowspan="${g.rows.length}">${noIdx}</td><td class="dl-fm__pos" rowspan="${g.rows.length}">${esc(g.p.name)}</td>`}<td class="dl-fm__sh is-${esc(s.tone || 'slate')}"><b>${esc(s.label || '—')}</b> <small>${esc(s.time || '')}</small></td><td class="dl-fm__who">${r.shown.length ? cell.html : `<span class="dl-fm__empty">Kosong ${d == null ? 'minggu ini' : 'hari ini'} <small>· klik ✎ untuk isi</small></span>`}</td><td class="dl-fm__ed"><button type="button" data-fm-edit="${esc(g.p.code)}|${esc(r.sh)}" title="Ubah" aria-label="Ubah"><i class="fa-solid fa-pen"></i></button></td></tr>`;
          }).join('');
        }).join('') || `<tr><td colspan="5" class="dl-fm__none">Tidak ada baris yang cocok.</td></tr>`;
        $(el, '[data-fm-colhead]').textContent = st.mode === 'day' ? `Yang bertugas (${dayName(st.day)}, ${DAYS[st.day]?.date || ''})` : `Yang bertugas (${dayName(0)}–${dayName(N - 1)}, ${DAYS[0]?.date || ''} – ${DAYS[N - 1]?.date || ''})`;
        $$(el, '[data-fm-mode] [data-m]').forEach(b => b.classList.toggle('is-active', b.dataset.m === st.mode));
        $(el, '[data-fm-days]').innerHTML = DAYS.map((x, i) => `<button type="button" class="dl-fm__day ${st.mode === 'day' && st.day === i ? 'is-active' : ''} ${i === +el.dataset.today ? 'is-today' : ''}" data-fm-day="${i}">${esc(x.key)} ${esc(String(x.date).split(' ')[0])}${i === +el.dataset.today ? ' · hari ini' : ''}</button>`).join('');
      }

      function drawSide() {
        const side = $(el, '[data-fm-side]');
        if (!st.edit) {
          const un = unscheduled(), groups = {};
          un.forEach(p => { (groups[p.group || 'Lainnya'] ||= []).push(p); });
          side.innerHTML = `<div class="dl-fm__sidehead"><b>Belum terjadwal minggu ini</b><small>${un.length} orang · klik ✎ di baris untuk menempatkan</small></div><div class="dl-fm__sidebody">${Object.entries(groups).map(([g, ps]) => `<div class="dl-fm__grp">${esc(g)}</div><div>${ps.map(p => `<span class="dl-fm__chip">${esc(p.name)}</span>`).join('')}</div>`).join('') || '<p class="dl-fm__none">Semua sudah terjadwal.</p>'}</div>`;
          side.classList.toggle('is-flash', st.filter === 'unsched');
          return;
        }
        const e = st.edit, p = POS.find(x => x.code === e.pos), s = SH[e.sh];
        const taken = new Set(asg.filter(a => a.days.length).map(a => String(a.id))), inRow = new Set(e.rows.map(r => String(r.id)));
        const cand = PEOPLE.filter(x => !inRow.has(String(x.id)) && !taken.has(String(x.id)) && (!p.group || x.group === p.group || x.borongan));
        side.classList.remove('is-flash');
        side.innerHTML = `<div class="dl-fm__sidehead"><b>${esc(p.name)}${s ? ' · ' + esc(s.label) : ''}</b><small>Centang hari masuknya tiap orang</small></div>
          <table class="dl-fm__ed-t"><thead><tr><th>Nama</th>${DAYS.map(x => `<th>${esc(x.key[0] + (x.key[2] || ''))}</th>`).join('')}<th></th></tr></thead><tbody>${e.rows.map((r, i) => { const pp = who[r.id] || {name: r.id}; return `<tr><td>${certOk(pp, e.pos) ? '' : '<span class="dl-fm__warn" title="Belum bersertifikat unit ini">⚠</span> '}${esc(pp.name)}</td>${ALL.map(d => `<td><input type="checkbox" data-fm-cb="${i}|${d}" ${r.days.includes(d) ? 'checked' : ''} aria-label="${esc(pp.name)} ${esc(dayName(d))}"></td>`).join('')}<td><button type="button" class="dl-fm__x" data-fm-rm="${i}" aria-label="Lepas">✕</button></td></tr>`; }).join('') || `<tr><td colspan="${N + 2}" class="dl-fm__none">Belum ada orang.</td></tr>`}</tbody></table>
          <div class="dl-fm__sidebody">
            <div class="dl-grid__search"><i class="fa-solid fa-user-plus"></i><input type="search" list="dl-fm-dl-${el.dataset.uid}" placeholder="+ Tambah orang (cari nama)…" data-fm-add aria-label="Tambah orang"></div>
            <datalist id="dl-fm-dl-${el.dataset.uid}">${PEOPLE.filter(x => !inRow.has(String(x.id))).map(x => `<option value="${esc(x.name)}">`).join('')}</datalist>
            <div class="dl-fm__grp">Belum terjadwal · ${esc(p.group || 'semua')}</div>
            <div>${cand.map(x => `<button type="button" class="dl-fm__chip ${x.borongan ? 'is-bor' : ''} ${certOk(x, e.pos) ? '' : 'is-warn'}" data-fm-pick="${esc(x.id)}">${certOk(x, e.pos) ? '' : '⚠ '}${esc(x.name)}</button>`).join('') || '<span class="dl-fm__none">Tidak ada.</span>'}</div>
            <div class="dl-fm__sideact"><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-fm-cancel>Batal</button><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-fm-apply>Simpan</button></div>
          </div>`;
      }

      const draw = () => { drawStats(); drawBody(); drawSide(); drawStatus(); };
      function drawStatus() {
        const pub = locked();
        $(el, '[data-fm-status]').textContent = pub ? 'PUBLISHED' : 'DRAFT';
        $(el, '[data-fm-status]').classList.toggle('is-pub', pub);
        $$(el, '[data-fm-publish],[data-fm-save],[data-fm-copy]').forEach(b => { b.disabled = pub; });
        el.classList.toggle('is-locked', pub);
      }
      const emit = (n, detail) => el.dispatchEvent(new CustomEvent('dl:formation-' + n, {bubbles: true, detail}));
      const addPerson = id => { if (!st.edit || st.edit.rows.some(r => String(r.id) === String(id))) return; st.edit.rows.push({id, days: [...ALL]}); drawSide(); };

      el.addEventListener('click', e => {
        const x = e.target;
        const f = x.closest('[data-fm-f]'); if (f) { const k = f.dataset.fmF; st.filter = k === 'all' || st.filter === k ? null : k; if (k !== 'all' && k !== 'unsched' && k !== 'empty' && st.mode === 'week') { st.mode = 'day'; } return draw(); }
        const m = x.closest('[data-fm-mode] [data-m]'); if (m) { st.mode = m.dataset.m; return draw(); }
        const dy = x.closest('[data-fm-day]'); if (dy) { st.mode = 'day'; st.day = +dy.dataset.fmDay; return draw(); }
        const ed = x.closest('[data-fm-edit]'); if (ed) {
          if (locked()) return toast('Formasi sudah dipublish — tidak bisa diubah.', 'warn');
          const [pos, sh] = ed.dataset.fmEdit.split('|');
          const shift = sh || (POS.find(p => p.code === pos)?.shifts || [])[0] || '';
          st.edit = {pos, sh: shift, rows: slotOf(pos, shift).map(a => ({id: a.id, days: [...a.days]}))}; drawBody(); drawSide();
          $(el, '[data-fm-side]').scrollIntoView({block: 'nearest'}); return;
        }
        const rm = x.closest('[data-fm-rm]'); if (rm) { st.edit.rows.splice(+rm.dataset.fmRm, 1); return drawSide(); }
        const pk = x.closest('[data-fm-pick]'); if (pk) { const p = PEOPLE.find(q => String(q.id) === pk.dataset.fmPick); if (p && !certOk(p, st.edit.pos)) toast(`${p.name} belum bersertifikat ${posName(st.edit.pos)}.`, 'warn'); return addPerson(pk.dataset.fmPick); }
        if (x.closest('[data-fm-cancel]')) { st.edit = null; drawBody(); return drawSide(); }
        if (x.closest('[data-fm-apply]')) {
          const e2 = st.edit;
          asg = asg.filter(a => !(a.pos === e2.pos && a.shift === e2.sh)).concat(e2.rows.filter(r => r.days.length).map(r => ({pos: e2.pos, shift: e2.sh, id: r.id, days: [...r.days].sort((a, b) => a - b)})));
          st.edit = null; el.dataset.dirty = '1'; draw();
          emit('change', {assign: asg}); toast(`Formasi ${posName(e2.pos)} diperbarui.`, 'success'); return;
        }
        const wk = x.closest('[data-fm-week]'); if (wk) { emit('week', {dir: +wk.dataset.fmWeek}); return toast(`Pindah ke minggu ${+wk.dataset.fmWeek < 0 ? 'sebelumnya' : 'berikutnya'} (pratinjau).`); }
        if (x.closest('[data-fm-save]')) { delete el.dataset.dirty; emit('save', {assign: asg}); return toast('Formasi disimpan (Draft).', 'success'); }
        if (x.closest('[data-fm-print]')) return window.print();
        if (x.closest('[data-fm-copy]')) return confirmBox({message: 'Salin formasi minggu lalu? Isi minggu ini akan diganti.'}).then(ok => { if (ok) { emit('copy', {}); toast('Formasi minggu lalu disalin (pratinjau).', 'success'); } });
        if (x.closest('[data-fm-publish]')) return confirmBox({message: 'Publish formasi minggu ini? Setelah publish, formasi terkunci.', tone: 'warn'}).then(ok => { if (!ok) return; el.dataset.status = 'PUBLISHED'; st.edit = null; draw(); emit('publish', {assign: asg}); toast('Formasi dipublish.', 'success'); });
      });
      el.addEventListener('change', e => {
        const cb = e.target.closest('[data-fm-cb]'); if (!cb) return;
        const [i, d] = cb.dataset.fmCb.split('|').map(Number), r = st.edit.rows[i];
        r.days = cb.checked ? [...new Set([...r.days, d])] : r.days.filter(x => x !== d);
      });
      el.addEventListener('input', e => { if (e.target.matches('[data-fm-search]')) { st.q = e.target.value.trim().toLowerCase(); drawBody(); } });
      el.addEventListener('keydown', e => {
        if (e.key !== 'Enter' || !e.target.matches('[data-fm-add]')) return;
        e.preventDefault(); const v = e.target.value.trim().toLowerCase(), p = PEOPLE.find(q => q.name.toLowerCase() === v) || PEOPLE.find(q => q.name.toLowerCase().includes(v));
        if (!p) return toast('Nama tidak ditemukan.', 'warn');
        if (!certOk(p, st.edit.pos)) toast(`${p.name} belum bersertifikat ${posName(st.edit.pos)}.`, 'warn');
        addPerson(p.id);
      });
      el.addEventListener('change', e => { if (e.target.matches('[data-fm-add]') && e.target.value) { const p = PEOPLE.find(q => q.name === e.target.value); if (p) addPerson(p.id); } });
      el.dataset.uid = Math.random().toString(36).slice(2, 8);
      $(el, '[data-fm-legend]').innerHTML = Object.values(SH).map(s => `<span><i class="dl-fm__swatch is-${esc(s.tone || 'slate')}"></i> ${esc(s.label)}</span>`).join('')
        + '<span><span class="dl-fm__chip is-bor">borongan</span> dipanggil saat kerja berlebih</span><span><span class="dl-fm__chip is-multi">⇄</span> di 2 titik hari itu</span><span><span class="dl-fm__chip is-warn">⚠</span> belum bersertifikat unit ini</span>';
      el.dlFormation = {get assign() { return asg.map(a => ({...a, days: [...a.days]})); }, redraw: draw};
      draw();
    }
  }
  Devlib.define("Formation", Formation);
})(window.Devlib);
