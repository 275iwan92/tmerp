/* DevLib · Grid */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, fmt, at, val, emit} = Devlib.h;
  /* ============================ GRID ============================ */
  // Mesin grid sisi-browser (DevLib, tanpa DB). Di tmerp, versi server (Livewire) memakai
  // markup & kelas yang sama; hanya sumber data & query-nya yang berpindah ke server.
  class Grid extends Devlib.Component {
    static selector = "[data-dl-grid]";
    init() {
      const root = this.el;
      const cfg = JSON.parse(root.dataset.config), rows = JSON.parse(root.dataset.rows || '[]'), cols = cfg.columns, hasExp = cfg.expand.length > 0 || !!cfg.detail;
      const saveKey = 'dl-grid-' + cfg.id;
      let saved = {}; try { saved = JSON.parse(localStorage.getItem(saveKey) || '{}'); } catch (e) {}
      const st = Object.assign({search: '', sort: null, dir: 1, stat: null, tab: 0, filters: {}, hidden: cols.filter(c => !c.visible).map(c => c.key),
        group: cfg.groupBy || '', view: 'table', page: 1, perPage: cfg.perPage, density: 'normal', lines: 'fixed', linesN: 1, collapsed: [], open: []}, saved, {page: 1, search: ''});
      const sel = new Set(); let lastIdx = null;
      const persist = () => { try { localStorage.setItem(saveKey, JSON.stringify({sort: st.sort, dir: st.dir, hidden: st.hidden, group: st.group, perPage: st.perPage, density: st.density, lines: st.lines, linesN: st.linesN, filters: st.filters, tab: st.tab})); } catch (e) {} };
      const q = s => $(root, s), qq = s => $$(root, s);
      const shown = () => cols.filter(c => !st.hidden.includes(c.key));
      // true = tidak ada induk yang menangani (grid berdiri sendiri -> tampilkan pratinjau).
      const emit = (name, detail) => root.dispatchEvent(new CustomEvent(name, {bubbles: true, cancelable: true, detail}));
      const val = (r, k) => r[k] ?? '';
      const match = (r, where) => !where || Object.entries(where).every(([k, v]) => Array.isArray(v) ? v.map(String).includes(String(val(r, k))) : String(val(r, k)) === String(v));
      const fmtCell = (c, v) => c.type === 'money' ? fmt.money(v) : c.type === 'number' ? fmt.number(v, String(v).includes('.') ? 2 : 0) : c.type === 'date' ? (v ? fmt.date(v) : '') : v;

      function filtered(opts = {}) {
        const term = st.search.trim().toLowerCase(), vis = shown();
        return rows.filter(r =>
          (opts.noStat || st.stat === null || match(r, cfg.stats[st.stat].where)) &&
          (opts.noTab || !cfg.tabs.length || match(r, cfg.tabs[st.tab].where)) &&
          Object.entries(st.filters).every(([k, v]) => v === '' || String(val(r, k)) === v) &&
          (!term || vis.some(c => String(fmtCell(c, val(r, c.key))).toLowerCase().includes(term) || (c.code && String(val(r, c.code)).toLowerCase().includes(term)))));
      }
      function sorted(list) {
        if (!st.sort) return list;
        const c = cols.find(x => x.key === st.sort), num = ['number', 'money'].includes(c && c.type);
        return [...list].sort((a, b) => { const x = val(a, st.sort), y = val(b, st.sort); return (num ? (+x - +y) : String(x).localeCompare(String(y), 'id', {numeric: true})) * st.dir; });
      }

      // ---- kotak statistik & tab (angka dihitung dari seluruh data, bukan hasil saringan) ----
      function drawStats() {
        const box = q('[data-g-stats]'); if (!box) return;
        const base = filtered({noStat: true});
        box.innerHTML = cfg.stats.map((s, i) => `<button type="button" class="dl-stat dl-stat--${esc(s.tone || 'accent')} is-filter ${st.stat === i ? 'is-active' : ''}" data-i="${i}"><span class="dl-stat__label">${esc(s.label)}</span><span class="dl-stat__value">${fmt.number(base.filter(r => match(r, s.where)).length)}</span></button>`).join('');
      }
      function drawTabs() {
        const bar = q('[data-g-tabs]'); if (!bar) return;
        const base = filtered({noTab: true, noStat: true});
        bar.innerHTML = cfg.tabs.map((t, i) => `<button type="button" class="dl-tabs__tab ${st.tab === i ? 'is-active' : ''}" data-i="${i}">${esc(t.label)} <span class="dl-grid__tabcount">${base.filter(r => match(r, t.where)).length}</span></button>`).join('');
      }

      // ---- kepala tabel ----
      function drawHead() {
        const vis = shown();
        q('[data-g-head]').innerHTML = '<tr><th class="dl-grid__chk"><input type="checkbox" data-g-all aria-label="Pilih semua di halaman"></th>' + (hasExp ? '<th class="dl-grid__exp"></th>' : '')
          + vis.map(c => {
            const on = st.sort === c.key, arrow = on ? (st.dir > 0 ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short') : 'fa-sort';
            return `<th class="${['number', 'money'].includes(c.type) ? 'num' : ''} ${c.sortable ? 'is-sortable' : ''} ${on ? 'is-sorted' : ''}" data-key="${esc(c.key)}" ${c.width ? `style="width:${esc(c.width)}"` : ''}>${esc(c.label)}${c.sortable ? ` <i class="fa-solid ${arrow}"></i>` : ''}</th>`;
          }).join('') + (cfg.actions !== 'none' ? '<th class="dl-grid__act">Aksi</th>' : '') + '</tr>';
      }

      // ---- sel ----
      function cell(c, r) {
        const v = val(r, c.key);
        switch (c.type) {
          case 'identity': {
            const name = String(v || '?'), w = name.trim().split(/\s+/), ini = (w[0][0] + (w[1] ? w[1][0] : '')).toUpperCase();
            let h = 0; for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % 360;
            return `<td><span class="dl-avatar-id"><span class="dl-avatar-id__pic" style="--h:${h}">${esc(ini)}</span><span class="dl-avatar-id__txt"><b>${esc(name)}</b>${c.code ? `<small>${esc(val(r, c.code))}</small>` : ''}</span></span></td>`;
          }
          case 'badge': return `<td><span class="dl-badge dl-badge--pill dl-badge--${esc((c.tones || {})[v] || 'neutral')}">${esc(v)}</span></td>`;
          case 'money': case 'number': return `<td class="num">${esc(fmtCell(c, v))}</td>`;
          case 'image': return `<td class="dl-grid__imgcell">${v ? `<button type="button" class="dl-grid__thumb" data-g-img="${esc(v)}" title="Perbesar"><img src="${esc(v)}" alt="" loading="lazy"></button>` : '<span class="dl-grid__noimg"><i class="fa-regular fa-image"></i></span>'}</td>`;
          case 'progress': { const n = Math.max(0, Math.min(100, +v || 0)), t = n >= 100 ? 'success' : n >= 60 ? 'accent' : n >= 30 ? 'warn' : 'danger'; return `<td><span class="dl-grid__prog dl-chart--${t}"><span style="width:${n}%"></span></span><small class="dl-grid__progv">${n}%</small></td>`; }
          case 'steps': { const at = (c.steps || []).indexOf(v); return `<td><span class="dl-grid__steps" title="${esc(v)}">${(c.steps || []).map((s2, i) => `<i class="${i < at ? 'is-done' : i === at ? 'is-now' : ''}" title="${esc(s2)}"></i>`).join('')}</span><small class="dl-grid__stepv">${esc(v)}</small></td>`; }
          case 'toggle': return `<td><label class="dl-switch dl-switch--sm"><input type="checkbox" data-g-cell="${esc(c.key)}" ${v ? 'checked' : ''}><span class="dl-switch__slider"></span></label></td>`;
          case 'rating': return `<td class="dl-grid__stars" title="${esc(v)} / 5">${[1, 2, 3, 4, 5].map(i => `<i class="fa-solid fa-star ${i <= Math.round(+v || 0) ? 'is-on' : ''}"></i>`).join('')}</td>`;
          case 'spark': { const d = (Array.isArray(v) ? v : []).map(Number); if (d.length < 2) return '<td></td>'; const mn = Math.min(...d), mx = Math.max(...d); const pts = d.map((y, i) => `${(i / (d.length - 1) * 80).toFixed(1)},${(22 - (mx > mn ? (y - mn) / (mx - mn) : .5) * 20).toFixed(1)}`).join(' '); return `<td><svg class="dl-grid__spark ${d[d.length - 1] < d[0] ? 'is-down' : ''}" viewBox="0 0 80 24"><polyline points="${pts}"/></svg></td>`; }
          case 'button': return `<td><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-g-action="${esc(c.action || c.key)}">${esc(c.text || c.label)}</button></td>`;
          default: return `<td><div class="dl-grid__txt">${esc(fmtCell(c, v))}</div></td>`;
        }
      }
      function actionsCell(r) {
        if (cfg.actions === 'none') return '';
        const id = esc(r[cfg.rowKey]);
        if (cfg.actions === 'worklist') return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="open" data-id="${id}" title="Buka (klik 2x baris)"><i class="fa-solid fa-up-right-from-square"></i></button>${r.status === 'SUBMITTED' ? `<button type="button" class="dl-iconbtn dl-iconbtn--success" data-g-act="confirm" data-id="${id}" title="Confirm" data-dl-confirm="Confirm dokumen ini?"><i class="fa-solid fa-circle-check"></i></button>` : ''}</td>`;
        const on = !!r[cfg.activeKey];
        return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="edit" data-id="${id}" title="Edit (klik 2x baris)"><i class="fa-solid fa-pen"></i></button><button type="button" class="dl-iconbtn dl-iconbtn--${on ? 'danger' : 'success'}" data-g-act="toggle" data-id="${id}" title="${on ? 'Nonaktifkan' : 'Aktifkan'}" data-dl-confirm="${on ? 'Nonaktifkan' : 'Aktifkan'} data ini?"><i class="fa-solid ${on ? 'fa-ban' : 'fa-circle-check'}"></i></button></td>`;
      }
      function rowHtml(r, idx) {
        const id = String(r[cfg.rowKey]), open = st.open.includes(id), span = shown().length + 1 + (hasExp ? 1 : 0) + (cfg.actions !== 'none' ? 1 : 0);
        let h = `<tr class="dl-grid__row ${sel.has(id) ? 'is-selected' : ''} ${cfg.activeKey && r[cfg.activeKey] === false ? 'is-inactive' : ''}" data-id="${esc(id)}" data-idx="${idx}" title="Klik 2x untuk membuka">`
          + `<td class="dl-grid__chk"><input type="checkbox" ${sel.has(id) ? 'checked' : ''} aria-label="Pilih baris"></td>`
          + (hasExp ? `<td class="dl-grid__exp"><button type="button" class="dl-grid__chev ${open ? 'is-open' : ''}" data-g-exp aria-label="Buka detail"><i class="fa-solid fa-chevron-right"></i></button></td>` : '')
          + shown().map(c => cell(c, r)).join('') + actionsCell(r) + '</tr>';
        if (open && cfg.detail) { const D = cfg.detail, kids = r[D.key] || []; h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__nest"><b>${esc(D.title || 'Detail')} · ${kids.length} baris</b><table><thead><tr>${D.columns.map(c => `<th class="${['number', 'money'].includes(c.type) ? 'num' : ''}">${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${kids.map(k => `<tr>${D.columns.map(c => `<td class="${['number', 'money'].includes(c.type) ? 'num' : ''}">${esc(fmtCell(c, k[c.key] ?? ''))}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${D.columns.length}">Tidak ada detail</td></tr>`}</tbody></table></div></td></tr>`; }
        else if (open) h += `<tr class="dl-grid__detail"><td colspan="${span}"><div class="dl-grid__detailbox">${cfg.expand.map(k => { const c = cols.find(x => x.key === k) || {key: k, label: k}; return `<div><small>${esc(c.label)}</small><b>${esc(fmtCell(c, val(r, k)) || '—')}</b></div>`; }).join('')}</div></td></tr>`;
        return h;
      }

      // ---- badan tabel (+ grup) & kaki (subtotal) ----
      let current = [];
      function drawBody() {
        const list = sorted(filtered()), total = list.length, pages = Math.max(1, Math.ceil(total / st.perPage));
        st.page = Math.min(st.page, pages);
        const page = list.slice((st.page - 1) * st.perPage, st.page * st.perPage);
        current = page;
        const span = shown().length + 1 + (hasExp ? 1 : 0) + (cfg.actions !== 'none' ? 1 : 0), sums = shown().filter(c => c.sum);
        let html = '';
        if (!page.length) html = `<tr><td colspan="${span}"><div class="dl-empty"><span class="dl-empty__icon"><i class="fa-solid fa-magnifying-glass"></i></span><b class="dl-empty__title">Tidak ada data yang cocok</b><p class="dl-empty__msg">Ubah kata kunci, lepas filter, atau pilih tab lain.</p></div></td></tr>`;
        else if (st.group) {
          const gcol = cols.find(c => c.key === st.group), buckets = new Map();
          page.forEach((r, i) => { const g = String(val(r, st.group)) || '(kosong)'; if (!buckets.has(g)) buckets.set(g, []); buckets.get(g).push([r, i]); });
          buckets.forEach((items, g) => {
            const shut = st.collapsed.includes(g);
            html += `<tr class="dl-grid__group"><td colspan="${span}"><button type="button" data-g-grp="${esc(g)}"><i class="fa-solid fa-chevron-${shut ? 'right' : 'down'}"></i> ${esc(gcol ? gcol.label : st.group)}: <b>${esc(g)}</b> <span>${items.length} baris</span>${sums.map(c => ` · ${esc(c.label)} <b>${esc(fmtCell(c, items.reduce((t, [r]) => t + (+val(r, c.key) || 0), 0)))}</b>`).join('')}</button></td></tr>`;
            if (!shut) items.forEach(([r, i]) => { html += rowHtml(r, i); });
          });
        } else page.forEach((r, i) => { html += rowHtml(r, i); });
        q('[data-g-body]').innerHTML = html;
        if (cfg.quickAdd.length) {
          const qa = cfg.quickAdd.map(k => cols.find(c => c.key === k) || {key: k, label: k});
          q('[data-g-body]').insertAdjacentHTML('afterbegin', `<tr class="dl-grid__quick"><td class="dl-grid__chk"><i class="fa-solid fa-plus"></i></td>${hasExp ? '<td></td>' : ''}<td colspan="${span - 1 - (hasExp ? 1 : 0)}"><div class="dl-grid__qa">${qa.map(c => `<input type="text" data-g-qa="${esc(c.key)}" placeholder="${esc(c.label)}${['number', 'money'].includes(c.type) ? ' (angka)' : ''}" aria-label="${esc(c.label)}">`).join('')}<button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-g-qa-add>Tambah</button><small>Enter = tambah</small></div></td></tr>`);
        }
        const cardsOn = cfg.cards && st.view === 'cards';
        q('[data-g-cards]').hidden = !cardsOn; q('.dl-grid__wrap').hidden = !!cardsOn;
        if (cardsOn) { const C = cfg.cards; q('[data-g-cards]').innerHTML = page.map(r => `<article class="dl-grid__card" data-id="${esc(r[cfg.rowKey])}"><div class="dl-grid__cardimg">${r[C.image] ? `<img src="${esc(r[C.image])}" alt="" loading="lazy">` : '<i class="fa-regular fa-image"></i>'}${C.badge && r[C.badge] ? `<span class="dl-badge dl-badge--pill dl-badge--${esc((C.tones || {})[r[C.badge]] || 'neutral')}">${esc(r[C.badge])}</span>` : ''}</div><div class="dl-grid__cardbody"><b>${esc(r[C.title])}</b><small>${esc(r[C.sub] ?? '')}</small>${(C.meta || []).map(k => { const c = cols.find(x => x.key === k) || {key: k}; return `<span><em>${esc(c.label || k)}</em>${esc(fmtCell(c, r[k] ?? ''))}</span>`; }).join('')}</div></article>`).join('') || '<div class="dl-empty"><b class="dl-empty__title">Tidak ada data yang cocok</b></div>'; }
        q('[data-g-foot]').innerHTML = sums.length && page.length ? '<tr><td class="dl-grid__chk"></td>' + (hasExp ? '<td></td>' : '') + shown().map((c, i) => c.sum ? `<td class="num"><b>${esc(fmtCell(c, list.reduce((t, r) => t + (+val(r, c.key) || 0), 0)))}</b></td>` : `<td>${i === 0 ? '<b>Total (semua hasil)</b>' : ''}</td>`).join('') + (cfg.actions !== 'none' ? '<td></td>' : '') + '</tr>' : '';
        drawPager(total, pages);
        const all = q('[data-g-all]'); if (all) { const ids = page.map(r => String(r[cfg.rowKey])); all.checked = ids.length > 0 && ids.every(i => sel.has(i)); all.indeterminate = !all.checked && ids.some(i => sel.has(i)); }
      }
      function drawPager(total, pages) {
        const from = total ? (st.page - 1) * st.perPage + 1 : 0, to = Math.min(total, st.page * st.perPage), start = Math.max(1, Math.min(st.page - 2, pages - 4));
        let nums = ''; for (let p = start; p <= Math.min(pages, start + 4); p++) nums += `<button type="button" class="${p === st.page ? 'is-active' : ''}" data-p="${p}">${p}</button>`;
        q('[data-g-pager]').innerHTML = `<div class="dl-pager"><div class="dl-pager__left">Go to <input type="number" min="1" max="${pages}" value="${st.page}" class="dl-pager__goto" data-g-goto> View ${[10, 25, 50, 100].map(n => `<button type="button" class="dl-pager__size ${n === st.perPage ? 'is-active' : ''}" data-pp="${n}">${n}</button>`).join('')}</div>`
          + `<div class="dl-pager__nav"><button type="button" data-p="1" ${st.page <= 1 ? 'disabled' : ''} title="Halaman pertama">⏮</button><button type="button" data-p="${st.page - 1}" ${st.page <= 1 ? 'disabled' : ''} title="Sebelumnya">◀</button>${nums}<button type="button" data-p="${st.page + 1}" ${st.page >= pages ? 'disabled' : ''} title="Berikutnya">▶</button><button type="button" data-p="${pages}" ${st.page >= pages ? 'disabled' : ''} title="Halaman terakhir">⏭</button></div>`
          + `<div class="dl-pager__info">[${fmt.number(from)} – ${fmt.number(to)} dari ${fmt.number(total)}]</div></div>`;
      }

      // ---- toolbar: filter, kolom, grup, kerapatan, chip ----
      function drawTools() {
        const ff = q('[data-g-filter-fields]');
        if (ff) ff.innerHTML = Object.entries(cfg.filters).map(([k, label]) => { const opts = [...new Set(rows.map(r => String(val(r, k))))].sort(); return `<label class="dl-pop2__field"><span>${esc(label)}</span><select data-g-filter="${esc(k)}"><option value="">Semua</option>${opts.map(o => `<option ${st.filters[k] === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></label>`; }).join('');
        const n = Object.values(st.filters).filter(v => v !== '').length, badge = q('[data-g-filter-count]');
        if (badge) { badge.hidden = !n; badge.textContent = n; }
        q('[data-g-col-list]').innerHTML = cols.map(c => `<label class="dl-choice"><input type="checkbox" data-g-col="${esc(c.key)}" ${st.hidden.includes(c.key) ? '' : 'checked'}> ${esc(c.label)}</label>`).join('');
        const gl = q('[data-g-group-list]');
        if (gl) gl.innerHTML = [['', 'Tanpa grup'], ...Object.entries(cfg.groups)].map(([k, l]) => `<label class="dl-choice"><input type="radio" name="g-${esc(cfg.id)}" value="${esc(k)}" data-g-group ${st.group === k ? 'checked' : ''}> ${esc(l)}</label>`).join('');
        qq('[data-g-density] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === st.density));
        qq('[data-g-lines] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b.dataset.value === st.lines));
        const ln = q('[data-g-lines-n]'); ln.value = st.linesN; ln.disabled = st.lines === 'auto';
        root.dataset.density = st.density; root.style.setProperty('--dl-grid-lines', st.lines === 'auto' ? 'unset' : st.linesN); root.classList.toggle('is-clamped', st.lines !== 'auto');
        const chips = [
          ...(st.stat !== null ? [['stat', 'Kotak: ' + cfg.stats[st.stat].label]] : []),
          ...Object.entries(st.filters).filter(([, v]) => v !== '').map(([k, v]) => ['f:' + k, `${cfg.filters[k]}: ${v}`]),
          ...(st.search ? [['search', 'Cari: “' + st.search + '”']] : []),
        ];
        const cbox = q('[data-g-chips]'); cbox.hidden = !chips.length;
        cbox.innerHTML = chips.map(([k, l]) => `<span class="dl-tags__chip">${esc(l)}<button type="button" data-g-chip="${esc(k)}" aria-label="Lepas">&times;</button></span>`).join('') + (chips.length > 1 ? '<button type="button" class="dl-btn dl-btn--link" data-g-chip="all">Lepas semua</button>' : '');
      }
      function drawBulk() {
        const b = q('[data-g-bulk]'); b.hidden = !sel.size; q('[data-g-bulk-count]').textContent = fmt.number(sel.size);
        const all = q('[data-g-select-all]'); const n = filtered().length; all.textContent = sel.size < n ? `Pilih semua ${fmt.number(n)} hasil` : 'Semua hasil terpilih'; all.disabled = sel.size >= n;
      }
      const redraw = () => { drawStats(); drawTabs(); drawTools(); drawHead(); drawBody(); drawBulk(); persist(); };

      // ---- interaksi ----
      const search = q('[data-g-search]'); let t;
      search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { st.search = search.value; st.page = 1; redraw(); }, 250); });
      root.addEventListener('click', e => {
        const x = e.target;
        const s = x.closest('.dl-grid__stats [data-i]'); if (s) { st.stat = st.stat === +s.dataset.i ? null : +s.dataset.i; st.page = 1; return redraw(); }
        const tb = x.closest('[data-g-tabs] [data-i]'); if (tb) { st.tab = +tb.dataset.i; st.page = 1; return redraw(); }
        const th = x.closest('th.is-sortable'); if (th) { const k = th.dataset.key; if (st.sort === k) { if (st.dir > 0) st.dir = -1; else { st.sort = null; st.dir = 1; } } else { st.sort = k; st.dir = 1; } return redraw(); }
        const pg = x.closest('[data-p]'); if (pg && !pg.disabled) { st.page = +pg.dataset.p; return drawBody(); }
        const pp = x.closest('[data-pp]'); if (pp) { st.perPage = +pp.dataset.pp; st.page = 1; return redraw(); }
        const tg = x.closest('[data-g-toggle]'); if (tg) { e.stopPropagation(); const pan = q(`[data-g-panel="${tg.dataset.gToggle}"]`), was = pan.classList.contains('is-open'); qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); pan.classList.toggle('is-open', !was); return; }
        const d = x.closest('[data-g-density] .dl-segment__opt'); if (d) { st.density = d.dataset.value; return redraw(); }
        const l = x.closest('[data-g-lines] .dl-segment__opt'); if (l) { st.lines = l.dataset.value; return redraw(); }
        if (x.closest('[data-g-density-reset]')) { st.density = 'normal'; st.lines = 'fixed'; st.linesN = 1; return redraw(); }
        if (x.closest('[data-g-filter-reset]')) { st.filters = {}; st.page = 1; return redraw(); }
        const ch = x.closest('[data-g-chip]'); if (ch) { const k = ch.dataset.gChip; if (k === 'all') { st.filters = {}; st.stat = null; st.search = ''; search.value = ''; } else if (k === 'stat') st.stat = null; else if (k === 'search') { st.search = ''; search.value = ''; } else st.filters[k.slice(2)] = ''; st.page = 1; return redraw(); }
        const gr = x.closest('[data-g-grp]'); if (gr) { const g = gr.dataset.gGrp; st.collapsed = st.collapsed.includes(g) ? st.collapsed.filter(v => v !== g) : [...st.collapsed, g]; return drawBody(); }
        const vw = x.closest('[data-g-view] .dl-segment__opt'); if (vw) { st.view = vw.dataset.value; qq('[data-g-view] .dl-segment__opt').forEach(b => b.classList.toggle('is-active', b === vw)); return drawBody(); }
        const im = x.closest('[data-g-img]'); if (im) { const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-grid__lightbox'; ov.innerHTML = `<img src="${esc(im.dataset.gImg)}" alt=""><button type="button" class="dl-modal__x">&times;</button>`; ov.addEventListener('click', () => ov.remove()); document.body.appendChild(ov); return; }
        const ga = x.closest('[data-g-action]'); if (ga) { const id = ga.closest('tr').dataset.id; if (emit('dl:grid-action', {id, action: ga.dataset.gAction})) toast(`${ga.textContent}: ${rows.find(v => String(v[cfg.rowKey]) === id)?.name || id} (pratinjau)`); return; }
        if (x.closest('[data-g-qa-add]')) return quickAdd();
        const ex = x.closest('[data-g-exp]'); if (ex) { const id = ex.closest('tr').dataset.id; st.open = st.open.includes(id) ? st.open.filter(v => v !== id) : [...st.open, id]; return drawBody(); }
        if (x.closest('[data-g-select-all]')) { filtered().forEach(r => sel.add(String(r[cfg.rowKey]))); drawBody(); return drawBulk(); }
        if (x.closest('[data-g-select-none]')) { sel.clear(); drawBody(); return drawBulk(); }
        const ba = x.closest('[data-g-bulk-action]'); if (ba) { toast(`${ba.textContent.trim()}: ${fmt.number(sel.size)} baris (pratinjau)`, 'success'); root.dispatchEvent(new CustomEvent('dl:grid-bulk', {bubbles: true, detail: {action: ba.dataset.gBulkAction, ids: [...sel]}})); return; }
        const act = x.closest('[data-g-act]'); if (act) { const r = rows.find(v => String(v[cfg.rowKey]) === act.dataset.id); if (act.dataset.gAct === 'toggle' && cfg.activeKey) { r[cfg.activeKey] = !r[cfg.activeKey]; toast((r[cfg.activeKey] ? 'Diaktifkan: ' : 'Dinonaktifkan: ') + (r.name || act.dataset.id), 'success'); redraw(); } else if (emit('dl:grid-open', {id: act.dataset.id, action: act.dataset.gAct})) toast(`${act.title.split(' (')[0]}: ${r.name || r[cfg.rowKey]} (pratinjau)`); return; }
        if (x.closest('[data-g-new]')) { if (emit('dl:grid-new', {})) toast('+ New Record (pratinjau)'); return; }
        if (x.closest('[data-g-export]')) return exportCsv();
        const cb = x.matches('.dl-grid__row input[type=checkbox]') ? x : null;
        if (cb) {
          const tr = cb.closest('tr'), idx = +tr.dataset.idx;
          if (e.shiftKey && lastIdx !== null) { const [a, b] = [Math.min(lastIdx, idx), Math.max(lastIdx, idx)]; current.slice(a, b + 1).forEach(r => cb.checked ? sel.add(String(r[cfg.rowKey])) : sel.delete(String(r[cfg.rowKey]))); }
          else cb.checked ? sel.add(tr.dataset.id) : sel.delete(tr.dataset.id);
          lastIdx = idx; drawBody(); drawBulk(); return;
        }
        if (x.matches('[data-g-all]')) { current.forEach(r => x.checked ? sel.add(String(r[cfg.rowKey])) : sel.delete(String(r[cfg.rowKey]))); drawBody(); drawBulk(); }
      });
      function quickAdd() {
        const inputs = qq('[data-g-qa]'), first = inputs[0];
        if (!first || !first.value.trim()) { first && first.focus(); toast(`${cols.find(c => c.key === cfg.quickAdd[0])?.label || 'Isian pertama'} wajib diisi.`, 'warn'); return; }
        const r = {[cfg.rowKey]: 'n' + Date.now()}; if (cfg.activeKey) r[cfg.activeKey] = true;
        inputs.forEach(i => { const c = cols.find(x => x.key === i.dataset.gQa) || {}; r[i.dataset.gQa] = ['number', 'money'].includes(c.type) ? +i.value.replace(/\./g, '').replace(',', '.') || 0 : i.value.trim(); });
        rows.unshift(r); redraw(); emit('dl:grid-add', {row: r}); toast('Baris ditambahkan.', 'success'); const f = q('[data-g-qa]'); if (f) f.focus();
      }
      root.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-g-qa]')) { e.preventDefault(); quickAdd(); } });
      root.addEventListener('change', e => {
        const tc = e.target.closest('[data-g-cell]'); if (tc) { const id = tc.closest('tr').dataset.id, r = rows.find(v => String(v[cfg.rowKey]) === id); r[tc.dataset.gCell] = tc.checked; emit('dl:grid-cell', {id, key: tc.dataset.gCell, value: tc.checked}); return; }
        const f = e.target.closest('[data-g-filter]'); if (f) { st.filters[f.dataset.gFilter] = f.value; st.page = 1; return redraw(); }
        const c = e.target.closest('[data-g-col]'); if (c) { const k = c.dataset.gCol; st.hidden = c.checked ? st.hidden.filter(v => v !== k) : [...st.hidden, k]; if (!shown().length) { st.hidden = st.hidden.filter(v => v !== k); } return redraw(); }
        const g = e.target.closest('[data-g-group]'); if (g) { st.group = g.value; st.collapsed = []; return redraw(); }
        if (e.target.matches('[data-g-lines-n]')) { st.linesN = Math.max(1, Math.min(5, +e.target.value || 1)); return redraw(); }
        if (e.target.matches('[data-g-goto]')) { st.page = Math.max(1, +e.target.value || 1); drawBody(); }
      });
      root.addEventListener('dblclick', e => { const tr = e.target.closest('.dl-grid__row, .dl-grid__card'); if (!tr || e.target.closest('button,input,label')) return; const r = rows.find(v => String(v[cfg.rowKey]) === tr.dataset.id); if (emit('dl:grid-open', {id: tr.dataset.id, action: 'open'})) toast('Buka: ' + (r.name || tr.dataset.id) + ' (klik 2x)'); });
      document.addEventListener('click', e => { if (!e.target.closest('[data-g-pop]')) qq('.dl-pop2__panel').forEach(p => p.classList.remove('is-open')); });

      // Export = kolom yang sedang tampil, semua baris hasil saringan (bukan hanya halaman ini).
      function exportCsv() {
        const vis = shown(), list = sorted(filtered()), cellTxt = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
        const csv = [vis.map(c => cellTxt(c.label)).join(';'), ...list.map(r => vis.map(c => cellTxt(c.type === 'identity' && c.code ? `${val(r, c.key)} (${val(r, c.code)})` : val(r, c.key))).join(';'))].join('\r\n');
        const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'})); a.download = cfg.id + '.csv'; a.click();
        toast(`Export ${fmt.number(list.length)} baris × ${vis.length} kolom (kolom yang tampil).`, 'success');
      }

      root.dlGrid = {rows, cfg, redraw, setRows: list => { rows.splice(0, rows.length, ...list); st.page = 1; sel.clear(); redraw(); }};
      redraw();
    }
  }
  Devlib.define("Grid", Grid);
})(window.Devlib);
