/*
 * REMARK
 * Tujuan       : Peta bantuan interaktif: pohon menu, filter (modul / jenis / belum ada panduan / cari), pilihan satu layar,
 *                detail panduan, panah atas/bawah, Ctrl+Enter, Esc bertahap; pilihan dan filter diingat selama sesi browser.
 * Sumber hulu  : data-map dari x-dl::help-map (PHP).
 * Input        : data-map JSON; pesan {dl:'ss-search'} dari kotak cari Side Screen.
 * Output       : pesan {dl:'open-item'} ke Shell induk, tab Tickets terisi, tautan panduan di clipboard.
 * Dipakai oleh : x-dl::help-map.
 * Status       : Aktif (DevLib).
 */
/* DevLib · HelpMap */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast} = Devlib.h;
  const KEY = 'dl-helpmap';
  const TYPES = ['input', 'report', 'guide'];

  class HelpMap extends Devlib.Component {
    static selector = '[data-dl-helpmap]';

    init() {
      const d = JSON.parse(this.el.dataset.map || '{}');
      this.L = d.l || {}; this.topics = d.topics || {}; this.cur = d.current || null; this.copyUrl = d.copyUrl || ''; this.reqTab = d.requestTab || 'tickets';
      this.leaves = []; this.branches = new Map();
      (d.tree || []).forEach(m => this.walk(m, [], m.id, null));
      this.q = $(this.el, '[data-hm-q]'); this.treeEl = $(this.el, '[data-hm-tree]'); this.detail = $(this.el, '[data-hm-detail]');
      this.st = {q: '', mod: '', type: 'all', missing: false, sel: null, open: []};
      try { Object.assign(this.st, JSON.parse(sessionStorage.getItem(KEY) || '{}')); } catch (e) {}
      // F1 from a screen: its guide is selected (overrides the remembered selection).
      if (this.cur && this.leaf(this.cur)) { this.st.sel = this.cur; this.st.q = ''; this.st.mod = ''; this.st.type = 'all'; this.st.missing = false; }
      this.q.value = this.st.q;
      this.bind();
      this.refresh();
    }

    // Tree node -> flat leaves with their path and the module they belong to.
    walk(n, path, mod, parent) {
      const p = [...path, n.label];
      if (n.s) {
        const t = n.s.topic ? this.topics[n.s.topic] : null;
        const l = {id: n.s.slug || ('t:' + n.s.topic), label: n.label, path: p, mod, s: n.s, topic: t, parent,
          hay: [n.label, p.join(' '), t?.purpose, t?.keywords, t?.title].filter(Boolean).join(' ').toLowerCase()};
        this.leaves.push(l);
        for (let b = parent; b; b = b.parent) { b.leaves.push(l); }
        return;
      }
      const b = {id: n.id, label: n.label, icon: n.icon, mod, parent, kids: [], leaves: []};
      this.branches.set(b.id, b);
      parent?.kids.push(b);
      (n.kids || []).forEach(k => this.walk(k, p, mod, b));
    }

    leaf(id) { return this.leaves.find(l => l.id === id); }

    pass(l) {
      const s = this.st;
      if (s.mod && l.mod !== s.mod) return false;
      if (s.type !== 'all' && l.topic?.type.toLowerCase() !== s.type) return false;
      if (s.missing && l.topic) return false;
      if (s.q && !s.q.toLowerCase().split(/\s+/).every(w => l.hay.includes(w))) return false;
      return true;
    }

    active() { return !!(this.st.q || this.st.mod || this.st.type !== 'all' || this.st.missing); }

    save() { try { sessionStorage.setItem(KEY, JSON.stringify(this.st)); } catch (e) {} }

    bind() {
      this.q.addEventListener('input', () => { this.st.q = this.q.value.trim(); this.refresh(); });
      this.el.addEventListener('click', e => {
        const t = e.target;
        let b;
        if ((b = t.closest('[data-hm-leaf]'))) { this.st.sel = b.dataset.hmLeaf; this.refresh(); }
        else if ((b = t.closest('[data-hm-branch]'))) { const i = this.st.open.indexOf(b.dataset.hmBranch); i < 0 ? this.st.open.push(b.dataset.hmBranch) : this.st.open.splice(i, 1); this.refresh(false); }
        else if ((b = t.closest('[data-hm-type]'))) { this.st.type = b.dataset.hmType; this.refresh(); }
        else if (t.closest('[data-hm-missing]')) { this.st.missing = !this.st.missing; this.refresh(); }
        else if (t.closest('[data-hm-reset]')) { this.st.q = ''; this.st.mod = ''; this.st.type = 'all'; this.st.missing = false; this.q.value = ''; this.refresh(); }
        else if ((b = t.closest('[data-hm-open]'))) { this.openScreen(this.leaf(b.dataset.hmOpen)); }
        else if ((b = t.closest('[data-hm-copy]'))) { this.copy(this.leaf(b.dataset.hmCopy)); }
        else if ((b = t.closest('[data-hm-request]'))) { this.request(this.leaf(b.dataset.hmRequest)); }
        else if ((b = t.closest('[data-hm-go]'))) { this.st.sel = b.dataset.hmGo; this.refresh(); }
        else if (t.closest('[data-hm-mine]') && this.cur && this.leaf(this.cur)) { this.st.sel = this.cur; this.refresh(); }
      });
      this.el.addEventListener('change', e => { if (e.target.matches('[data-hm-mod]')) { this.st.mod = e.target.value; this.refresh(); } });
      // Single search box: the Side Screen header search is forwarded here.
      window.addEventListener('message', e => { if (e.data && e.data.dl === 'ss-search') { this.q.value = e.data.q || ''; this.st.q = this.q.value.trim(); this.refresh(); } });
      // Esc clears the search first; the next Esc reaches the window (closes the Side Screen).
      window.addEventListener('keydown', e => {
        if (e.key === 'Escape' && this.shown() && this.q.value) { e.preventDefault(); e.stopPropagation(); this.q.value = ''; this.st.q = ''; this.refresh(); }
      }, true);
      document.addEventListener('keydown', e => {
        if (!this.shown()) return;
        const typing = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
        if (e.key === '/' && !typing && !e.ctrlKey && !e.metaKey) { e.preventDefault(); this.q.focus(); this.q.select(); }
        else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          if (e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
          const v = this.leaves.filter(l => this.pass(l)); if (!v.length) return;
          e.preventDefault();
          const i = v.findIndex(l => l.id === this.st.sel);
          this.st.sel = v[Math.max(0, Math.min(v.length - 1, i + (e.key === 'ArrowDown' ? 1 : -1)))].id;
          this.refresh(true);
        } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
          const l = this.leaf(this.st.sel); if (l?.s.slug) { e.preventDefault(); this.openScreen(l); }
        }
      });
    }

    // The Help tab may be hidden (other Side Screen section): keys act only when the map is on screen.
    shown() { return this.el.offsetParent !== null; }

    refresh(scroll = true) {
      const vis = this.leaves.filter(l => this.pass(l));
      if (!vis.some(l => l.id === this.st.sel)) this.st.sel = vis[0]?.id || null;
      this.save();
      this.drawFilters(vis);
      this.drawTree(vis);
      this.drawDetail();
      if (scroll) $(this.treeEl, '.is-sel')?.scrollIntoView({block: 'nearest'});
    }

    drawFilters(vis) {
      const L = this.L, s = this.st;
      const mods = [...this.branches.values()].filter(b => !b.parent);
      const cnt = t => this.leaves.filter(l => (!s.mod || l.mod === s.mod) && l.topic?.type.toLowerCase() === t).length;
      const chip = (k, label, n, on, attr) => `<button type="button" class="dl-hm__chip${on ? ' is-on' : ''}" ${attr} aria-pressed="${on}">${esc(label)}<b>${n}</b></button>`;
      const total = this.leaves.filter(l => !s.mod || l.mod === s.mod);
      const miss = total.filter(l => !l.topic).length;
      $(this.el, '[data-hm-filters]').innerHTML =
        `<label class="dl-hm__sel">${esc(L['Module'])}<select data-hm-mod><option value="">${esc(L['All modules'])}</option>${mods.map(m => `<option value="${esc(m.id)}"${s.mod === m.id ? ' selected' : ''}>${esc(m.label)}</option>`).join('')}</select></label>`
        + `<span class="dl-hm__chips" role="group" aria-label="${esc(L['Type'])}">`
        + chip('all', L['All'], total.length - miss, s.type === 'all', 'data-hm-type="all"')
        + TYPES.map(t => chip(t, L[t[0].toUpperCase() + t.slice(1)], cnt(t), s.type === t, `data-hm-type="${t}"`)).join('')
        + `</span>` + chip('missing', L['No guide yet'], miss, s.missing, 'data-hm-missing');
      const scr = this.leaves.filter(l => l.s.slug), withG = scr.filter(l => l.topic).length;
      $(this.el, '[data-hm-sum]').innerHTML =
        `<span><b>${vis.length}</b> ${esc(vis.length === 1 ? L['screen matches the filter'] : L['screens match the filter'])}</span>`
        + `<span class="dl-hm__cover">${esc(L[':with of :total screens have a guide, :without without a guide'].replace(':with', withG).replace(':total', scr.length).replace(':without', scr.length - withG))}</span>`
        + (this.active() ? `<button type="button" class="dl-hm__reset" data-hm-reset><i class="fa-solid fa-rotate-left"></i> ${esc(L['Reset filter'])}</button>` : '');
    }

    drawTree(vis) {
      const ids = new Set(vis.map(l => l.id)), s = this.st, filtering = this.active();
      const selBranches = new Set(); for (let b = this.leaf(s.sel)?.parent; b; b = b.parent) selBranches.add(b.id);
      const roots = [...this.branches.values()].filter(b => !b.parent);
      const leafHtml = l => `<button type="button" class="dl-hm__leaf${l.id === s.sel ? ' is-sel' : ''}${l.s.slug && l.s.slug === this.cur ? ' is-here' : ''}${l.topic ? '' : ' is-missing'}" data-hm-leaf="${esc(l.id)}" aria-current="${l.id === s.sel}">`
        + `<span class="dl-hm__dot" title="${esc(l.topic ? '' : this.L['No guide yet'])}"></span><span class="dl-hm__lbl">${esc(l.label)}</span>`
        + (l.s.slug && l.s.slug === this.cur ? `<em class="dl-hm__here">${esc(this.L['here'])}</em>` : '')
        + (l.topic ? `<span class="dl-hm__type is-${esc(l.topic.type.toLowerCase())}">${esc(l.topic.type.slice(0, 1))}</span>` : '') + '</button>';
      const draw = (b, depth) => {
        const own = b.leaves.filter(l => ids.has(l.id)); if (!own.length) return '';
        const open = filtering || s.open.includes(b.id) || selBranches.has(b.id) || (depth === 0 && !s.open.length && !s.sel);
        const missing = b.leaves.some(l => l.s.slug && !l.topic);
        const direct = b.leaves.filter(l => l.parent === b && ids.has(l.id));
        return `<div class="dl-hm__br depth-${depth}${open ? ' is-open' : ''}"><button type="button" class="dl-hm__bh" data-hm-branch="${esc(b.id)}" aria-expanded="${open}">`
          + `<i class="fa-solid fa-chevron-right"></i><span class="dl-hm__lbl">${esc(b.label)}</span>${missing ? `<span class="dl-hm__dot is-warn" title="${esc(this.L['No guide yet'])}"></span>` : ''}<b>${own.length}</b></button>`
          + (open ? `<div class="dl-hm__bb">${b.kids.map(k => draw(k, depth + 1)).join('')}${direct.map(leafHtml).join('')}</div>` : '') + '</div>';
      };
      const html = roots.map(b => draw(b, 0)).join('');
      this.treeEl.innerHTML = html || `<div class="dl-hm__none"><i class="fa-regular fa-folder-open"></i><b>${esc(this.L['No screen matches'])}</b><span>${esc(this.L['Change or reset the filters above.'])}</span>`
        + `<button type="button" class="dl-hm__reset" data-hm-reset><i class="fa-solid fa-rotate-left"></i> ${esc(this.L['Reset filter'])}</button></div>`;
    }

    drawDetail() {
      const L = this.L, l = this.leaf(this.st.sel), cur = this.cur && this.leaf(this.cur);
      const here = cur && (!l || cur.id !== l.id)
        ? `<div class="dl-hm__here-card"><i class="fa-solid fa-location-dot"></i><span>${esc(L['You are at'])} <b>${esc(cur.path.join(' › '))}</b></span><button type="button" class="dl-hm__btn" data-hm-mine>${esc(L['Guide for this screen'])}</button></div>` : '';
      if (!l) { this.detail.innerHTML = here + `<div class="dl-hm__empty">${esc(L['Pick a screen on the left to read its guide.'])}</div>`; return; }
      const t = l.topic, sib = l.parent ? l.parent.leaves.filter(x => x.parent === l.parent) : [l], i = sib.indexOf(l);
      const nav = (x, label, ic) => x ? `<button type="button" class="dl-hm__btn" data-hm-go="${esc(x.id)}" title="${esc(x.label)}"><i class="fa-solid ${ic}"></i> ${esc(label)}</button>` : '';
      const acts = `<div class="dl-hm__acts">`
        + (l.s.slug ? `<button type="button" class="dl-hm__btn is-primary" data-hm-open="${esc(l.id)}"><i class="fa-solid fa-arrow-up-right-from-square"></i> ${esc(L['Open screen'])}</button>` : '')
        + (l.s.slug && t ? `<button type="button" class="dl-hm__btn" data-hm-copy="${esc(l.id)}"><i class="fa-solid fa-link"></i> ${esc(L['Copy guide link'])}</button>` : '')
        + `<span class="dl-hm__spacer"></span>${nav(sib[i - 1], L['Previous screen'], 'fa-chevron-left')}${nav(sib[i + 1], L['Next screen'], 'fa-chevron-right')}</div>`;
      const crumb = `<div class="dl-hm__crumb">${l.path.slice(0, -1).map(esc).join(' › ')}</div>`;
      if (!t) {
        this.detail.innerHTML = here + crumb + `<h3 class="dl-hm__title">${esc(l.label)}</h3>${l.s.slug ? `<code class="dl-hm__route">/v2/s/${esc(l.s.slug)}</code>` : ''}`
          + `<div class="dl-hm__empty is-warn"><i class="fa-solid fa-circle-exclamation"></i><b>${esc(L['There is no guide for this screen yet.'])}</b>`
          + `<button type="button" class="dl-hm__btn" data-hm-request="${esc(l.id)}"><i class="fa-solid fa-paper-plane"></i> ${esc(L['Request guide'])}</button></div>${acts}`;
        return;
      }
      this.detail.innerHTML = here + crumb
        + `<h3 class="dl-hm__title">${esc(t.title)} <span class="dl-hm__type is-${esc(t.type.toLowerCase())}">${esc(t.type)}</span></h3>`
        + (l.s.slug ? `<code class="dl-hm__route">/v2/s/${esc(l.s.slug)}</code>` : '')
        + `<h4>${esc(L['Purpose'])}</h4><p>${esc(t.purpose)}</p>`
        + (t.steps?.length ? `<h4>${esc(L['Steps'])}</h4><ol class="dl-hm__steps">${t.steps.map(x => `<li>${esc(x)}</li>`).join('')}</ol>` : '')
        + (t.fields?.length ? `<h4>${esc(L['Fields'])}</h4><dl class="dl-hm__fields">${t.fields.map(f => `<dt>${esc(f[0])}</dt><dd>${esc(f[1])}</dd>`).join('')}</dl>` : '')
        + acts;
    }

    // Opens the screen in the main Shell; the Help window stays open (floating) so the steps can be followed.
    openScreen(l) {
      if (!l?.s.slug) return;
      const msg = {dl: 'open-item', code: l.s.code, url: l.s.url, label: l.label};
      if (window.parent !== window) window.parent.postMessage(msg, location.origin); else location.href = l.s.url;
    }

    copy(l) {
      const url = this.copyUrl + (this.copyUrl.includes('?') ? '&' : '?') + 'topic=' + encodeURIComponent(l.s.slug);
      const done = () => toast(this.L['Link copied']);
      if (navigator.clipboard?.writeText) navigator.clipboard.writeText(url).then(done, done);
      else { const a = document.createElement('textarea'); a.value = url; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e) {} a.remove(); done(); }
    }

    // "Request guide" -> Tickets tab with the screen and a title already filled in.
    request(l) {
      $(document, '[data-dl-ss-layout]')?.dlComponent?.show(this.reqTab);
      const f = $(document, `[data-dl-ss-panel="${this.reqTab}"] [name=screen_context]`), d = $(document, `[data-dl-ss-panel="${this.reqTab}"] [name=description]`);
      if (f) { f.value = l.path.join(' > '); f.dispatchEvent(new Event('input', {bubbles: true})); }
      if (d) { d.value = this.L['Request guide: '] + l.label; d.dispatchEvent(new Event('input', {bubbles: true})); d.focus(); }
    }
  }
  Devlib.define('HelpMap', HelpMap);
})(window.Devlib);
