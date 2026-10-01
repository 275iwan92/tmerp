/* DevLib · Chat */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, CASES, CASE_LABEL, applyCase, toast, TZ, at, T} = Devlib.h;
  /* ============================ CHAT (WhatsApp) ============================ */
  class Chat extends Devlib.Component {
    static selector = "[data-dl-chat]";
    init() {
      const self = this;
      this.me = JSON.parse(this.el.dataset.me || '{}');
      this.people = JSON.parse(this.el.dataset.people || '[]');
      this.convos = JSON.parse(this.el.dataset.convos || '{}');
      this.P = Object.fromEntries(this.people.map(p => [String(p.id), p]));
      this.cur = null;
      this.q = '';
      this.pending = null;
      this.caseIdx = 0;
      this.TONES = ['accent', 'green', 'amber-2', 'violet', 'danger', 'navy'];

      this.el.addEventListener('click', e => {
        const x = e.target;
        const o = x.closest('[data-wa-open]'); if (o) { if (o.hasAttribute('data-wa-fromnew')) $(this.el, '[data-wa-newpanel]').hidden = true; return this.open(o.dataset.waOpen); }
        if (x.closest('[data-wa-new]')) { $(this.el, '[data-wa-newpanel]').hidden = false; this.drawOffline(); $(this.el, '[data-wa-nq]').focus(); return; }
        if (x.closest('[data-wa-newx]')) { $(this.el, '[data-wa-newpanel]').hidden = true; return; }
        if (x.closest('[data-wa-back]')) { this.cur = null; this.drawList(); return this.drawMain(); }
        if (x.closest('[data-wa-photo]')) { const p = this.P[this.cur]; if (p.photo) this.lightbox(p.photo, p.name); return; }
        const im = x.closest('[data-wa-img]'); if (im) { const m = this.conv(this.cur).msgs[+im.dataset.waImg]; return this.lightbox(m.img, m.t); }
        if (x.closest('[data-wa-lbx]') || x.matches('[data-wa-lb]')) { $(this.el, '[data-wa-lb]').hidden = true; return; }
        if (x.closest('[data-wa-attach]')) return $(this.el, '[data-wa-file]').click();
        if (x.closest('[data-wa-unpend]')) return this.clearPending();
        if (x.closest('[data-wa-send]')) return this.send();
        const cb = x.closest('[data-wa-case]'); if (cb) { const inp = $(this.el, '[data-wa-text]'); this.caseIdx = (this.caseIdx + 1) % 3; cb.textContent = CASE_LABEL[CASES[this.caseIdx]]; inp.value = applyCase(inp.value, CASES[this.caseIdx]); inp.focus(); }
      });
      this.el.addEventListener('input', e => {
        if (e.target.matches('[data-wa-q]')) { this.q = e.target.value.trim().toLowerCase(); this.drawList(); }
        if (e.target.matches('[data-wa-nq]')) this.drawOffline();
        if (e.target.matches('[data-wa-text]') && this.caseIdx) { const t = e.target, p = t.selectionStart; t.value = applyCase(t.value, CASES[this.caseIdx]); try { t.setSelectionRange(p, p); } catch (er) {} }
      });
      this.el.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.matches('[data-wa-text]')) { e.preventDefault(); this.send(); }
        if (e.key === 'Escape' && !$(this.el, '[data-wa-lb]').hidden) $(this.el, '[data-wa-lb]').hidden = true;
      });
      $(this.el, '[data-wa-file]').addEventListener('change', e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        if (!f.type.startsWith('image/')) return toast(T('ch_only_photo'), 'warn');
        if (f.size > 5 * 1024 * 1024) return toast(T('ch_photo_max'), 'warn');
        const r = new FileReader();
        r.onload = () => { this.pending = r.result; const pv = $(this.el, '[data-wa-preview]'); pv.hidden = false; pv.innerHTML = `<img src="${this.pending}" alt=""><span>${esc(T('ch_caption_hint'))}</span><button type="button" class="dl-wa__ib" data-wa-unpend aria-label="${esc(T('ch_cancel_photo'))}"><i class="fa-solid fa-xmark"></i></button>`; $(this.el, '[data-wa-text]').focus(); };
        r.readAsDataURL(f);
      });
      $(this.el, '[data-wa-meav]').outerHTML = this.avatar(this.me);
      this.el.dlChat = {get unread() { return self.people.reduce((n, p) => n + (self.conv(p.id).unread || 0), 0); }, open: this.open, send: (id, t) => { this.open(id); $(this.el, '[data-wa-text]').value = t; this.send(); }, get convos() { return self.convos; }};
      this.drawList();this.drawMain();
    }

    conv(id) {
      return (this.convos[id] ||= {unread: 0, msgs: []});
    }

    initials(n) {
      return String(n || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    avatar(p, dot) {
      return `<span class="dl-wa__av" style="--c:var(--dl-${this.TONES[(String(p?.name || '').length + String(p?.id || '').length) % this.TONES.length]})">${p?.photo ? `<img src="${esc(p.photo)}" alt="">` : `<span>${esc(this.initials(p?.name))}</span>`}${dot ? '<i class="dl-wa__dot"></i>' : ''}</span>`;
    }

    ticks(st) {
      return st === 'read' ? '<i class="fa-solid fa-check-double dl-wa__tk is-read" title="Dibaca"></i>' : st === 'delivered' ? '<i class="fa-solid fa-check-double dl-wa__tk" title="Diterima"></i>' : '<i class="fa-solid fa-check dl-wa__tk" title="Terkirim"></i>';
    }

    last(id) { const m = this.conv(id).msgs; return m[m.length - 1]; }

    preview(m) {
      return !m ? '' : (m.me ? this.ticks(m.st) + ' ' : '') + (m.img ? '<i class="fa-solid fa-camera"></i> ' + esc(m.t || 'Foto') : esc(m.t));
    }

    status(p) {
      return p.online ? 'online' : `terakhir dilihat ${esc(p.seen || '-')}`;
    }

    now() {
      return new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ()}).replace('.', ':');
    }

    match(p) {
      return !this.q || p.name.toLowerCase().includes(this.q) || String(p.role || '').toLowerCase().includes(this.q);
    }

    row(p) { const m = this.last(p.id), u = this.conv(p.id).unread; return `<button type="button" class="dl-wa__row ${String(this.cur) === String(p.id) ? 'is-active' : ''}" data-wa-open="${esc(p.id)}">${this.avatar(p, p.online)}<span class="dl-wa__rt"><span class="dl-wa__r1"><b>${esc(p.name)}</b><small class="${u ? 'is-new' : ''}">${esc(m?.at || '')}</small></span><span class="dl-wa__r2"><span>${m ? this.preview(m) : `<em>${esc(p.role || '')}</em>`}</span>${u ? `<span class="dl-wa__badge">${u}</span>` : ''}</span></span></button>`; }

    drawList() {
      const on = this.people.filter(p => p.online && this.match(p)), other = this.people.filter(p => !p.online && this.conv(p.id).msgs.length && this.match(p));
      $(this.el, '[data-wa-list]').innerHTML = `<div class="dl-wa__cap">Sedang login · ${this.people.filter(p => p.online).length}</div>` + (on.map(this.row).join('') || '<p class="dl-wa__none">Tidak ada yang cocok.</p>')
        + (other.length ? `<div class="dl-wa__cap">Percakapan lain</div>${other.map(this.row).join('')}` : '');
      const unread = this.people.reduce((n, p) => n + (this.conv(p.id).unread || 0), 0);
      this.el.dispatchEvent(new CustomEvent('dl:chat-unread', {bubbles: true, detail: {count: unread}}));
    }

    drawOffline() {
      const nq = ($(this.el, '[data-wa-nq]').value || '').trim().toLowerCase();
      $(this.el, '[data-wa-offline]').innerHTML = this.people.filter(p => !p.online && (!nq || p.name.toLowerCase().includes(nq))).map(p => `<button type="button" class="dl-wa__row" data-wa-open="${esc(p.id)}" data-wa-fromnew>${this.avatar(p)}<span class="dl-wa__rt"><span class="dl-wa__r1"><b>${esc(p.name)}</b></span><span class="dl-wa__r2"><span>${esc(p.role || '')} · ${this.status(p)}</span></span></span></button>`).join('') || '<p class="dl-wa__none">Semua orang sedang login.</p>';
    }

    drawMain() {
      const head = $(this.el, '[data-wa-head]'), box = $(this.el, '[data-wa-msgs]'), inp = $(this.el, '[data-wa-in]');
      this.el.classList.toggle('is-open', this.cur != null);
      if (this.cur == null) { head.innerHTML = ''; inp.hidden = true; box.innerHTML = '<div class="dl-wa__empty"><i class="fa-regular fa-comments"></i><b>Pilih percakapan</b><span>Daftar kiri berisi orang yang sedang login. Tekan <i class="fa-solid fa-plus"></i> untuk chat dengan yang tidak sedang login.</span></div>'; return; }
      const p = this.P[this.cur]; inp.hidden = false;
      head.innerHTML = `<button type="button" class="dl-wa__ib dl-wa__back" data-wa-back aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></button><button type="button" class="dl-wa__who" data-wa-photo>${this.avatar(p, p.online)}<span><b>${esc(p.name)}</b><small class="${p.online ? 'is-on' : ''}">${this.status(p)}</small></span></button>`;
      let day = null;
      box.innerHTML = this.conv(this.cur).msgs.map((m, i) => {
        const sep = m.day && m.day !== day ? `<div class="dl-wa__day"><span>${esc(day = m.day)}</span></div>` : '';
        return sep + `<div class="dl-wa__msg ${m.me ? 'is-me' : ''}">${m.img ? `<button type="button" class="dl-wa__img" data-wa-img="${i}"><img src="${esc(m.img)}" alt="Foto"></button>` : ''}${m.t ? `<span class="dl-wa__t">${esc(m.t)}</span>` : ''}<span class="dl-wa__meta">${esc(m.at || '')} ${m.me ? this.ticks(m.st) : ''}</span></div>`;
      }).join('');
      box.scrollTop = box.scrollHeight;
      $$(box, 'img').forEach(i => i.addEventListener('load', () => { box.scrollTop = box.scrollHeight; }, {once: true}));
    }

    open(id) { this.cur = String(id); this.conv(this.cur).unread = 0; this.clearPending(); this.drawList(); this.drawMain(); $(this.el, '[data-wa-text]').focus({preventScroll: true}); }
    lightbox(src, cap) { $(this.el, '[data-wa-lbimg]').src = src; $(this.el, '[data-wa-lbcap]').textContent = cap || ''; $(this.el, '[data-wa-lb]').hidden = false; }
    clearPending() { this.pending = null; const pv = $(this.el, '[data-wa-preview]'); pv.hidden = true; pv.innerHTML = ''; }

    send() {
      const inp = $(this.el, '[data-wa-text]'), t = inp.value.trim();
      if (this.cur == null || (!t && !this.pending)) return;
      const m = {me: true, t, img: this.pending || undefined, at: this.now(), day: 'Hari ini', st: 'sent'};
      this.conv(this.cur).msgs.push(m); inp.value = '';
      this.el.dispatchEvent(new CustomEvent('dl:chat-send', {bubbles: true, detail: {to: this.cur, text: t, image: this.pending}}));
      this.clearPending(); this.drawList(); this.drawMain();
      // Centang: tidak login = 1 centang (terkirim) sampai ia login; login = diterima lalu dibaca (pratinjau; di aplikasi dari server).
      if (this.P[this.cur].online) { setTimeout(() => { m.st = 'delivered'; this.drawList(); this.drawMain(); }, 600); setTimeout(() => { m.st = 'read'; this.drawList(); this.drawMain(); }, 1500); }
    }
  }
  Devlib.define("Chat", Chat);
})(window.Devlib);
