/* DevLib · Chat */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, CASES, CASE_LABEL, applyCase, toast, T, Tf, DlDate, DlTime, numFmt} = Devlib.h;
  const FILE_ICONS = {pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word', xls: 'fa-file-excel', xlsx: 'fa-file-excel', csv: 'fa-file-csv', ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint',
    zip: 'fa-file-zipper', rar: 'fa-file-zipper', '7z': 'fa-file-zipper', txt: 'fa-file-lines', jpg: 'fa-file-image', jpeg: 'fa-file-image', png: 'fa-file-image', gif: 'fa-file-image', webp: 'fa-file-image', mp4: 'fa-file-video', mp3: 'fa-file-audio'};
  const TICKS = {read: ['fa-solid fa-check-double', 'is-read', 'ch_read'], delivered: ['fa-solid fa-check-double', '', 'ch_delivered'], sent: ['fa-solid fa-check', '', 'ch_sent'],
    sending: ['fa-regular fa-clock', '', 'ch_sending'], failed: ['fa-solid fa-circle-exclamation', 'is-failed', 'ch_failed']};
  /* ============================ CHAT (WhatsApp) ============================ */
  // data-api kosong = pratinjau (katalog DevLib, tanpa server). Terisi = aplikasi: pesan disimpan lewat api.send, pesan baru +
  // siapa yang online + centang dibaca diambil tiap api.poll_seconds dari api.poll (lihat Devlib\Components\Chat).
  // api.realtime terisi = kabar itu juga didorong seketika lewat WebSocket (plus "sedang mengetik"), dan poll cukup tiap
  // api.realtime_poll_seconds sebagai jaring pengaman; WebSocket putus = kembali ke poll cepat sampai tersambung lagi.
  class Chat extends Devlib.Component {
    static selector = "[data-dl-chat]";
    init() {
      const self = this;
      this.me = JSON.parse(this.el.dataset.me || '{}');
      this.people = JSON.parse(this.el.dataset.people || '[]');
      this.convos = JSON.parse(this.el.dataset.convos || '{}');
      this.api = JSON.parse(this.el.dataset.api || '{}');
      this.live = !!this.api.poll;
      this.lastId = +this.api.last_id || 0;
      this.maxMb = +this.api.max_mb || 20;
      this.P = Object.fromEntries(this.people.map(p => [String(p.id), p]));
      this.cur = null;
      this.q = '';
      this.pending = null;
      this.caseIdx = 0;
      this.typing = {};
      this.TONES = ['accent', 'green', 'amber-2', 'violet', 'danger', 'navy'];

      this.el.addEventListener('click', e => {
        const x = e.target;
        const o = x.closest('[data-wa-open]'); if (o) { if (o.hasAttribute('data-wa-fromnew')) $(this.el, '[data-wa-newpanel]').hidden = true; return this.open(o.dataset.waOpen); }
        if (x.closest('[data-wa-new]')) { $(this.el, '[data-wa-newpanel]').hidden = false; this.drawOffline(); $(this.el, '[data-wa-nq]').focus(); return; }
        if (x.closest('[data-wa-newx]')) { $(this.el, '[data-wa-newpanel]').hidden = true; return; }
        if (x.closest('[data-wa-back]')) { this.cur = null; this.drawList(); return this.drawMain(); }
        if (x.closest('[data-wa-photo]')) { const p = this.P[this.cur]; if (p.photo) this.lightbox(p.photo, p.name); return; }
        const im = x.closest('[data-wa-img]'); if (im) { const m = this.conv(this.cur).msgs[+im.dataset.waImg]; return this.lightbox(this.image(m), m.t, m.file); }
        if (x.closest('[data-wa-more]')) return this.load(this.conv(this.cur).msgs.find(m => m.id)?.id);
        if (x.closest('[data-wa-lbx]') || x.matches('[data-wa-lb]')) { $(this.el, '[data-wa-lb]').hidden = true; return; }
        if (x.closest('[data-wa-attach]')) return $(this.el, '[data-wa-file]').click();
        if (x.closest('[data-wa-unpend]')) return this.clearPending();
        if (x.closest('[data-wa-send]')) return this.send();
        const cb = x.closest('[data-wa-case]'); if (cb) { const inp = $(this.el, '[data-wa-text]'); this.caseIdx = (this.caseIdx + 1) % 3; cb.textContent = CASE_LABEL[CASES[this.caseIdx]]; inp.value = applyCase(inp.value, CASES[this.caseIdx]); inp.focus(); }
      });
      this.el.addEventListener('input', e => {
        if (e.target.matches('[data-wa-q]')) { this.q = e.target.value.trim().toLowerCase(); this.drawList(); }
        if (e.target.matches('[data-wa-nq]')) this.drawOffline();
        if (e.target.matches('[data-wa-text]')) this.sayTyping();
        if (e.target.matches('[data-wa-text]') && this.caseIdx) { const t = e.target, p = t.selectionStart; t.value = applyCase(t.value, CASES[this.caseIdx]); try { t.setSelectionRange(p, p); } catch (er) {} }
      });
      this.el.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.matches('[data-wa-text]')) { e.preventDefault(); this.send(); }
        if (e.key === 'Escape' && !$(this.el, '[data-wa-lb]').hidden) $(this.el, '[data-wa-lb]').hidden = true;
      });
      $(this.el, '[data-wa-file]').addEventListener('change', e => {
        const f = e.target.files[0]; e.target.value = ''; if (!f) return;
        if (!f.size) return toast(T('ch_file_empty'), 'warn');
        if (f.size > this.maxMb * 1024 * 1024) return toast(Tf('ch_file_max', {n: this.maxMb}), 'warn');
        this.clearPending();
        const image = /^image\/(jpeg|png|gif|webp)$/.test(f.type);
        this.pending = {file: f, image, url: image ? URL.createObjectURL(f) : null};
        const pv = $(this.el, '[data-wa-preview]'); pv.hidden = false;
        pv.innerHTML = `${image ? `<img src="${this.pending.url}" alt="">` : `<i class="fa-solid ${this.fileIcon(f.name)} dl-wa__pvico"></i>`}<span><b>${esc(f.name)}</b> · ${esc(this.size(f.size))}<br>${esc(T('ch_caption_hint'))}</span><button type="button" class="dl-wa__ib" data-wa-unpend aria-label="${esc(T('ch_cancel_file'))}"><i class="fa-solid fa-xmark"></i></button>`;
        $(this.el, '[data-wa-text]').focus();
      });
      $(this.el, '[data-wa-meav]').outerHTML = this.avatar(this.me, true, false);
      this.el.dlChat = {get unread() { return self.people.reduce((n, p) => n + (self.conv(p.id).unread || 0), 0); }, open: this.open, send: (id, t) => { this.open(id); $(this.el, '[data-wa-text]').value = t; this.send(); }, get convos() { return self.convos; }};
      this.drawList();this.drawMain();
      if (this.live) {
        this.realtime(false);
        this.poll();
        this.connect();
        // Bunyi baru boleh setelah ada sentuhan user di halaman (aturan browser).
        ['pointerdown', 'keydown'].forEach(ev => document.addEventListener(ev, () => this.audio()?.resume(), {once: true, capture: true}));
      }
    }

    conv(id) {
      return (this.convos[id] ||= {unread: 0, msgs: []});
    }

    initials(n) {
      return String(n || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    }

    // on = sedang login: warna penuh + cincin hijau; tidak login = abu-abu.
    avatar(p, on, dot = on) {
      const tone = on ? this.TONES[(String(p?.name || '').length + String(p?.id || '').length) % this.TONES.length] : 'muted';
      return `<span class="dl-wa__av ${on ? 'is-on' : ''}" style="--c:var(--dl-${tone})">${p?.photo ? `<img src="${esc(p.photo)}" alt="">` : `<span>${esc(this.initials(p?.name))}</span>`}${dot ? '<i class="dl-wa__dot"></i>' : ''}</span>`;
    }

    // Centang pesan sendiri: terkirim -> diterima (lawan sedang online / layarnya sudah mengambilnya) -> dibaca.
    state(m, id) {
      if (!this.live || m.st === 'sending' || m.st === 'failed') return m.st || 'sent';
      const p = this.P[id] || {};
      return m.id <= (this.conv(id).read || 0) ? 'read' : p.online || (p.seen || 0) >= m.ts ? 'delivered' : 'sent';
    }

    ticks(st) {
      const [icon, cls, key] = TICKS[st] || TICKS.sent;
      return `<i class="${icon} dl-wa__tk ${cls}" title="${esc(T(key))}"></i>`;
    }

    last(id) { const m = this.conv(id).msgs; return m[m.length - 1]; }
    image(m) { return m.img || (m.file?.image ? m.file.url : null); }
    fileIcon(name) { return FILE_ICONS[String(name).split('.').pop().toLowerCase()] || 'fa-file'; }
    size(b) { return b >= 1048576 ? numFmt(b / 1048576, 1) + ' MB' : numFmt(Math.max(1, Math.round(b / 1024))) + ' KB'; }

    preview(m, id) {
      const body = this.image(m) ? '<i class="fa-solid fa-camera"></i> ' + esc(m.t || T('ch_photo')) : m.file ? '<i class="fa-solid fa-paperclip"></i> ' + esc(m.t || m.file.name) : esc(m.t);
      return (m.me ? this.ticks(this.state(m, id)) + ' ' : '') + body;
    }

    // Jam & hari dari ts (epoch, zona waktu aplikasi); data pratinjau membawa teks jadi 'at' / 'day'.
    stamp(ts) { const d = new Date(ts * 1000), day = DlDate.of(d); return {day, time: DlTime.of(d).display(), today: day.iso() === DlDate.today().iso(), yesterday: day.iso() === DlDate.of(new Date(Date.now() - 864e5)).iso()}; }
    clock(m) { return m.at ?? (m.ts ? this.stamp(m.ts).time : ''); }
    dayLabel(m) { if (m.day || !m.ts) return m.day || null; const s = this.stamp(m.ts); return s.today ? T('today') : s.yesterday ? T('ch_yesterday') : s.day.display(); }
    when(m) { if (m.at || !m.ts) return m.at || ''; const s = this.stamp(m.ts); return s.today ? s.time : s.yesterday ? T('ch_yesterday') : s.day.display(); }

    status(p) {
      if (this.typing[p.id]) return T('ch_typing');
      if (p.online) return T('ch_online');
      if (!p.seen) return T('ch_offline');
      if (typeof p.seen === 'string') return Tf('ch_last_seen', {when: p.seen});
      const s = this.stamp(p.seen);
      return Tf('ch_last_seen', {when: s.today ? Tf('ch_today_at', s) : s.yesterday ? Tf('ch_yesterday_at', s) : s.day.display() + ' ' + s.time});
    }

    match(p) {
      return !this.q || p.name.toLowerCase().includes(this.q) || String(p.role || '').toLowerCase().includes(this.q);
    }

    row(p, fromNew = false) {
      const m = fromNew ? null : this.last(p.id), u = fromNew ? 0 : this.conv(p.id).unread;
      const sub = !fromNew && this.typing[p.id] ? `<em class="dl-wa__typing">${esc(T('ch_typing'))}</em>` : m ? this.preview(m, p.id) : fromNew ? esc([p.role, this.status(p)].filter(Boolean).join(' · ')) : `<em>${esc(p.role || '')}</em>`;
      return `<button type="button" class="dl-wa__row ${p.online ? 'is-on' : 'is-off'} ${!fromNew && String(this.cur) === String(p.id) ? 'is-active' : ''}" data-wa-open="${esc(p.id)}" ${fromNew ? 'data-wa-fromnew' : ''}>${this.avatar(p, p.online)}<span class="dl-wa__rt"><span class="dl-wa__r1"><b>${esc(p.name)}</b><small class="${u ? 'is-new' : ''}">${esc(m ? this.when(m) : '')}</small></span><span class="dl-wa__r2"><span>${sub}</span>${u ? `<span class="dl-wa__badge">${u}</span>` : ''}</span></span></button>`;
    }

    // Digambar ulang hanya bila isinya berubah: daftar yang diganti di tengah klik membuat kliknya hilang.
    drawList() {
      const recent = (a, b) => (this.last(b.id)?.ts || 0) - (this.last(a.id)?.ts || 0);
      const online = this.people.filter(p => p.online), on = online.filter(this.match).sort(recent),
        other = this.people.filter(p => !p.online && this.conv(p.id).msgs.length && this.match(p)).sort(recent);
      const html = `<div class="dl-wa__cap">${esc(T('ch_signed_in'))} · ${online.length}</div>` + (on.map(p => this.row(p)).join('') || `<p class="dl-wa__none">${esc(T(online.length ? 'ch_no_match' : 'ch_nobody_online'))}</p>`)
        + (other.length ? `<div class="dl-wa__cap is-off">${esc(T('ch_other_convos'))}</div>${other.map(p => this.row(p)).join('')}` : '');
      if (html !== this.listHtml) $(this.el, '[data-wa-list]').innerHTML = this.listHtml = html;
      const unread = this.people.reduce((n, p) => n + (this.conv(p.id).unread || 0), 0);
      this.el.dispatchEvent(new CustomEvent('dl:chat-unread', {bubbles: true, detail: {count: unread}}));
    }

    drawOffline() {
      const nq = ($(this.el, '[data-wa-nq]').value || '').trim().toLowerCase();
      $(this.el, '[data-wa-offline]').innerHTML = this.people.filter(p => !p.online && (!nq || p.name.toLowerCase().includes(nq))).map(p => this.row(p, true)).join('') || `<p class="dl-wa__none">${esc(T(nq ? 'ch_no_match' : 'ch_all_online'))}</p>`;
    }

    bubble(m, i) {
      const img = this.image(m), st = m.me ? this.state(m, this.cur) : null;
      const file = m.file && !img ? `<a class="dl-wa__file" ${m.file.url ? `href="${esc(m.file.url)}" download="${esc(m.file.name)}" title="${esc(T('ch_download'))}"` : ''}><i class="fa-solid ${this.fileIcon(m.file.name)}"></i><span><b>${esc(m.file.name)}</b><small>${esc(this.size(m.file.size))}</small></span>${m.file.url ? '<i class="fa-solid fa-download"></i>' : ''}</a>` : '';
      return `<div class="dl-wa__msg ${m.me ? 'is-me' : ''} ${st === 'failed' ? 'is-failed' : ''}">${img ? `<button type="button" class="dl-wa__img" data-wa-img="${i}"><img src="${esc(img)}" alt="${esc(m.file?.name || T('ch_photo'))}"></button>` : ''}${file}${m.t ? `<span class="dl-wa__t">${esc(m.t)}</span>` : ''}${m.up != null ? `<span class="dl-wa__up"><i style="width:${m.up}%"></i></span>` : ''}<span class="dl-wa__meta">${esc(this.clock(m))} ${m.me ? this.ticks(st) : ''}</span></div>`;
    }

    // keep = gambar ulang karena data berubah (bukan pindah percakapan): posisi gulir dipertahankan kecuali sedang di dasar.
    drawMain(keep = false) {
      const head = $(this.el, '[data-wa-head]'), box = $(this.el, '[data-wa-msgs]'), inp = $(this.el, '[data-wa-in]');
      this.el.classList.toggle('is-open', this.cur != null);
      if (this.cur == null) { head.innerHTML = ''; inp.hidden = true; box.innerHTML = `<div class="dl-wa__empty"><i class="fa-regular fa-comments"></i><b>${esc(T('ch_pick'))}</b><span>${esc(T('ch_pick_hint'))}</span></div>`; return; }
      const p = this.P[this.cur], c = this.conv(this.cur); inp.hidden = false;
      head.innerHTML = `<button type="button" class="dl-wa__ib dl-wa__back" data-wa-back aria-label="${esc(T('ch_back'))}"><i class="fa-solid fa-arrow-left"></i></button><button type="button" class="dl-wa__who" data-wa-photo>${this.avatar(p, p.online)}<span><b>${esc(p.name)}</b><small class="${p.online || this.typing[p.id] ? 'is-on' : ''}">${esc(this.status(p))}</small></span></button>`;
      const stick = !keep || box.scrollHeight - box.scrollTop - box.clientHeight < 60, top = box.scrollTop;
      let day = null;
      box.innerHTML = (c.more ? `<button type="button" class="dl-wa__more" data-wa-more>${esc(T('ch_earlier'))}</button>` : '') + c.msgs.map((m, i) => {
        const d = this.dayLabel(m), sep = d && d !== day ? `<div class="dl-wa__day"><span>${esc(day = d)}</span></div>` : '';
        return sep + this.bubble(m, i);
      }).join('');
      if (!stick) { box.scrollTop = top; return; }
      box.scrollTop = box.scrollHeight;
      $$(box, 'img').forEach(i => i.addEventListener('load', () => { box.scrollTop = box.scrollHeight; }, {once: true}));
    }

    open(id) {
      this.cur = String(id); const c = this.conv(this.cur); c.unread = 0; this.clearPending(); this.drawList(); this.drawMain(); $(this.el, '[data-wa-text]').focus({preventScroll: true});
      if (this.live) c.loaded ? this.poll() : this.load();
    }

    lightbox(src, cap, file) {
      const dl = $(this.el, '[data-wa-lbdl]');
      dl.hidden = !file?.url; if (file?.url) { dl.href = file.url; dl.download = file.name; }
      $(this.el, '[data-wa-lbimg]').src = src; $(this.el, '[data-wa-lbcap]').textContent = cap || ''; $(this.el, '[data-wa-lb]').hidden = false;
    }

    clearPending() { if (this.pending?.url) URL.revokeObjectURL(this.pending.url); this.hidePending(); }
    hidePending() { this.pending = null; const pv = $(this.el, '[data-wa-preview]'); pv.hidden = true; pv.innerHTML = ''; }

    /* ---------------- server ---------------- */
    async req(url, body) {
      const opt = {credentials: 'same-origin', headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}};
      if (body) {
        opt.method = 'POST'; opt.headers['X-CSRF-TOKEN'] = this.api.csrf || window.DL_CSRF || '';
        if (body instanceof FormData) opt.body = body; else { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
      }
      const r = await fetch(url, opt), j = await r.json().catch(() => ({}));
      if (!r.ok) throw Object.assign(new Error(j.message || ''), {status: r.status});
      return j;
    }

    // Sesi habis = berhenti bertanya ke server (halaman login yang akan menjawab), bukan mengulang terus.
    fail(e, key = 'ch_send_failed') {
      if (e.status === 401 || e.status === 419) { if (!this.dead) toast(T('ch_session_ended'), 'warn'); this.dead = true; clearInterval(this.timer); this.ws?.close(); return; }
      toast(e.status === 422 && e.message ? e.message : T(key), 'danger');
    }

    // Pesan masuk ke percakapan urut id; pesan yang masih dikirim (belum ber-id) tetap paling bawah. false = sudah ada.
    add(id, m) {
      const c = this.conv(id); if (c.msgs.some(x => x.id === m.id)) return false;
      const at = c.msgs.findIndex(x => !x.id); at < 0 ? c.msgs.push(m) : c.msgs.splice(at, 0, m);
      return true;
    }

    // Isi percakapan (halaman terbaru; before = id pesan tertua yang tampil -> halaman sebelumnya).
    async load(before = null) {
      const id = this.cur, c = this.conv(id), box = $(this.el, '[data-wa-msgs]');
      try {
        const r = await this.req(`${this.api.messages}?peer=${encodeURIComponent(id)}${before ? '&before=' + before : ''}`), got = new Set(r.msgs.map(m => m.id));
        c.msgs = [...r.msgs, ...c.msgs.filter(m => !got.has(m.id))];
        Object.assign(c, {loaded: true, more: r.more, read: Math.max(c.read || 0, r.read)});
        if (this.cur !== id) return;
        const fromBottom = box.scrollHeight - box.scrollTop;
        this.drawMain(!!before); if (before) box.scrollTop = box.scrollHeight - fromBottom;
      } catch (e) { this.fail(e, 'ch_load_failed'); }
    }

    // Percakapan ini sedang benar-benar dilihat user (pesan masuknya langsung dianggap dibaca, tanpa bunyi).
    shown() { return this.el.offsetWidth > 0 && getComputedStyle(this.el).visibility !== 'hidden'; }
    viewing(id) { return String(this.cur) === String(id) && document.hasFocus() && this.shown(); }

    sig() {
      if (this.cur == null) return '';
      const c = this.conv(this.cur), p = this.P[this.cur];
      return [c.msgs.length, p.online, p.online ? '' : p.seen, !!this.typing[this.cur], c.msgs.filter(m => m.me).map(m => this.state(m, this.cur)).join()].join('|');
    }

    // Ubah data, lalu gambar ulang seperlunya (daftar; percakapan terbuka hanya bila isinya berubah).
    update(fn) { const was = this.sig(); fn(); this.drawList(); if (this.sig() !== was) this.drawMain(true); }

    async poll() {
      if (this.dead) return;
      if (this.busy) { this.again = true; return; }
      this.busy = true;
      try {
        const seeing = this.cur != null && this.viewing(this.cur), open = seeing ? '&open=' + encodeURIComponent(this.cur) : '';
        // Kembali melihat percakapan yang terbuka (jendela difokus lagi / panel chat ditampilkan lagi) = sudah dibaca.
        if (seeing && this.conv(this.cur).unread) { this.conv(this.cur).unread = 0; this.drawList(); }
        this.apply(await this.req(`${this.api.poll}?after=${this.lastId}${open}`));
      } catch (e) { if (e.status === 401 || e.status === 419) this.fail(e); }
      this.busy = false;
      // Ada yang minta poll selagi yang ini berjalan (mis. pesan masuk yang harus langsung ditandai dibaca).
      if (this.again) { this.again = false; this.poll(); }
    }

    apply(r) {
      let from;
      this.update(() => {
        Object.entries(r.presence || {}).forEach(([id, v]) => { const p = this.P[id]; if (p) { p.online = !!v[0]; p.seen = v[1]; } });
        Object.entries(r.read || {}).forEach(([id, n]) => { if (this.P[id]) this.conv(id).read = Math.max(this.conv(id).read || 0, n); });
        from = this.receive(r.msgs || []);
        this.lastId = Math.max(this.lastId, +r.last_id || 0);
      });
      this.notify(from);
    }

    // Pesan yang datang (poll / WebSocket). Baru, dari orang lain, dan tidak sedang dilihat = belum dibaca.
    // Kembali: pengirim pesan baru terakhir (untuk bunyi), atau null.
    receive(list) {
      let from = null;
      list.forEach(m => {
        const p = this.P[m.peer];
        if (!p || !this.add(m.peer, m) || m.me) return;
        this.setTyping(m.peer, false);
        if (!this.viewing(m.peer)) { this.conv(m.peer).unread++; from = p; }
      });
      return from;
    }

    notify(from) { if (from) { this.ring(); if (!this.shown()) toast(Tf('ch_new_from', {name: from.name})); } }

    /* ---------------- WebSocket (protokol Pusher: Laravel Reverb) ---------------- */
    // Tersambung = kabar datang sendiri, poll cukup jarang (jaring pengaman + denyut online); putus = poll cepat lagi.
    realtime(on) {
      this.rt = on;
      clearInterval(this.timer); this.timer = setInterval(this.poll, (on ? +this.api.realtime_poll_seconds || 30 : +this.api.poll_seconds || 5) * 1000);
      if (on) this.poll();
    }

    connect() {
      const rt = this.api.realtime; if (!rt || this.dead || !window.WebSocket) return;
      let ws;
      try { ws = this.ws = new WebSocket(`${rt.tls ? 'wss' : 'ws'}://${rt.host || location.hostname}:${rt.port}/app/${encodeURIComponent(rt.key)}?protocol=7&client=devlib&version=1.0`); } catch (e) { setTimeout(this.connect, 30000); return; }
      ws.onmessage = e => {
        let f; try { f = JSON.parse(e.data); f.data = typeof f.data === 'string' ? JSON.parse(f.data || '{}') : f.data || {}; } catch (er) { return; }
        if (f.event === 'pusher:connection_established') this.subscribe(ws, f.data);
        else if (f.event === 'pusher:ping') ws.send('{"event":"pusher:pong","data":{}}');
        else if (String(f.event).startsWith('chat.')) this.signal(f.event, f.data);
      };
      ws.onclose = () => { clearInterval(ws.beat); if (this.ws !== ws || this.dead) return; this.realtime(false); setTimeout(this.connect, 30000); };
    }

    // Berlangganan channel milik user ini (izin ditandatangani server), lalu jaga sambungan tetap hidup.
    async subscribe(ws, d) {
      ws.beat = setInterval(() => { if (ws.readyState === 1) ws.send('{"event":"pusher:ping","data":{}}'); }, Math.max(10, (d.activity_timeout || 30) - 5) * 1000);
      try {
        for (const channel of this.api.realtime.channels) {
          const a = await this.req(this.api.realtime.auth, {socket_id: d.socket_id, channel_name: channel});
          ws.send(JSON.stringify({event: 'pusher:subscribe', data: {channel, auth: a.auth}}));
        }
        this.realtime(true);
      } catch (e) { if (e.status === 401 || e.status === 419) this.fail(e); ws.close(); }
    }

    signal(ev, d) {
      let from = null, seeing = false;
      this.update(() => {
        if (ev === 'chat.message') {
          const me = String(d.from) === String(this.me.id), peer = me ? d.to : d.from;
          seeing = !me && this.viewing(peer);
          from = this.receive([{...d.msg, me, peer}]);
        } else if (ev === 'chat.read') {
          if (this.P[d.reader]) this.conv(d.reader).read = Math.max(this.conv(d.reader).read || 0, d.id);
        } else if (ev === 'chat.typing') this.setTyping(d.from, true);
        else if (ev === 'chat.presence') {
          const p = this.P[d.id]; if (!p) return;
          p.online = !!d.online; p.seen = d.seen; if (!p.online) this.setTyping(d.id, false);
        }
      });
      this.notify(from);
      // Pesan masuk ke percakapan yang sedang dilihat: langsung tandai dibaca di server (pengirim mendapat centang biru).
      if (seeing) this.poll();
    }

    // "sedang mengetik…": tampil 4 detik sejak kabar terakhir; hilang saat pesannya datang.
    setTyping(id, on) {
      clearTimeout(this.typing[id]); delete this.typing[id];
      if (on && this.P[id]) this.typing[id] = setTimeout(() => this.update(() => { delete this.typing[id]; }), 4000);
    }

    // Kabari lawan bicara selagi mengetik, paling sering tiap 2,5 detik. Hanya saat WebSocket tersambung (tanpa itu kabarnya tidak sampai).
    sayTyping() {
      if (!this.rt || this.cur == null || Date.now() - (this.typedAt || 0) < 2500) return;
      this.typedAt = Date.now();
      this.req(this.api.typing, {to: this.cur}).catch(() => {});
    }

    uuid() {
      const b = crypto.getRandomValues(new Uint8Array(16)); b[6] = b[6] & 15 | 64; b[8] = b[8] & 63 | 128;
      const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
      return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    // Lampiran dikirim berpotong-potong (api.chunk byte) supaya tidak terbentur batas upload server.
    async upload(file, progress) {
      const uid = this.uuid(), step = +this.api.chunk || 1048576;
      for (let off = 0; off < file.size; off += step) {
        const fd = new FormData(); fd.append('uid', uid); fd.append('offset', off); fd.append('chunk', file.slice(off, off + step), 'chunk');
        await this.req(this.api.upload, fd);
        progress(Math.min(100, Math.round((off + step) / file.size * 100)));
      }
      return uid;
    }

    async send() {
      const inp = $(this.el, '[data-wa-text]'), t = inp.value.trim(), f = this.pending, to = this.cur;
      if (to == null || (!t && !f)) return;
      const c = this.conv(to), m = {me: true, t, ts: Math.floor(Date.now() / 1000), st: this.live ? 'sending' : 'sent'};
      if (f) { m.file = {name: f.file.name, size: f.file.size}; if (f.image) m.img = f.url; }
      c.msgs.push(m); inp.value = ''; this.hidePending(); this.drawList(); this.drawMain();
      this.emit('dl:chat-send', {to, text: t, file: f?.file || null});
      if (!this.live) {
        // Pratinjau: tidak login = 1 centang; login = diterima lalu dibaca.
        if (this.P[to].online) { setTimeout(() => { m.st = 'delivered'; this.drawList(); this.drawMain(true); }, 600); setTimeout(() => { m.st = 'read'; this.drawList(); this.drawMain(true); }, 1500); }
        return;
      }
      try {
        const upload = f ? await this.upload(f.file, pct => { m.up = pct; if (this.cur === to) this.drawMain(true); }) : null;
        const r = await this.req(this.api.send, {to, text: t, upload, name: f?.file.name});
        // lastId tidak dimajukan di sini: pesan lawan ber-id lebih kecil yang belum terambil poll bisa terlewat.
        c.msgs.splice(c.msgs.indexOf(m), 1);
        if (m.img) r.msg.img = m.img;
        this.add(to, r.msg); this.typedAt = 0;
      } catch (e) { m.st = 'failed'; delete m.up; this.fail(e); }
      this.drawList(); if (this.cur === to) this.drawMain(true);
    }

    /* ---------------- bunyi ---------------- */
    audio() {
      const C = window.AudioContext || window.webkitAudioContext;
      try { return C ? (Chat.ctx ||= new C()) : null; } catch (e) { return null; }
    }

    // Dua nada pendek. Bila browser belum mengizinkan bunyi (belum ada sentuhan user), dilewati -- tidak ditumpuk untuk nanti.
    ring() {
      const ctx = this.audio(); if (!ctx) return;
      const asked = Date.now(), play = () => {
        if (Date.now() - asked > 1500) return;
        [[880, 0], [1175, .14]].forEach(([hz, at]) => {
          const o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + at;
          o.type = 'sine'; o.frequency.value = hz;
          g.gain.setValueAtTime(.0001, t); g.gain.exponentialRampToValueAtTime(.3, t + .02); g.gain.exponentialRampToValueAtTime(.0001, t + .4);
          o.connect(g).connect(ctx.destination); o.start(t); o.stop(t + .45);
        });
      };
      ctx.state === 'running' ? play() : ctx.resume().then(play).catch(() => {});
    }
  }
  Devlib.define("Chat", Chat);
})(window.Devlib);
