/* DevLib · Kanban */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, T, visible, fmt, at, emit} = Devlib.h;
  /* ============================ KANBAN ============================ */
  class Kanban extends Devlib.Component {
    static selector = "[data-dl-kanban]";
    init() {
      const el = this.el;
      const cols = JSON.parse(el.dataset.columns || '[]'), cards = JSON.parse(el.dataset.cards || '[]'), rules = JSON.parse(el.dataset.transitions || '{}'), me = el.dataset.me;
      const members0 = JSON.parse(el.dataset.members || '[]');
      const board = $(el, '[data-k-board]'), canAddCol = el.dataset.addColumn === '1', canAddCard = el.dataset.addCard === '1';
      let q = '', prio = '', drag = null, seq = Date.now();
      const ini = n => String(n || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const due = c => c.due ? new Date(c.due.replace(' ', 'T') + ':00+07:00') : null;
      const dueTxt = c => { const d = due(c); return d ? `${fmt.date(d)}, ${fmt.time(d).replace(' WIB', '')}` : ''; };
      const allowed = (from, to) => from === to || !rules[from] || rules[from].includes(to);
      const emit = (type, card, from, to) => el.dispatchEvent(new CustomEvent('dl:kanban', {bubbles: true, detail: {type, card, from, to}}));
      const colOf = k => cols.find(c => c.key === k);
      const visible = c => (!prio || c.priority === prio) && (!q || [c.title, c.desc, ...(c.tags || []).map(t => t.label || t)].join(' ').toLowerCase().includes(q));

      function cardHtml(c) {
        const ck = c.checklist || [], done = ck.filter(i => i.done).length, d = due(c), late = d && d < new Date() && !colOf(c.col)?.done;
        return `<div class="dl-kcard" draggable="true" data-k-card="${esc(c.id)}" tabindex="0" title="Klik 2x untuk membuka detail">
          ${(c.tags || []).length ? `<div class="dl-kcard__tags">${c.tags.map(t => `<span class="dl-badge dl-badge--${esc(t.tone || 'accent')}">${esc(t.label || t)}</span>`).join('')}</div>` : ''}
          <div class="dl-kcard__title">${esc(c.title)}</div>
          ${c.desc ? `<div class="dl-kcard__desc">${esc(c.desc)}</div>` : ''}
          <div class="dl-kcard__foot">
            ${c.priority ? `<span class="dl-kcard__prio is-${esc(c.priority.toLowerCase())}">${esc(c.priority)}</span>` : ''}
            ${d ? `<span class="dl-kcard__due ${late ? 'is-late' : ''}"><i class="fa-regular fa-clock"></i> ${esc(dueTxt(c))}</span>` : ''}
            ${ck.length ? `<span class="dl-kcard__ck ${done === ck.length ? 'is-done' : ''}"><i class="fa-regular fa-square-check"></i> ${done}/${ck.length}</span>` : ''}
            ${(c.comments || []).length ? `<span class="dl-kcard__ck"><i class="fa-regular fa-comment"></i> ${c.comments.length}</span>` : ''}
            <span class="dl-kcard__spacer"></span>
            ${(c.members || (c.assignee ? [c.assignee] : [])).map(m => `<span class="dl-kcard__av" title="${esc(m)}">${esc(ini(m))}</span>`).join('')}
            <button type="button" class="dl-kcard__menu" data-k-move title="Pindah ke... (tanpa drag)"><i class="fa-solid fa-ellipsis"></i></button>
          </div></div>`;
      }
      function render() {
        board.innerHTML = cols.map(col => {
          const list = cards.filter(c => c.col === col.key), shown = list.filter(visible), over = col.limit && list.length > col.limit;
          return `<section class="dl-kcol dl-kcol--${esc(col.tone || 'accent')} ${over ? 'is-over' : ''}" data-k-col="${esc(col.key)}">
            <header class="dl-kcol__head"><span class="dl-kcol__title" contenteditable="true" spellcheck="false" data-k-rename title="Klik untuk ganti nama">${esc(col.label)}</span>
              <span class="dl-kcol__count" title="${col.limit ? 'Batas WIP ' + col.limit : ''}">${list.length}${col.limit ? ' / ' + col.limit : ''}</span></header>
            <div class="dl-kcol__body" data-k-drop>${shown.map(cardHtml).join('') || '<div class="dl-kcol__empty">Tidak ada kartu</div>'}</div>
            ${canAddCard ? `<footer class="dl-kcol__foot"><button type="button" class="dl-kcol__add" data-k-add><i class="fa-solid fa-plus"></i> Tambah kartu</button>
              <div class="dl-kcol__composer" hidden><textarea rows="2" placeholder="Judul kartu..." data-k-new-title></textarea><input type="datetime-local" data-k-new-due title="Tenggat">
              <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-k-new-save>Simpan</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-k-new-cancel>Batal</button></div></div></footer>` : ''}
          </section>`;
        }).join('') + (canAddCol ? '<button type="button" class="dl-kanban__addcol" data-k-addcol><i class="fa-solid fa-plus"></i> Tambah kolom</button>' : '');
      }
      function move(card, to, index) {
        const from = card.col;
        if (!allowed(from, to)) { toast(`Tidak boleh pindah dari "${colOf(from).label}" ke "${colOf(to).label}".`, 'danger'); render(); return false; }
        const tgt = colOf(to);
        if (from !== to && tgt.limit && cards.filter(c => c.col === to).length >= tgt.limit) toast(`Kolom "${tgt.label}" melewati batas WIP (${tgt.limit}).`, 'warn');
        cards.splice(cards.indexOf(card), 1);
        card.col = to;
        const inCol = cards.filter(c => c.col === to);
        const before = index == null || index >= inCol.length ? null : inCol[index];
        cards.splice(before ? cards.indexOf(before) : cards.length, 0, card);
        if (from !== to) (card.activity = card.activity || []).unshift({by: me, at: `${fmt.date()} ${fmt.time()}`, text: `memindahkan dari ${colOf(from).label} ke ${tgt.label}`});
        render(); emit('move', card, from, to);
        return true;
      }
      const cardById = id => cards.find(c => String(c.id) === String(id));

      // Drag & drop: posisi sisip = kartu pertama yang titik tengahnya di bawah kursor.
      el.addEventListener('dragstart', e => { const c = e.target.closest('[data-k-card]'); if (!c) return; drag = c.dataset.kCard; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', drag); } catch (x) {} });
      el.addEventListener('dragend', () => { drag = null; $$(el, '.is-drag,.is-target').forEach(x => x.classList.remove('is-drag', 'is-target')); $$(el, '.dl-kcard__ph').forEach(x => x.remove()); });
      el.addEventListener('dragover', e => {
        const body = e.target.closest('[data-k-drop]'); if (!body || drag == null) return;
        e.preventDefault();
        $$(el, '.is-target').forEach(x => x.classList.remove('is-target')); body.closest('.dl-kcol').classList.add('is-target');
        const after = $$(body, '[data-k-card]:not(.is-drag)').find(c => { const r = c.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; });
        let ph = $(el, '.dl-kcard__ph'); if (!ph) { ph = document.createElement('div'); ph.className = 'dl-kcard__ph'; }
        if (after) body.insertBefore(ph, after); else body.appendChild(ph);
      });
      el.addEventListener('drop', e => {
        const body = e.target.closest('[data-k-drop]'); if (!body || drag == null) return;
        e.preventDefault();
        const card = cardById(drag), ph = $(body, '.dl-kcard__ph');
        const index = ph ? $$(body, '[data-k-card]:not(.is-drag), .dl-kcard__ph').indexOf(ph) : null;
        move(card, body.closest('[data-k-col]').dataset.kCol, index);
      });

      el.addEventListener('click', e => {
        const x = e.target;
        const mv = x.closest('[data-k-move]');
        if (mv) {
          e.stopPropagation();
          const card = cardById(mv.closest('[data-k-card]').dataset.kCard);
          $$(document, '.dl-kanban__menu').forEach(m => m.remove());
          const m = document.createElement('div'); m.className = 'dl-kanban__menu';
          m.innerHTML = `<div class="dl-kanban__menutitle">Pindah ke</div>` + cols.map(c => `<button type="button" data-k-to="${esc(c.key)}" ${c.key === card.col || !allowed(card.col, c.key) ? 'disabled' : ''}>${esc(c.label)}</button>`).join('');
          const r = mv.getBoundingClientRect(); m.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 200)}px;top:${r.bottom + scrollY + 4}px`;
          m.addEventListener('click', ev => { const b = ev.target.closest('[data-k-to]'); if (b) { move(card, b.dataset.kTo); m.remove(); } });
          document.body.appendChild(m); setTimeout(() => document.addEventListener('click', () => m.remove(), {once: true}));
          return;
        }
        const add = x.closest('[data-k-add]'); if (add) { add.hidden = true; const cp = add.nextElementSibling; cp.hidden = false; $(cp, 'textarea').focus(); return; }
        if (x.closest('[data-k-new-cancel]')) { const f = x.closest('.dl-kcol__foot'); $(f, '.dl-kcol__composer').hidden = true; $(f, '[data-k-add]').hidden = false; return; }
        if (x.closest('[data-k-new-save]')) {
          const f = x.closest('.dl-kcol__foot'), t = $(f, '[data-k-new-title]').value.trim(), d = $(f, '[data-k-new-due]').value;
          if (!t) { $(f, '[data-k-new-title]').focus(); return; }
          const card = {id: 'n' + (++seq), col: x.closest('[data-k-col]').dataset.kCol, title: t, due: d ? d.replace('T', ' ') : '', assignee: me, tags: [], checklist: [], comments: [], activity: [{by: me, at: `${fmt.date()} ${fmt.time()}`, text: 'membuat kartu ini'}]};
          cards.push(card); render(); emit('create', card, null, card.col);
          const cf = $(el, `[data-k-col="${card.col}"] [data-k-add]`); cf.click(); return;
        }
        if (x.closest('[data-k-addcol]')) { const key = 'c' + (++seq); cols.push({key, label: 'Kolom baru', tone: 'neutral'}); render(); const t = $(el, `[data-k-col="${key}"] [data-k-rename]`); t.focus(); document.getSelection().selectAllChildren(t); emit('column', null, null, key); }
      });
      el.addEventListener('dblclick', e => { const c = e.target.closest('[data-k-card]'); if (c && !e.target.closest('[data-k-move]')) openCard(cardById(c.dataset.kCard)); });
      el.addEventListener('keydown', e => {
        if (e.target.matches('[data-k-rename]') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); }
        if (e.target.matches('[data-k-new-title]') && e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.target.closest('.dl-kcol__foot').querySelector('[data-k-new-save]').click(); }
        if (e.target.matches('[data-k-card]') && e.key === 'Enter') openCard(cardById(e.target.dataset.kCard));
      });
      el.addEventListener('focusout', e => { if (e.target.matches('[data-k-rename]')) { const c = colOf(e.target.closest('[data-k-col]').dataset.kCol), v = e.target.textContent.trim(); if (v && v !== c.label) { c.label = v; emit('column', null, null, c.key); } else e.target.textContent = c.label; } });
      $(el, '[data-k-search]').addEventListener('input', e => { q = e.target.value.trim().toLowerCase(); render(); });
      $$(el, '[data-k-prio] .dl-segment__opt').forEach(b => b.addEventListener('click', () => { prio = b.dataset.value; $$(el, '[data-k-prio] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === b)); render(); }));

      // Detail kartu (popup) -- tata letak mockup_UIUX_kanban: bilah aksi + popover, deskripsi klik-untuk-edit,
      // checklist bernama, komentar & aktivitas di kanan.
      const TONES = ['accent', 'success', 'warn', 'violet', 'info', 'danger'];
      const toneOf = l => TONES[[...l].reduce((a, ch) => a + ch.charCodeAt(0), 0) % TONES.length];
      const allLabels = () => [...new Map(cards.flatMap(c => (c.tags || []).map(t => typeof t === 'string' ? {label: t, tone: toneOf(t)} : t)).map(t => [t.label, t])).values()];
      const log = (card, text) => (card.activity = card.activity || []).unshift({by: me, at: `${fmt.date()} ${fmt.time()}`, text});
      function openCard(card) {
        const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-kmodal';
        let pop = null, editDesc = false, addItem = false, labelQ = '';
        const members = [...new Set([me, ...members0, ...cards.flatMap(c => c.members || (c.assignee ? [c.assignee] : []))])];
        card.members = card.members || (card.assignee ? [card.assignee] : []);
        const popHtml = {
          labels: () => `<input class="dl-kmodal__in" placeholder="Cari / buat label..." data-m-labelq value="${esc(labelQ)}">
            <div class="dl-kpop__list">${allLabels().filter(t => !labelQ || t.label.toLowerCase().includes(labelQ.toLowerCase())).map(t => `<label class="dl-kpop__row"><input type="checkbox" data-m-label="${esc(t.label)}" ${(card.tags || []).some(x => (x.label || x) === t.label) ? 'checked' : ''}><span class="dl-badge dl-badge--${esc(t.tone)}">${esc(t.label)}</span></label>`).join('')}</div>
            ${labelQ && !allLabels().some(t => t.label.toLowerCase() === labelQ.toLowerCase()) ? `<button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-m-labelnew>+ Buat label "${esc(labelQ)}"</button>` : ''}`,
          dates: () => `<span class="dl-kpop__lbl">Tenggat (tanggal & jam, WIB)</span><input type="datetime-local" class="dl-kmodal__in" data-m-due value="${esc((card.due || '').replace(' ', 'T'))}">
            <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-duesave>Simpan</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-dueclear>Hapus tenggat</button></div>`,
          checklist: () => `<span class="dl-kpop__lbl">Judul checklist</span><input class="dl-kmodal__in" data-m-cktitle value="Checklist">
            <button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-ckcreate>Tambah</button>`,
          members: () => `<div class="dl-kpop__list">${members.map(m => `<label class="dl-kpop__row"><input type="checkbox" data-m-member="${esc(m)}" ${card.members.includes(m) ? 'checked' : ''}><span class="dl-kcard__av">${esc(ini(m))}</span>${esc(m)}</label>`).join('')}</div>`,
          priority: () => `<div class="dl-kpop__list">${['High', 'Medium', 'Low', ''].map(p => `<button type="button" class="dl-kpop__opt ${p === (card.priority || '') ? 'is-active' : ''}" data-m-prio="${p}">${p ? `<span class="dl-kcard__prio is-${p.toLowerCase()}">${p}</span>` : 'Tanpa prioritas'}</button>`).join('')}</div>`,
          move: () => `<div class="dl-kpop__list">${cols.map(c => `<button type="button" class="dl-kpop__opt ${c.key === card.col ? 'is-active' : ''}" data-m-to="${esc(c.key)}" ${c.key === card.col || !allowed(card.col, c.key) ? 'disabled' : ''}>${esc(c.label)}</button>`).join('')}</div>`,
        };
        const TITLES = {labels: 'Labels', dates: 'Dates', checklist: 'Checklist', members: 'Members', priority: 'Prioritas', move: 'Pindah ke'};
        const actionBtn = (k, icon) => `<div class="dl-kmodal__ag"><button type="button" class="dl-kmodal__abtn ${pop === k ? 'is-on' : ''}" data-m-pop="${k}"><i class="fa-solid ${icon}"></i> ${TITLES[k]}</button>${pop === k ? `<div class="dl-kpop"><div class="dl-kpop__head">${TITLES[k]}<button type="button" data-m-popclose>&times;</button></div>${popHtml[k]()}</div>` : ''}</div>`;
        const draw = () => {
          const ck = card.checklist || [], done = ck.filter(i => i.done).length, pct = ck.length ? Math.round(done / ck.length * 100) : 0, d = due(card);
          const hasCk = card.checklistTitle != null || ck.length;
          ov.innerHTML = `<div class="dl-modal dl-kmodal__box" role="dialog" aria-modal="true">
            <div class="dl-kmodal__head"><i class="fa-regular fa-square-check dl-kmodal__hicon"></i><div class="dl-kmodal__titlewrap"><input class="dl-kmodal__title" value="${esc(card.title)}" data-m-title aria-label="Judul kartu"><small>di kolom <b>${esc(colOf(card.col).label)}</b></small></div><button type="button" class="dl-modal__x" data-m-close aria-label="Tutup">&times;</button></div>
            <div class="dl-kmodal__body">
              <div class="dl-kmodal__main">
                <div class="dl-kmodal__actions">${actionBtn('labels', 'fa-tag')}${actionBtn('dates', 'fa-clock')}${actionBtn('checklist', 'fa-square-check')}${actionBtn('members', 'fa-user')}${actionBtn('priority', 'fa-flag')}${actionBtn('move', 'fa-arrow-right-arrow-left')}</div>
                ${(card.tags || []).length || card.members.length || d || card.priority ? `<div class="dl-kmodal__meta">
                  ${card.members.length ? `<div><span>Members</span><div class="dl-row">${card.members.map(m => `<span class="dl-kcard__av" title="${esc(m)}">${esc(ini(m))}</span>`).join('')}</div></div>` : ''}
                  ${(card.tags || []).length ? `<div><span>Labels</span><div class="dl-row">${card.tags.map(t => `<span class="dl-badge dl-badge--${esc(t.tone || 'accent')}">${esc(t.label || t)}</span>`).join('')}</div></div>` : ''}
                  ${d ? `<div><span>Tenggat</span><b class="${d < new Date() ? 'dl-kcard__due is-late' : ''}">${esc(dueTxt(card))}</b></div>` : ''}
                  ${card.priority ? `<div><span>Prioritas</span><span class="dl-kcard__prio is-${esc(card.priority.toLowerCase())}">${esc(card.priority)}</span></div>` : ''}
                </div>` : ''}
                <p class="dl-kmodal__cap"><i class="fa-solid fa-align-left"></i> Deskripsi</p>
                ${editDesc ? `<textarea rows="5" class="dl-kmodal__desc" data-m-desc placeholder="Tambahkan deskripsi yang lebih rinci...">${esc(card.desc || '')}</textarea>
                  <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-descsave>Simpan</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-desccancel>Batal</button></div>`
                  : `<div class="dl-kmodal__descview ${card.desc ? '' : 'is-empty'}" data-m-descedit tabindex="0">${esc(card.desc || 'Tambahkan deskripsi yang lebih rinci...')}</div>`}
                ${hasCk ? `<div class="dl-kmodal__ckhead"><p class="dl-kmodal__cap"><i class="fa-regular fa-square-check"></i> ${esc(card.checklistTitle || 'Checklist')}</p><button type="button" class="dl-btn dl-btn--danger-soft dl-btn--sm" data-m-ckdelall>Delete</button></div>
                  <div class="dl-kmodal__pct"><span>${pct}%</span><div class="dl-progress__track"><div class="dl-progress__bar ${pct === 100 ? 'is-done' : ''}" style="width:${pct}%"></div></div></div>
                  <ul class="dl-kmodal__ck">${ck.map((i, k) => `<li><label class="dl-choice"><input type="checkbox" data-m-ck="${k}" ${i.done ? 'checked' : ''}> <span class="${i.done ? 'is-done' : ''}">${esc(i.text)}</span></label><button type="button" data-m-ckdel="${k}" title="Hapus item"><i class="fa-solid fa-xmark"></i></button></li>`).join('')}</ul>
                  ${addItem ? `<textarea rows="2" class="dl-kmodal__in" placeholder="Tambah item" data-m-cknew></textarea><div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-ckadd>Tambah</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-cknewcancel>&times;</button></div>`
                    : '<button type="button" class="dl-kmodal__additem" data-m-ckopen>Tambah item</button>'}` : ''}
              </div>
              <aside class="dl-kmodal__side">
                <p class="dl-kmodal__cap"><i class="fa-regular fa-comments"></i> Komentar & Aktivitas</p>
                <div class="dl-kmodal__cmt"><span class="dl-kcard__av">${esc(ini(me))}</span><textarea rows="1" class="dl-kmodal__in" placeholder="Tulis komentar..." data-m-cmt></textarea></div>
                <ul class="dl-kmodal__act">${[...(card.comments || []).map(c => ({...c, kind: 'c'})), ...(card.activity || [])].map(a => `<li><span class="dl-kcard__av">${esc(ini(a.by))}</span><div><b>${esc(a.by)}</b> ${a.kind === 'c' ? '' : esc(a.text)}${a.kind === 'c' ? `<q>${esc(a.text)}</q>` : ''}<small>${esc(a.at)}</small></div></li>`).join('') || '<li class="dl-kcol__empty">Belum ada aktivitas</li>'}</ul>
              </aside>
            </div>
            <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--danger dl-btn--md" data-m-del><i class="fa-solid fa-trash-can"></i> Hapus kartu</button><span class="dl-kcard__spacer"></span><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-m-close>Tutup</button></div>
          </div>`;
        };
        const keepTitle = () => { const t = $(ov, '[data-m-title]'); if (t && t.value.trim()) card.title = t.value.trim(); };
        const redraw = (focusSel) => { keepTitle(); draw(); if (focusSel) { const f = $(ov, focusSel); if (f) { f.focus(); if (f.setSelectionRange && f.value) f.setSelectionRange(f.value.length, f.value.length); } } };
        const close = () => { keepTitle(); ov.remove(); render(); emit('update', card, card.col, card.col); };
        draw();
        ov.addEventListener('click', e => {
          const x = e.target;
          if (x === ov || x.closest('[data-m-close]')) return close();
          const pb = x.closest('[data-m-pop]'); if (pb) { pop = pop === pb.dataset.mPop ? null : pb.dataset.mPop; labelQ = ''; return redraw(pop === 'labels' ? '[data-m-labelq]' : null); }
          if (x.closest('[data-m-popclose]') || (pop && !x.closest('.dl-kpop'))) { pop = null; redraw(); if (!x.closest('[data-m-descedit],[data-m-ckopen]')) return; }
          if (x.closest('[data-m-labelnew]')) { (card.tags = card.tags || []).push({label: labelQ, tone: toneOf(labelQ)}); log(card, `menambah label ${labelQ}`); labelQ = ''; return redraw('[data-m-labelq]'); }
          if (x.closest('[data-m-duesave]')) { card.due = $(ov, '[data-m-due]').value.replace('T', ' '); log(card, card.due ? `mengatur tenggat ${dueTxt(card)}` : 'menghapus tenggat'); pop = null; return redraw(); }
          if (x.closest('[data-m-dueclear]')) { card.due = ''; log(card, 'menghapus tenggat'); pop = null; return redraw(); }
          if (x.closest('[data-m-ckcreate]')) { card.checklistTitle = $(ov, '[data-m-cktitle]').value.trim() || 'Checklist'; card.checklist = card.checklist || []; pop = null; addItem = true; log(card, `menambah checklist ${card.checklistTitle}`); return redraw('[data-m-cknew]'); }
          const pr = x.closest('[data-m-prio]'); if (pr) { card.priority = pr.dataset.mPrio; pop = null; return redraw(); }
          const to = x.closest('[data-m-to]'); if (to) { pop = null; keepTitle(); if (move(card, to.dataset.mTo)) draw(); return; }
          if (x.closest('[data-m-descedit]')) { editDesc = true; return redraw('[data-m-desc]'); }
          if (x.closest('[data-m-descsave]')) { card.desc = $(ov, '[data-m-desc]').value.trim(); editDesc = false; return redraw(); }
          if (x.closest('[data-m-desccancel]')) { editDesc = false; return redraw(); }
          if (x.closest('[data-m-ckopen]')) { addItem = true; return redraw('[data-m-cknew]'); }
          if (x.closest('[data-m-cknewcancel]')) { addItem = false; return redraw(); }
          if (x.closest('[data-m-ckadd]')) { const i = $(ov, '[data-m-cknew]'); if (i.value.trim()) { (card.checklist = card.checklist || []).push({text: i.value.trim(), done: false}); } return redraw('[data-m-cknew]'); }
          const del = x.closest('[data-m-ckdel]'); if (del) { card.checklist.splice(+del.dataset.mCkdel, 1); return redraw(); }
          if (x.closest('[data-m-ckdelall]')) return confirmBox({message: `Hapus checklist "${card.checklistTitle || 'Checklist'}" beserta semua itemnya?`, tone: 'danger'}).then(ok => { if (ok) { card.checklist = []; card.checklistTitle = null; addItem = false; redraw(); } });
          if (x.closest('[data-m-del]')) confirmBox({message: `Hapus kartu "${card.title}"?`, tone: 'danger'}).then(ok => { if (!ok) return; cards.splice(cards.indexOf(card), 1); ov.remove(); render(); emit('delete', card, card.col, null); });
        });
        ov.addEventListener('input', e => { if (e.target.matches('[data-m-labelq]')) { labelQ = e.target.value; redraw('[data-m-labelq]'); } });
        ov.addEventListener('change', e => {
          const t = e.target;
          if (t.matches('[data-m-ck]')) { card.checklist[+t.dataset.mCk].done = t.checked; redraw(); }
          if (t.matches('[data-m-label]')) { const l = t.dataset.mLabel; card.tags = card.tags || []; if (t.checked) card.tags.push(allLabels().find(x => x.label === l)); else card.tags = card.tags.filter(x => (x.label || x) !== l); redraw(); }
          if (t.matches('[data-m-member]')) { const m = t.dataset.mMember; card.members = t.checked ? [...card.members, m] : card.members.filter(x => x !== m); card.assignee = card.members[0] || ''; log(card, `${t.checked ? 'menambah' : 'melepas'} anggota ${m}`); redraw(); }
        });
        ov.addEventListener('keydown', e => {
          const t = e.target;
          if (e.key === 'Escape') { if (pop) { pop = null; redraw(); } else close(); return; }
          if (e.key !== 'Enter' || e.shiftKey) return;
          if (t.matches('[data-m-title]')) { e.preventDefault(); t.blur(); }
          else if (t.matches('[data-m-cknew]')) { e.preventDefault(); $(ov, '[data-m-ckadd]').click(); }
          else if (t.matches('[data-m-cmt]') && t.value.trim()) { e.preventDefault(); (card.comments = card.comments || []).unshift({by: me, at: `${fmt.date()} ${fmt.time()}`, text: t.value.trim()}); redraw('[data-m-cmt]'); }
          else if (t.matches('[data-m-labelq]') && $(ov, '[data-m-labelnew]')) { e.preventDefault(); $(ov, '[data-m-labelnew]').click(); }
          else if (t.matches('[data-m-descedit]')) { editDesc = true; redraw('[data-m-desc]'); }
        });
        document.body.appendChild(ov); $(ov, '[data-m-title]').focus();
      }
      el.dlKanban = {cards, cols, render, move: (id, to, i) => move(cardById(id), to, i)};
      render();
    }
  }
  Devlib.define("Kanban", Kanban);
})(window.Devlib);
