/* DevLib · Kanban */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, T, Tf, visible, fmt, at, emit} = Devlib.h;
  /* ============================ KANBAN ============================ */
  class Kanban extends Devlib.Component {
    static selector = "[data-dl-kanban]";
    init() {
      this.cols = JSON.parse(this.el.dataset.columns || '[]');
      this.cards = JSON.parse(this.el.dataset.cards || '[]');
      this.rules = JSON.parse(this.el.dataset.transitions || '{}');
      this.me = this.el.dataset.me;
      this.members0 = JSON.parse(this.el.dataset.members || '[]');
      this.board = $(this.el, '[data-k-board]');
      this.canAddCol = this.el.dataset.addColumn === '1';
      this.canAddCard = this.el.dataset.addCard === '1';
      this.q = '';
      this.prio = '';
      this.drag = null;
      this.seq = Date.now();

      // Drag & drop: posisi sisip = kartu pertama yang titik tengahnya di bawah kursor.
      this.el.addEventListener('dragstart', e => { const c = e.target.closest('[data-k-card]'); if (!c) return; this.drag = c.dataset.kCard; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', this.drag); } catch (x) {} });
      this.el.addEventListener('dragend', () => { this.drag = null; $$(this.el, '.is-drag,.is-target').forEach(x => x.classList.remove('is-drag', 'is-target')); $$(this.el, '.dl-kcard__ph').forEach(x => x.remove()); });
      this.el.addEventListener('dragover', e => {
        const body = e.target.closest('[data-k-drop]'); if (!body || this.drag == null) return;
        e.preventDefault();
        $$(this.el, '.is-target').forEach(x => x.classList.remove('is-target')); body.closest('.dl-kcol').classList.add('is-target');
        const after = $$(body, '[data-k-card]:not(.is-drag)').find(c => { const r = c.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; });
        let ph = $(this.el, '.dl-kcard__ph'); if (!ph) { ph = document.createElement('div'); ph.className = 'dl-kcard__ph'; }
        if (after) body.insertBefore(ph, after); else body.appendChild(ph);
      });
      this.el.addEventListener('drop', e => {
        const body = e.target.closest('[data-k-drop]'); if (!body || this.drag == null) return;
        e.preventDefault();
        const card = this.cardById(this.drag), ph = $(body, '.dl-kcard__ph');
        const index = ph ? $$(body, '[data-k-card]:not(.is-drag), .dl-kcard__ph').indexOf(ph) : null;
        this.move(card, body.closest('[data-k-col]').dataset.kCol, index);
      });

      this.el.addEventListener('click', e => {
        const x = e.target;
        const mv = x.closest('[data-k-move]');
        if (mv) {
          e.stopPropagation();
          const card = this.cardById(mv.closest('[data-k-card]').dataset.kCard);
          $$(document, '.dl-kanban__menu').forEach(m => m.remove());
          const m = document.createElement('div'); m.className = 'dl-kanban__menu';
          m.innerHTML = `<div class="dl-kanban__menutitle">${esc(T('k_move_to'))}</div>` + this.cols.map(c => `<button type="button" data-k-to="${esc(c.key)}" ${c.key === card.col || !this.allowed(card.col, c.key) ? 'disabled' : ''}>${esc(c.label)}</button>`).join('');
          const r = mv.getBoundingClientRect(); m.style.cssText = `left:${Math.min(r.left + scrollX, innerWidth - 200)}px;top:${r.bottom + scrollY + 4}px`;
          m.addEventListener('click', ev => { const b = ev.target.closest('[data-k-to]'); if (b) { this.move(card, b.dataset.kTo); m.remove(); } });
          document.body.appendChild(m); setTimeout(() => document.addEventListener('click', () => m.remove(), {once: true}));
          return;
        }
        const add = x.closest('[data-k-add]'); if (add) { add.hidden = true; const cp = add.nextElementSibling; cp.hidden = false; $(cp, 'textarea').focus(); return; }
        if (x.closest('[data-k-new-cancel]')) { const f = x.closest('.dl-kcol__foot'); $(f, '.dl-kcol__composer').hidden = true; $(f, '[data-k-add]').hidden = false; return; }
        if (x.closest('[data-k-new-save]')) {
          const f = x.closest('.dl-kcol__foot'), t = $(f, '[data-k-new-title]').value.trim(), d = $(f, '[data-k-new-due]').value;
          if (!t) { $(f, '[data-k-new-title]').focus(); return; }
          const card = {id: 'n' + (++this.seq), col: x.closest('[data-k-col]').dataset.kCol, title: t, due: d ? d.replace('T', ' ') : '', assignee: this.me, tags: [], checklist: [], comments: [], activity: [{by: this.me, at: `${fmt.date()} ${fmt.time()}`, text: T('k_act_created')}]};
          this.cards.push(card); this.render(); this._emit('create', card, null, card.col);
          const cf = $(this.el, `[data-k-col="${card.col}"] [data-k-add]`); cf.click(); return;
        }
        if (x.closest('[data-k-addcol]')) { const key = 'c' + (++this.seq); this.cols.push({key, label: T('k_new_col'), tone: 'neutral'}); this.render(); const t = $(this.el, `[data-k-col="${key}"] [data-k-rename]`); t.focus(); document.getSelection().selectAllChildren(t); this._emit('column', null, null, key); }
      });
      this.el.addEventListener('dblclick', e => { const c = e.target.closest('[data-k-card]'); if (c && !e.target.closest('[data-k-move]')) this.openCard(this.cardById(c.dataset.kCard)); });
      this.el.addEventListener('keydown', e => {
        if (e.target.matches('[data-k-rename]') && e.key === 'Enter') { e.preventDefault(); e.target.blur(); }
        if (e.target.matches('[data-k-new-title]') && e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.target.closest('.dl-kcol__foot').querySelector('[data-k-new-save]').click(); }
        if (e.target.matches('[data-k-card]') && e.key === 'Enter') this.openCard(this.cardById(e.target.dataset.kCard));
      });
      this.el.addEventListener('focusout', e => { if (e.target.matches('[data-k-rename]')) { const c = this.colOf(e.target.closest('[data-k-col]').dataset.kCol), v = e.target.textContent.trim(); if (v && v !== c.label) { c.label = v; this._emit('column', null, null, c.key); } else e.target.textContent = c.label; } });
      $(this.el, '[data-k-search]').addEventListener('input', e => { this.q = e.target.value.trim().toLowerCase(); this.render(); });
      $$(this.el, '[data-k-prio] .dl-segment__opt').forEach(b => b.addEventListener('click', () => { this.prio = b.dataset.value; $$(this.el, '[data-k-prio] .dl-segment__opt').forEach(x => x.classList.toggle('is-active', x === b)); this.render(); }));

      this.TONES = ['accent', 'success', 'warn', 'violet', 'info', 'danger'];
      this.el.dlKanban = {cards: this.cards, cols: this.cols, render: this.render, move: (id, to, i) => this.move(this.cardById(id), to, i)};
      this.render();
    }

    ini(n) {
      return String(n || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    due(c) {
      return c.due ? new Date(c.due.replace(' ', 'T') + ':00+07:00') : null;
    }

    dueTxt(c) { const d = this.due(c); return d ? `${fmt.date(d)}, ${fmt.time(d).replace(' WIB', '')}` : ''; }

    allowed(from, to) {
      return from === to || !this.rules[from] || this.rules[from].includes(to);
    }

    _emit(type, card, from, to) {
      return this.el.dispatchEvent(new CustomEvent('dl:kanban', {bubbles: true, detail: {type, card, from, to}}));
    }

    colOf(k) {
      return this.cols.find(c => c.key === k);
    }

    visible(c) {
      return (!this.prio || c.priority === this.prio) && (!this.q || [c.title, c.desc, ...(c.tags || []).map(t => t.label || t)].join(' ').toLowerCase().includes(this.q));
    }

    cardHtml(c) {
      const ck = c.checklist || [], done = ck.filter(i => i.done).length, d = this.due(c), late = d && d < new Date() && !this.colOf(c.col)?.done;
      return `<div class="dl-kcard" draggable="true" data-k-card="${esc(c.id)}" tabindex="0" title="${esc(T('k_dbl_open'))}">
        ${(c.tags || []).length ? `<div class="dl-kcard__tags">${c.tags.map(t => `<span class="dl-badge dl-badge--${esc(t.tone || 'accent')}">${esc(t.label || t)}</span>`).join('')}</div>` : ''}
        <div class="dl-kcard__title">${esc(c.title)}</div>
        ${c.desc ? `<div class="dl-kcard__desc">${esc(c.desc)}</div>` : ''}
        <div class="dl-kcard__foot">
          ${c.priority ? `<span class="dl-kcard__prio is-${esc(c.priority.toLowerCase())}">${esc(c.priority)}</span>` : ''}
          ${d ? `<span class="dl-kcard__due ${late ? 'is-late' : ''}"><i class="fa-regular fa-clock"></i> ${esc(this.dueTxt(c))}</span>` : ''}
          ${ck.length ? `<span class="dl-kcard__ck ${done === ck.length ? 'is-done' : ''}"><i class="fa-regular fa-square-check"></i> ${done}/${ck.length}</span>` : ''}
          ${(c.comments || []).length ? `<span class="dl-kcard__ck"><i class="fa-regular fa-comment"></i> ${c.comments.length}</span>` : ''}
          <span class="dl-kcard__spacer"></span>
          ${(c.members || (c.assignee ? [c.assignee] : [])).map(m => `<span class="dl-kcard__av" title="${esc(m)}">${esc(this.ini(m))}</span>`).join('')}
          <button type="button" class="dl-kcard__menu" data-k-move title="${esc(T('k_move_tip'))}"><i class="fa-solid fa-ellipsis"></i></button>
        </div></div>`;
    }

    render() {
      this.board.innerHTML = this.cols.map(col => {
        const list = this.cards.filter(c => c.col === col.key), shown = list.filter(this.visible), over = col.limit && list.length > col.limit;
        return `<section class="dl-kcol dl-kcol--${esc(col.tone || 'accent')} ${over ? 'is-over' : ''}" data-k-col="${esc(col.key)}">
          <header class="dl-kcol__head"><span class="dl-kcol__title" contenteditable="true" spellcheck="false" data-k-rename title="${esc(T('k_rename'))}">${esc(col.label)}</span>
            <span class="dl-kcol__count" title="${col.limit ? esc(Tf('k_wip_limit', {n: col.limit})) : ''}">${list.length}${col.limit ? ' / ' + col.limit : ''}</span></header>
          <div class="dl-kcol__body" data-k-drop>${shown.map(this.cardHtml).join('') || '<div class="dl-kcol__empty">' + esc(T('k_no_cards')) + '</div>'}</div>
          ${this.canAddCard ? `<footer class="dl-kcol__foot"><button type="button" class="dl-kcol__add" data-k-add><i class="fa-solid fa-plus"></i> ${esc(T('k_add_card'))}</button>
            <div class="dl-kcol__composer" hidden><textarea rows="2" placeholder="${esc(T('k_card_title_ph'))}" data-k-new-title></textarea><input type="datetime-local" data-k-new-due title="${esc(T('k_due'))}">
            <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-k-new-save>Save</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-k-new-cancel>Cancel</button></div></div></footer>` : ''}
        </section>`;
      }).join('') + (this.canAddCol ? '<button type="button" class="dl-kanban__addcol" data-k-addcol><i class="fa-solid fa-plus"></i> ' + esc(T('k_add_col')) + '</button>' : '');
    }

    move(card, to, index) {
      const from = card.col;
      if (!this.allowed(from, to)) { toast(Tf('k_move_denied', {from: this.colOf(from).label, to: this.colOf(to).label}), 'danger'); this.render(); return false; }
      const tgt = this.colOf(to);
      if (from !== to && tgt.limit && this.cards.filter(c => c.col === to).length >= tgt.limit) toast(Tf('k_wip_over', {col: tgt.label, n: tgt.limit}), 'warn');
      this.cards.splice(this.cards.indexOf(card), 1);
      card.col = to;
      const inCol = this.cards.filter(c => c.col === to);
      const before = index == null || index >= inCol.length ? null : inCol[index];
      this.cards.splice(before ? this.cards.indexOf(before) : this.cards.length, 0, card);
      if (from !== to) (card.activity = card.activity || []).unshift({by: this.me, at: `${fmt.date()} ${fmt.time()}`, text: Tf('k_act_moved', {from: this.colOf(from).label, to: tgt.label})});
      this.render(); this._emit('move', card, from, to);
      return true;
    }

    cardById(id) {
      return this.cards.find(c => String(c.id) === String(id));
    }

    toneOf(l) {
      return this.TONES[[...l].reduce((a, ch) => a + ch.charCodeAt(0), 0) % this.TONES.length];
    }

    allLabels() {
      return [...new Map(this.cards.flatMap(c => (c.tags || []).map(t => typeof t === 'string' ? {label: t, tone: this.toneOf(t)} : t)).map(t => [t.label, t])).values()];
    }

    log(card, text) {
      return (card.activity = card.activity || []).unshift({by: this.me, at: `${fmt.date()} ${fmt.time()}`, text});
    }

    openCard(card) {
      const ov = document.createElement('div'); ov.className = 'dl-mov is-on dl-kmodal';
      let pop = null, editDesc = false, addItem = false, labelQ = '';
      const members = [...new Set([this.me, ...this.members0, ...this.cards.flatMap(c => c.members || (c.assignee ? [c.assignee] : []))])];
      card.members = card.members || (card.assignee ? [card.assignee] : []);
      const popHtml = {
        labels: () => `<input class="dl-kmodal__in" placeholder="${esc(T('k_label_search'))}" data-m-labelq value="${esc(labelQ)}">
          <div class="dl-kpop__list">${this.allLabels().filter(t => !labelQ || t.label.toLowerCase().includes(labelQ.toLowerCase())).map(t => `<label class="dl-kpop__row"><input type="checkbox" data-m-label="${esc(t.label)}" ${(card.tags || []).some(x => (x.label || x) === t.label) ? 'checked' : ''}><span class="dl-badge dl-badge--${esc(t.tone)}">${esc(t.label)}</span></label>`).join('')}</div>
          ${labelQ && !this.allLabels().some(t => t.label.toLowerCase() === labelQ.toLowerCase()) ? `<button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-m-labelnew>+ ${esc(Tf('k_label_new', {label: labelQ}))}</button>` : ''}`,
        dates: () => `<span class="dl-kpop__lbl">${esc(T('k_due_label'))}</span><input type="datetime-local" class="dl-kmodal__in" data-m-due value="${esc((card.due || '').replace(' ', 'T'))}">
          <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-duesave>Save</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-dueclear>${esc(T('k_clear_due'))}</button></div>`,
        checklist: () => `<span class="dl-kpop__lbl">${esc(T('k_checklist_title'))}</span><input class="dl-kmodal__in" data-m-cktitle value="Checklist">
          <button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-ckcreate>${esc(T('k_add'))}</button>`,
        members: () => `<div class="dl-kpop__list">${members.map(m => `<label class="dl-kpop__row"><input type="checkbox" data-m-member="${esc(m)}" ${card.members.includes(m) ? 'checked' : ''}><span class="dl-kcard__av">${esc(this.ini(m))}</span>${esc(m)}</label>`).join('')}</div>`,
        priority: () => `<div class="dl-kpop__list">${['High', 'Medium', 'Low', ''].map(p => `<button type="button" class="dl-kpop__opt ${p === (card.priority || '') ? 'is-active' : ''}" data-m-prio="${p}">${p ? `<span class="dl-kcard__prio is-${p.toLowerCase()}">${p}</span>` : esc(T('k_no_priority'))}</button>`).join('')}</div>`,
        move: () => `<div class="dl-kpop__list">${this.cols.map(c => `<button type="button" class="dl-kpop__opt ${c.key === card.col ? 'is-active' : ''}" data-m-to="${esc(c.key)}" ${c.key === card.col || !this.allowed(card.col, c.key) ? 'disabled' : ''}>${esc(c.label)}</button>`).join('')}</div>`,
      };
      const TITLES = {labels: T('k_labels'), dates: T('k_dates'), checklist: 'Checklist', members: T('k_members'), priority: T('k_priority'), move: T('k_move_to')};
      const actionBtn = (k, icon) => `<div class="dl-kmodal__ag"><button type="button" class="dl-kmodal__abtn ${pop === k ? 'is-on' : ''}" data-m-pop="${k}"><i class="fa-solid ${icon}"></i> ${TITLES[k]}</button>${pop === k ? `<div class="dl-kpop"><div class="dl-kpop__head">${TITLES[k]}<button type="button" data-m-popclose>&times;</button></div>${popHtml[k]()}</div>` : ''}</div>`;
      const draw = () => {
        const ck = card.checklist || [], done = ck.filter(i => i.done).length, pct = ck.length ? Math.round(done / ck.length * 100) : 0, d = this.due(card);
        const hasCk = card.checklistTitle != null || ck.length;
        ov.innerHTML = `<div class="dl-modal dl-kmodal__box" role="dialog" aria-modal="true">
          <div class="dl-kmodal__head"><i class="fa-regular fa-square-check dl-kmodal__hicon"></i><div class="dl-kmodal__titlewrap"><input class="dl-kmodal__title" value="${esc(card.title)}" data-m-title aria-label="${esc(T('k_card_title'))}"><small>${esc(T('k_in_column'))} <b>${esc(this.colOf(card.col).label)}</b></small></div><button type="button" class="dl-modal__x" data-m-close aria-label="${esc(T('close'))}">&times;</button></div>
          <div class="dl-kmodal__body">
            <div class="dl-kmodal__main">
              <div class="dl-kmodal__actions">${actionBtn('labels', 'fa-tag')}${actionBtn('dates', 'fa-clock')}${actionBtn('checklist', 'fa-square-check')}${actionBtn('members', 'fa-user')}${actionBtn('priority', 'fa-flag')}${actionBtn('move', 'fa-arrow-right-arrow-left')}</div>
              ${(card.tags || []).length || card.members.length || d || card.priority ? `<div class="dl-kmodal__meta">
                ${card.members.length ? `<div><span>${esc(T('k_members'))}</span><div class="dl-row">${card.members.map(m => `<span class="dl-kcard__av" title="${esc(m)}">${esc(this.ini(m))}</span>`).join('')}</div></div>` : ''}
                ${(card.tags || []).length ? `<div><span>${esc(T('k_labels'))}</span><div class="dl-row">${card.tags.map(t => `<span class="dl-badge dl-badge--${esc(t.tone || 'accent')}">${esc(t.label || t)}</span>`).join('')}</div></div>` : ''}
                ${d ? `<div><span>${esc(T('k_due'))}</span><b class="${d < new Date() ? 'dl-kcard__due is-late' : ''}">${esc(this.dueTxt(card))}</b></div>` : ''}
                ${card.priority ? `<div><span>${esc(T('k_priority'))}</span><span class="dl-kcard__prio is-${esc(card.priority.toLowerCase())}">${esc(card.priority)}</span></div>` : ''}
              </div>` : ''}
              <p class="dl-kmodal__cap"><i class="fa-solid fa-align-left"></i> ${esc(T('k_description'))}</p>
              ${editDesc ? `<textarea rows="5" class="dl-kmodal__desc" data-m-desc placeholder="${esc(T('k_desc_hint'))}">${esc(card.desc || '')}</textarea>
                <div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-descsave>Save</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-desccancel>Cancel</button></div>`
                : `<div class="dl-kmodal__descview ${card.desc ? '' : 'is-empty'}" data-m-descedit tabindex="0">${esc(card.desc || T('k_desc_hint'))}</div>`}
              ${hasCk ? `<div class="dl-kmodal__ckhead"><p class="dl-kmodal__cap"><i class="fa-regular fa-square-check"></i> ${esc(card.checklistTitle || 'Checklist')}</p><button type="button" class="dl-btn dl-btn--danger-soft dl-btn--sm" data-m-ckdelall>Delete</button></div>
                <div class="dl-kmodal__pct"><span>${pct}%</span><div class="dl-progress__track"><div class="dl-progress__bar ${pct === 100 ? 'is-done' : ''}" style="width:${pct}%"></div></div></div>
                <ul class="dl-kmodal__ck">${ck.map((i, k) => `<li><label class="dl-choice"><input type="checkbox" data-m-ck="${k}" ${i.done ? 'checked' : ''}> <span class="${i.done ? 'is-done' : ''}">${esc(i.text)}</span></label><button type="button" data-m-ckdel="${k}" title="${esc(T('k_del_item'))}"><i class="fa-solid fa-xmark"></i></button></li>`).join('')}</ul>
                ${addItem ? `<textarea rows="2" class="dl-kmodal__in" placeholder="${esc(T('k_add_item'))}" data-m-cknew></textarea><div class="dl-row"><button type="button" class="dl-btn dl-btn--primary dl-btn--sm" data-m-ckadd>${esc(T('k_add'))}</button><button type="button" class="dl-btn dl-btn--ghost dl-btn--sm" data-m-cknewcancel>&times;</button></div>`
                  : '<button type="button" class="dl-kmodal__additem" data-m-ckopen>' + esc(T('k_add_item')) + '</button>'}` : ''}
            </div>
            <aside class="dl-kmodal__side">
              <p class="dl-kmodal__cap"><i class="fa-regular fa-comments"></i> ${esc(T('k_comments'))}</p>
              <div class="dl-kmodal__cmt"><span class="dl-kcard__av">${esc(this.ini(this.me))}</span><textarea rows="1" class="dl-kmodal__in" placeholder="${esc(T('k_write_comment'))}" data-m-cmt></textarea></div>
              <ul class="dl-kmodal__act">${[...(card.comments || []).map(c => ({...c, kind: 'c'})), ...(card.activity || [])].map(a => `<li><span class="dl-kcard__av">${esc(this.ini(a.by))}</span><div><b>${esc(a.by)}</b> ${a.kind === 'c' ? '' : esc(a.text)}${a.kind === 'c' ? `<q>${esc(a.text)}</q>` : ''}<small>${esc(a.at)}</small></div></li>`).join('') || '<li class="dl-kcol__empty">' + esc(T('k_no_activity')) + '</li>'}</ul>
            </aside>
          </div>
          <div class="dl-modal__foot"><button type="button" class="dl-btn dl-btn--danger dl-btn--md" data-m-del><i class="fa-solid fa-trash-can"></i> ${esc(T('k_del_card'))}</button><span class="dl-kcard__spacer"></span><button type="button" class="dl-btn dl-btn--primary dl-btn--md" data-m-close>${esc(T('close'))}</button></div>
        </div>`;
      };
      const keepTitle = () => { const t = $(ov, '[data-m-title]'); if (t && t.value.trim()) card.title = t.value.trim(); };
      const redraw = (focusSel) => { keepTitle(); draw(); if (focusSel) { const f = $(ov, focusSel); if (f) { f.focus(); if (f.setSelectionRange && f.value) f.setSelectionRange(f.value.length, f.value.length); } } };
      const close = () => { keepTitle(); ov.remove(); this.render(); this._emit('update', card, card.col, card.col); };
      draw();
      ov.addEventListener('click', e => {
        const x = e.target;
        if (x === ov || x.closest('[data-m-close]')) return close();
        const pb = x.closest('[data-m-pop]'); if (pb) { pop = pop === pb.dataset.mPop ? null : pb.dataset.mPop; labelQ = ''; return redraw(pop === 'labels' ? '[data-m-labelq]' : null); }
        if (x.closest('[data-m-popclose]') || (pop && !x.closest('.dl-kpop'))) { pop = null; redraw(); if (!x.closest('[data-m-descedit],[data-m-ckopen]')) return; }
        if (x.closest('[data-m-labelnew]')) { (card.tags = card.tags || []).push({label: labelQ, tone: this.toneOf(labelQ)}); this.log(card, Tf('k_act_label', {label: labelQ})); labelQ = ''; return redraw('[data-m-labelq]'); }
        if (x.closest('[data-m-duesave]')) { card.due = $(ov, '[data-m-due]').value.replace('T', ' '); this.log(card, card.due ? Tf('k_act_due', {due: this.dueTxt(card)}) : T('k_act_due_clear')); pop = null; return redraw(); }
        if (x.closest('[data-m-dueclear]')) { card.due = ''; this.log(card, T('k_act_due_clear')); pop = null; return redraw(); }
        if (x.closest('[data-m-ckcreate]')) { card.checklistTitle = $(ov, '[data-m-cktitle]').value.trim() || 'Checklist'; card.checklist = card.checklist || []; pop = null; addItem = true; this.log(card, Tf('k_act_checklist', {title: card.checklistTitle})); return redraw('[data-m-cknew]'); }
        const pr = x.closest('[data-m-prio]'); if (pr) { card.priority = pr.dataset.mPrio; pop = null; return redraw(); }
        const to = x.closest('[data-m-to]'); if (to) { pop = null; keepTitle(); if (this.move(card, to.dataset.mTo)) draw(); return; }
        if (x.closest('[data-m-descedit]')) { editDesc = true; return redraw('[data-m-desc]'); }
        if (x.closest('[data-m-descsave]')) { card.desc = $(ov, '[data-m-desc]').value.trim(); editDesc = false; return redraw(); }
        if (x.closest('[data-m-desccancel]')) { editDesc = false; return redraw(); }
        if (x.closest('[data-m-ckopen]')) { addItem = true; return redraw('[data-m-cknew]'); }
        if (x.closest('[data-m-cknewcancel]')) { addItem = false; return redraw(); }
        if (x.closest('[data-m-ckadd]')) { const i = $(ov, '[data-m-cknew]'); if (i.value.trim()) { (card.checklist = card.checklist || []).push({text: i.value.trim(), done: false}); } return redraw('[data-m-cknew]'); }
        const del = x.closest('[data-m-ckdel]'); if (del) { card.checklist.splice(+del.dataset.mCkdel, 1); return redraw(); }
        if (x.closest('[data-m-ckdelall]')) return confirmBox({message: Tf('k_ask_del_checklist', {title: card.checklistTitle || 'Checklist'}), tone: 'danger'}).then(ok => { if (ok) { card.checklist = []; card.checklistTitle = null; addItem = false; redraw(); } });
        if (x.closest('[data-m-del]')) confirmBox({message: Tf('k_ask_del_card', {title: card.title}), tone: 'danger'}).then(ok => { if (!ok) return; this.cards.splice(this.cards.indexOf(card), 1); ov.remove(); this.render(); this._emit('delete', card, card.col, null); });
      });
      ov.addEventListener('input', e => { if (e.target.matches('[data-m-labelq]')) { labelQ = e.target.value; redraw('[data-m-labelq]'); } });
      ov.addEventListener('change', e => {
        const t = e.target;
        if (t.matches('[data-m-ck]')) { card.checklist[+t.dataset.mCk].done = t.checked; redraw(); }
        if (t.matches('[data-m-label]')) { const l = t.dataset.mLabel; card.tags = card.tags || []; if (t.checked) card.tags.push(this.allLabels().find(x => x.label === l)); else card.tags = card.tags.filter(x => (x.label || x) !== l); redraw(); }
        if (t.matches('[data-m-member]')) { const m = t.dataset.mMember; card.members = t.checked ? [...card.members, m] : card.members.filter(x => x !== m); card.assignee = card.members[0] || ''; this.log(card, Tf(t.checked ? 'k_act_member_add' : 'k_act_member_del', {name: m})); redraw(); }
      });
      ov.addEventListener('keydown', e => {
        const t = e.target;
        if (e.key === 'Escape') { if (pop) { pop = null; redraw(); } else close(); return; }
        if (e.key !== 'Enter' || e.shiftKey) return;
        if (t.matches('[data-m-title]')) { e.preventDefault(); t.blur(); }
        else if (t.matches('[data-m-cknew]')) { e.preventDefault(); $(ov, '[data-m-ckadd]').click(); }
        else if (t.matches('[data-m-cmt]') && t.value.trim()) { e.preventDefault(); (card.comments = card.comments || []).unshift({by: this.me, at: `${fmt.date()} ${fmt.time()}`, text: t.value.trim()}); redraw('[data-m-cmt]'); }
        else if (t.matches('[data-m-labelq]') && $(ov, '[data-m-labelnew]')) { e.preventDefault(); $(ov, '[data-m-labelnew]').click(); }
        else if (t.matches('[data-m-descedit]')) { editDesc = true; redraw('[data-m-desc]'); }
      });
      document.body.appendChild(ov); $(ov, '[data-m-title]').focus();
    }
  }
  Devlib.define("Kanban", Kanban);
})(window.Devlib);
