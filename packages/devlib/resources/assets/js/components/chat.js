/* DevLib · Chat */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, CASES, CASE_LABEL, applyCase, toast, TZ, at} = Devlib.h;
  /* ============================ CHAT (WhatsApp) ============================ */
  class Chat extends Devlib.Component {
    static selector = "[data-dl-chat]";
    init() {
      const el = this.el;
      const me = JSON.parse(el.dataset.me || '{}'), people = JSON.parse(el.dataset.people || '[]'), convos = JSON.parse(el.dataset.convos || '{}');
      const P = Object.fromEntries(people.map(p => [String(p.id), p]));
      const conv = id => (convos[id] ||= {unread: 0, msgs: []});
      let cur = null, q = '', pending = null, caseIdx = 0;
      const TONES = ['accent', 'green', 'amber-2', 'violet', 'danger', 'navy'];
      const initials = n => String(n || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const avatar = (p, dot) => `<span class="dl-wa__av" style="--c:var(--dl-${TONES[(String(p?.name || '').length + String(p?.id || '').length) % TONES.length]})">${p?.photo ? `<img src="${esc(p.photo)}" alt="">` : `<span>${esc(initials(p?.name))}</span>`}${dot ? '<i class="dl-wa__dot"></i>' : ''}</span>`;
      const ticks = st => st === 'read' ? '<i class="fa-solid fa-check-double dl-wa__tk is-read" title="Dibaca"></i>' : st === 'delivered' ? '<i class="fa-solid fa-check-double dl-wa__tk" title="Diterima"></i>' : '<i class="fa-solid fa-check dl-wa__tk" title="Terkirim"></i>';
      const last = id => { const m = conv(id).msgs; return m[m.length - 1]; };
      const preview = m => !m ? '' : (m.me ? ticks(m.st) + ' ' : '') + (m.img ? '<i class="fa-solid fa-camera"></i> ' + esc(m.t || 'Foto') : esc(m.t));
      const status = p => p.online ? 'online' : `terakhir dilihat ${esc(p.seen || '-')}`;
      const now = () => new Date().toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ()}).replace('.', ':');
      const match = p => !q || p.name.toLowerCase().includes(q) || String(p.role || '').toLowerCase().includes(q);

      const row = p => { const m = last(p.id), u = conv(p.id).unread; return `<button type="button" class="dl-wa__row ${String(cur) === String(p.id) ? 'is-active' : ''}" data-wa-open="${esc(p.id)}">${avatar(p, p.online)}<span class="dl-wa__rt"><span class="dl-wa__r1"><b>${esc(p.name)}</b><small class="${u ? 'is-new' : ''}">${esc(m?.at || '')}</small></span><span class="dl-wa__r2"><span>${m ? preview(m) : `<em>${esc(p.role || '')}</em>`}</span>${u ? `<span class="dl-wa__badge">${u}</span>` : ''}</span></span></button>`; };
      function drawList() {
        const on = people.filter(p => p.online && match(p)), other = people.filter(p => !p.online && conv(p.id).msgs.length && match(p));
        $(el, '[data-wa-list]').innerHTML = `<div class="dl-wa__cap">Sedang login · ${people.filter(p => p.online).length}</div>` + (on.map(row).join('') || '<p class="dl-wa__none">Tidak ada yang cocok.</p>')
          + (other.length ? `<div class="dl-wa__cap">Percakapan lain</div>${other.map(row).join('')}` : '');
        const unread = people.reduce((n, p) => n + (conv(p.id).unread || 0), 0);
        el.dispatchEvent(new CustomEvent('dl:chat-unread', {bubbles: true, detail: {count: unread}}));
      }
      function drawOffline() {
        const nq = ($(el, '[data-wa-nq]').value || '').trim().toLowerCase();
        $(el, '[data-wa-offline]').innerHTML = people.filter(p => !p.online && (!nq || p.name.toLowerCase().includes(nq))).map(p => `<button type="button" class="dl-wa__row" data-wa-open="${esc(p.id)}" data-wa-fromnew>${avatar(p)}<span class="dl-wa__rt"><span class="dl-wa__r1"><b>${esc(p.name)}</b></span><span class="dl-wa__r2"><span>${esc(p.role || '')} · ${status(p)}</span></span></span></button>`).join('') || '<p class="dl-wa__none">Semua orang sedang login.</p>';
      }
      function drawMain() {
        const head = $(el, '[data-wa-head]'), box = $(el, '[data-wa-msgs]'), inp = $(el, '[data-wa-in]');
        el.classList.toggle('is-open', cur != null);
        if (cur == null) { head.innerHTML = ''; inp.hidden = true; box.innerHTML = '<div class="dl-wa__empty"><i class="fa-regular fa-comments"></i><b>Pilih percakapan</b><span>Daftar kiri berisi orang yang sedang login. Tekan <i class="fa-solid fa-plus"></i> untuk chat dengan yang tidak sedang login.</span></div>'; return; }
        const p = P[cur]; inp.hidden = false;
        head.innerHTML = `<button type="button" class="dl-wa__ib dl-wa__back" data-wa-back aria-label="Kembali"><i class="fa-solid fa-arrow-left"></i></button><button type="button" class="dl-wa__who" data-wa-photo>${avatar(p, p.online)}<span><b>${esc(p.name)}</b><small class="${p.online ? 'is-on' : ''}">${status(p)}</small></span></button>`;
        let day = null;
        box.innerHTML = conv(cur).msgs.map((m, i) => {
          const sep = m.day && m.day !== day ? `<div class="dl-wa__day"><span>${esc(day = m.day)}</span></div>` : '';
          return sep + `<div class="dl-wa__msg ${m.me ? 'is-me' : ''}">${m.img ? `<button type="button" class="dl-wa__img" data-wa-img="${i}"><img src="${esc(m.img)}" alt="Foto"></button>` : ''}${m.t ? `<span class="dl-wa__t">${esc(m.t)}</span>` : ''}<span class="dl-wa__meta">${esc(m.at || '')} ${m.me ? ticks(m.st) : ''}</span></div>`;
        }).join('');
        box.scrollTop = box.scrollHeight;
        $$(box, 'img').forEach(i => i.addEventListener('load', () => { box.scrollTop = box.scrollHeight; }, {once: true}));
      }
      const open = id => { cur = String(id); conv(cur).unread = 0; clearPending(); drawList(); drawMain(); $(el, '[data-wa-text]').focus({preventScroll: true}); };
      const lightbox = (src, cap) => { $(el, '[data-wa-lbimg]').src = src; $(el, '[data-wa-lbcap]').textContent = cap || ''; $(el, '[data-wa-lb]').hidden = false; };
      function clearPending() { pending = null; const pv = $(el, '[data-wa-preview]'); pv.hidden = true; pv.innerHTML = ''; }
      function send() {
        const inp = $(el, '[data-wa-text]'), t = inp.value.trim();
        if (cur == null || (!t && !pending)) return;
        const m = {me: true, t, img: pending || undefined, at: now(), day: 'Hari ini', st: 'sent'};
        conv(cur).msgs.push(m); inp.value = '';
        el.dispatchEvent(new CustomEvent('dl:chat-send', {bubbles: true, detail: {to: cur, text: t, image: pending}}));
        clearPending(); drawList(); drawMain();
        // Centang: tidak login = 1 centang (terkirim) sampai ia login; login = diterima lalu dibaca (pratinjau; di aplikasi dari server).
        if (P[cur].online) { setTimeout(() => { m.st = 'delivered'; drawList(); drawMain(); }, 600); setTimeout(() => { m.st = 'read'; drawList(); drawMain(); }, 1500); }
      }

      el.addEventListener('click', e => {
        const x = e.target;
        const o = x.closest('[data-wa-open]'); if (o) { if (o.hasAttribute('data-wa-fromnew')) $(el, '[data-wa-newpanel]').hidden = true; return open(o.dataset.waOpen); }
        if (x.closest('[data-wa-new]')) { $(el, '[data-wa-newpanel]').hidden = false; drawOffline(); $(el, '[data-wa-nq]').focus(); return; }
        if (x.closest('[data-wa-newx]')) { $(el, '[data-wa-newpanel]').hidden = true; return; }
        if (x.closest('[data-wa-back]')) { cur = null; drawList(); return drawMain(); }
        if (x.closest('[data-wa-photo]')) { const p = P[cur]; if (p.photo) lightbox(p.photo, p.name); return; }
        const im = x.closest('[data-wa-img]'); if (im) { const m = conv(cur).msgs[+im.dataset.waImg]; return lightbox(m.img, m.t); }
        if (x.closest('[data-wa-lbx]') || x.matches('[data-wa-lb]')) { $(el, '[data-wa-lb]').hidden = true; return; }
        if (x.closest('[data-wa-attach]')) return $(el, '[data-wa-file]').click();
        if (x.closest('[data-wa-unpend]')) return clearPending();
        if (x.closest('[data-wa-send]')) return send();
        const cb = x.closest('[data-wa-case]'); if (cb) { const inp = $(el, '[data-wa-text]'); caseIdx = (caseIdx + 1) % 3; cb.textContent = CASE_LABEL[CASES[caseIdx]]; inp.value = applyCase(inp.value, CASES[caseIdx]); inp.focus(); }
      });
      el.addEventListener('input', e => {
        if (e.target.matches('[data-wa-q]')) { q = e.target.value.trim().toLowerCase(); drawList(); }
        if (e.target.matches('[data-wa-nq]')) drawOffline();
        if (e.target.matches('[data-wa-text]') && caseIdx) { const t = e.target, p = t.selectionStart; t.value = applyCase(t.value, CASES[caseIdx]); try { t.setSelectionRange(p, p); } catch (er) {} }
      });
      el.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.matches('[data-wa-text]')) { e.preventDefault(); send(); }
        if (e.key === 'Escape' && !$(el, '[data-wa-lb]').hidden) $(el, '[data-wa-lb]').hidden = true;
      });
      $(el, '[data-wa-file]').addEventListener('change', e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        if (!f.type.startsWith('image/')) return toast('Hanya foto yang bisa dikirim.', 'warn');
        if (f.size > 5 * 1024 * 1024) return toast('Foto maksimal 5 MB.', 'warn');
        const r = new FileReader();
        r.onload = () => { pending = r.result; const pv = $(el, '[data-wa-preview]'); pv.hidden = false; pv.innerHTML = `<img src="${pending}" alt=""><span>Tambahkan keterangan lalu tekan kirim</span><button type="button" class="dl-wa__ib" data-wa-unpend aria-label="Batalkan foto"><i class="fa-solid fa-xmark"></i></button>`; $(el, '[data-wa-text]').focus(); };
        r.readAsDataURL(f);
      });
      $(el, '[data-wa-meav]').outerHTML = avatar(me);
      el.dlChat = {get unread() { return people.reduce((n, p) => n + (conv(p.id).unread || 0), 0); }, open, send: (id, t) => { open(id); $(el, '[data-wa-text]').value = t; send(); }, get convos() { return convos; }};
      drawList(); drawMain();
    }
  }
  Devlib.define("Chat", Chat);
})(window.Devlib);
