/* DevLib · LayerMenu */
(function (Devlib) {
  'use strict';
  const {esc, toast, confirmBox} = Devlib.h;
  const BACK = '<button type="button" class="dl-lm__back" data-lm-back><i class="fa-solid fa-arrow-left"></i><span>Back</span></button>';
  /* ============ LAYER MENU (panel kiri berlapis: modul -> menu -> sub -> ...) + Chat & Profile ============ */
  class LayerMenu extends Devlib.Component {
    static selector = "[data-dl-layer-menu]";
    init() {
      // views = beberapa akar menu (mis. Operasional | Laporan Manajemen) yang ditukar lewat ikon di kepala panel.
      this.views = JSON.parse(this.el.dataset.views || '[]');
      this.view = 0;
      this.root = this.views.length ? this.views[0].items : JSON.parse(this.el.dataset.tree || '[]');
      this.track = this.$('[data-lm-track]');
      this.dots = this.$('[data-lm-dots]');
      this.key = 'dl-lm-' + this.el.dataset.id;
      this.path = [];     // cabang yang sedang dibuka, path[d-1] = induk layer d
      this.sel = null;    // key layar akhir terpilih
      this.st = {chat: false, dock: false, show: 'menu'};   // show = menu | chat | profile (yang tampil di panel kiri)
      this.initViews();
      this.track.addEventListener('click', e => { const t = e.target.closest('button, a'); if (t) this.click(e, t); });
      this.el.addEventListener('keydown', e => {
        if (e.key === 'Escape' && this.pop?.classList.contains('is-on')) { this.togglePop(false); return; }
        if ((e.key === 'Escape' || e.key === 'ArrowLeft') && this.st.show === 'menu' && this.path.length && e.target.closest('[data-lm-track]')) { e.preventDefault(); this.back(); }
      });
      this.initPanels();
      this.restore();
    }

    depthOf(items) { return 1 + Math.max(0, ...items.map(n => n.items ? this.depthOf(n.items) : 0)); }
    count(n) { return n.items ? n.items.reduce((a, x) => a + this.count(x), 0) : 1; }
    nodes(d) { return d ? this.path[d - 1].items : this.root; }

    // Jalur label ke layar akhir berkunci k (dipakai untuk prop active & posisi yang diingat).
    find(k, items = this.root, trail = []) {
      for (const n of items) {
        if (!n.items && n.key === k) return trail;
        if (n.items) { const r = this.find(k, n.items, [...trail, n]); if (r) return r; }
      }
      return null;
    }

    restore() {
      let saved = {};
      try { saved = JSON.parse(localStorage.getItem(this.key)) || {}; } catch (e) {}
      const k = this.el.dataset.active || saved.sel;
      // View awal: prop view > view yang memuat layar aktif > view terakhir dipakai.
      const want = this.el.dataset.view || (k && this.views.find(v => this.find(k, v.items))?.key) || saved.view;
      this.setView(Math.max(0, this.views.findIndex(v => v.key === want)), false);
      const trail = k ? this.find(k) : null;
      if (trail) { this.path = trail; this.sel = k; }
      else (saved.path || []).every(lbl => { const n = this.nodes(this.path.length).find(x => x.items && x.label === lbl); return n && this.path.push(n); });
      this.draw(false);
    }

    save() { try { localStorage.setItem(this.key, JSON.stringify({path: this.path.map(n => n.label), sel: this.sel, view: this.views[this.view]?.key})); } catch (e) {} }

    initViews() {
      const box = this.$('[data-lm-views]');
      if (this.views.length > 1) {
        box.innerHTML = this.views.map((v, i) => `<button type="button" data-lm-view="${i}" title="${esc(v.label)}" aria-label="${esc(v.label)}"><i class="${esc(v.icon)}"></i></button>`).join('');
        box.hidden = false;
        box.addEventListener('click', e => { const b = e.target.closest('[data-lm-view]'); if (b) this.setView(+b.dataset.lmView); });
      }
      this.dots.innerHTML = '<i></i>'.repeat(this.depthOf(this.root));
    }

    // Tukar akar menu: layer kembali ke 1, panel kembali ke menu (bila sedang Chat/Profile).
    setView(i, animate = true) {
      if (!this.views.length) return;
      const changed = i !== this.view;
      this.view = i;
      this.root = this.views[i].items;
      this.$$('[data-lm-view]').forEach(b => { const on = +b.dataset.lmView === i; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', String(on)); });
      this.dots.innerHTML = '<i></i>'.repeat(this.depthOf(this.root));
      if (!changed && !animate) return;
      this.path = [];
      if (this.st.show !== 'menu') this.show('menu');
      this.draw(animate);
      this.emit('dl:layer-menu-view', {key: this.views[i].key});
    }

    // Satu panel per layer. Tanpa animasi saat pertama tampil (restore) supaya tidak "terbang" dari layer 1.
    draw(animate = true) {
      const d = this.path.length;
      while (this.track.children.length > d + 1) this.track.lastChild.remove();
      for (let i = this.track.children.length; i <= d; i++) this.track.insertAdjacentHTML('beforeend', '<div class="dl-lm__pane"></div>');
      [...this.track.children].forEach((p, i) => { p.innerHTML = this.pane(i); p.inert = i !== d; });
      this.slide(this.track, d, animate);
      [...this.dots.children].forEach((x, i) => x.classList.toggle('is-on', i <= d));
      this.save();
    }

    slide(track, d, animate = true) {
      track.classList.toggle('no-anim', !animate);
      track.style.transform = `translateX(${-100 * d}%)`;
      if (!animate) requestAnimationFrame(() => track.classList.remove('no-anim'));
    }

    pane(d) {
      const counts = this.el.dataset.counts === '1';
      let h = '';
      if (d) {
        const p = this.path[d - 1];
        h += BACK + `<div class="dl-lm__title"><i class="${esc(p.icon)}"></i><b>${esc(p.label)}</b></div>`
          + `<div class="dl-lm__crumb">${this.path.slice(0, d).map(n => esc(n.label)).join(' <i class="fa-solid fa-angle-right"></i> ')}</div>`;
      } else h += `<div class="dl-lm__cap">${esc(this.views.length ? this.views[this.view].label : this.el.dataset.root)}</div>`;
      let g = null;
      this.nodes(d).forEach((n, x) => {
        if (n.group && n.group !== g) { g = n.group; h += `<div class="dl-lm__cap">${esc(g)}</div>`; }
        const badge = n.badge ? `<em class="dl-lm__badge is-${esc(n.tone || 'accent')}">${esc(n.badge)}</em>` : '';
        const lbl = `<span class="dl-lm__ico"><i class="${esc(n.icon)}"></i></span><span class="dl-lm__t">${esc(n.label)}</span>${badge}`;
        h += n.items
          ? `<button type="button" class="dl-lm__a" data-lm-x="${x}" title="${esc(n.label)}">${lbl}${counts ? `<span class="dl-lm__n">${this.count(n)}</span>` : ''}<i class="fa-solid fa-chevron-right dl-lm__chev"></i></button>`
          : `<a href="${esc(n.url || '#')}" class="dl-lm__a${n.key === this.sel ? ' is-active' : ''}" data-lm-x="${x}" title="${esc(n.label)}">${lbl}</a>`;
      });
      return h;
    }

    // Buka menu pada cabang berlabel (mis. ['Sales', 'Sales Order'] dari breadcrumb). Label tidak cocok -> cari lewat daun berlabel leaf.
    goTo(labels, leaf = null) {
      if (this.st.show !== 'menu') this.show('menu');
      const path = [];
      let items = this.root;
      for (const l of labels) { const n = items.find(x => x.items && x.label === l); if (!n) break; path.push(n); items = n.items; }
      if (path.length < labels.length && leaf) {
        const hunt = (xs, trail) => { for (const n of xs) { if (!n.items && n.label === leaf) return trail; if (n.items) { const r = hunt(n.items, [...trail, n]); if (r) return r; } } return null; };
        const t = hunt(this.root, []); if (t) path.splice(0, path.length, ...t.slice(0, labels.length));
      }
      this.path = path; this.draw();
      this.track.children[this.path.length]?.querySelector('.dl-lm__a')?.focus({preventScroll: true});
    }

    back() { this.path.pop(); this.draw(); this.track.children[this.path.length]?.querySelector('.dl-lm__a')?.focus({preventScroll: true}); }

    click(e, t) {
      if (t.hasAttribute('data-lm-back')) { this.back(); return; }
      if (!t.hasAttribute('data-lm-x')) return;
      const d = [...this.track.children].indexOf(t.closest('.dl-lm__pane'));
      const n = this.nodes(d)[+t.dataset.lmX];
      if (n.items) { this.path = [...this.path.slice(0, d), n]; this.draw(); this.track.children[d + 1].querySelector('[data-lm-back]').focus({preventScroll: true}); return; }
      if (!n.url || n.url === '#') e.preventDefault();
      this.sel = n.key;
      this.$$('.dl-lm__a.is-active').forEach(a => a.classList.remove('is-active'));
      t.classList.add('is-active');
      this.save();
      const detail = {key: n.key, label: n.label, url: n.url || null, path: [...this.path.map(p => p.label), n.label]};
      if (!this.emit('dl:layer-menu', detail, true)) { e.preventDefault(); return; }
      if (!n.url || n.url === '#') toast(`Buka: ${detail.path.join(' › ')} (pratinjau)`);
    }

    /* ---------------- roda gigi, Chat & Profile ---------------- */
    initPanels() {
      this.pop = this.$('[data-lm-pop]');
      this.chatOv = this.$('[data-lm-ov="chat"]');
      this.profOv = this.$('[data-lm-ov="profile"]');
      this.chatEl = this.$('[data-lm-chathost] [data-dl-chat]');
      this.chatHost = this.$('[data-lm-chathost]');
      if (this.pop) {
        this.$('[data-lm-gear]').addEventListener('click', e => { e.stopPropagation(); this.togglePop(); });
        document.addEventListener('click', e => { if (!this.pop.contains(e.target)) this.togglePop(false); });
        this.pop.addEventListener('click', e => {
          const go = e.target.closest('[data-lm-go]'), act = e.target.closest('[data-lm-act]');
          if (!go && !act) return;
          this.togglePop(false);
          if (go?.dataset.lmGo === 'chat') this.openChat();
          else if (go) this.openProfile();
          else this.action(act);
        });
      }
      this.$('[data-lm-swap]').addEventListener('click', () => this.show(this.st.show === 'chat' ? 'menu' : 'chat'));
      this.$('[data-lm-dockbtn]').addEventListener('click', () => this.setDock(true));
      if (this.chatEl) {
        // Tombol tutup ditempel di bilah judul chat (bukan bilah kedua) supaya chat tidak dobel judul.
        this.chatEl.querySelector('.dl-wa__bar').insertAdjacentHTML('beforeend', '<button type="button" class="dl-wa__ib" data-lm-chatx title="Tutup chat" aria-label="Tutup chat"><i class="fa-solid fa-xmark"></i></button>');
        this.chatEl.addEventListener('click', e => { if (e.target.closest('[data-lm-chatx]')) this.closeChat(); });
        this.unread = this.chatEl.dlChat?.unread || 0;
        this.chatEl.addEventListener('dl:chat-unread', e => { this.unread = e.detail.count; this.badges(); });
      }
      if (this.profOv) {
        this.pages = this.$('[data-lm-pages]');
        this.ptrack = this.$('[data-lm-ptrack]');
        this.pdet = this.$('[data-lm-pdet]');
        const acts = this.$$('[data-lm-act]').filter(a => !this.page(a.dataset.lmAct));
        this.$('[data-lm-plist]').innerHTML = [...this.pages.querySelectorAll(':scope > [data-lm-page]')].map(s =>
          `<button type="button" class="dl-lm__a" data-lm-page-open="${esc(s.dataset.lmPage)}"><span class="dl-lm__ico"><i class="${esc(this.faOf(s.dataset.icon))}"></i></span><span class="dl-lm__t">${esc(s.dataset.label)}</span><i class="fa-solid fa-chevron-right dl-lm__chev"></i></button>`).join('')
          + (acts.length ? '<div class="dl-lm__sep"></div>' + acts.map(a => a.outerHTML).join('') : '');
        this.profOv.addEventListener('click', e => {
          if (e.target.closest('[data-lm-close]')) return this.show(this.st.chat && !this.st.dock ? 'chat' : 'menu');
          const o = e.target.closest('[data-lm-page-open]'); if (o) return this.openPage(o.dataset.lmPageOpen);
          if (e.target.closest('[data-lm-pdet] [data-lm-back]')) return this.closePage();
          const act = e.target.closest('[data-lm-plist] [data-lm-act]'); if (act) this.action(act);
        });
      }
      this.badges();
    }

    faOf(icon) { return !icon ? 'fa-solid fa-circle-dot' : icon.includes(' ') ? icon : 'fa-solid ' + icon; }
    page(k) { return this.pages?.querySelector(`:scope > [data-lm-page="${CSS.escape(k)}"]`) || this.pdet?.querySelector(`[data-lm-page="${CSS.escape(k)}"]`); }

    togglePop(on = !this.pop.classList.contains('is-on')) {
      if (!this.pop) return;
      this.pop.classList.toggle('is-on', on);
      this.$('[data-lm-gear]').setAttribute('aria-expanded', String(on));
    }

    chatVisible() { return this.st.chat && (this.st.dock || this.st.show === 'chat'); }

    show(what) { this.st.show = what; this.apply(); }

    apply() {
      const {chat, dock, show} = this.st;
      this.chatOv?.classList.toggle('is-on', chat && !dock && show === 'chat');
      this.profOv?.classList.toggle('is-on', show === 'profile');
      [...this.track.children].forEach((p, i) => { p.inert = show !== 'menu' || i !== this.path.length; });
      this.$('[data-lm-swap]').hidden = this.$('[data-lm-dockbtn]').hidden = !(chat && !dock);
      this.$('[data-lm-swap]').title = show === 'chat' ? 'Tampilkan menu' : 'Tampilkan chat';
      const mode = {chat: 'Chat', profile: 'Profile'}[show];
      this.dots.hidden = !!mode;
      const m = this.$('[data-lm-mode]'); m.hidden = !mode; m.textContent = mode || '';
      if (this.dockHost) this.dockHost.classList.toggle('is-on', chat && dock);
      this.badges();
      this.emit('dl:layer-menu-panel', {...this.st});
    }

    badges() {
      const u = this.unread || 0, vis = this.chatVisible();
      this.$$('[data-lm-unread]').forEach(b => {
        const n = {pop: u, gear: vis ? 0 : u, swap: this.st.show === 'menu' ? u : 0}[b.dataset.lmUnread] || 0;
        b.hidden = !n; b.textContent = n > 99 ? '99+' : n;
      });
    }

    openChat() { if (!this.chatEl) return; this.st.chat = true; if (!this.st.dock) this.st.show = 'chat'; this.apply(); }

    closeChat() {
      const wasDock = this.st.dock;
      Object.assign(this.st, {chat: false, dock: false, show: this.st.show === 'chat' ? 'menu' : this.st.show});
      this.apply();
      // Balik ke panel kiri setelah animasi dok selesai, supaya dok menutup bersama isinya.
      if (wasDock) setTimeout(() => this.chatHost.appendChild(this.chatEl), 320);
    }

    // Dok kanan = wadah di layout (prop dock) atau laci tetap di tepi kanan layar. Elemen chat yang SAMA dipindah.
    setDock(on) {
      if (on) {
        if (!this.dockHost) {
          this.dockHost = document.querySelector(this.el.dataset.dock || '') || document.body.appendChild(Object.assign(document.createElement('aside'), {className: 'dl-lm-dock is-fixed'}));
          // Bilah dok milik LayerMenu: tetap terlihat walau percakapan sedang terbuka (bilah daftar chat tersembunyi).
          this.dockHost.insertAdjacentHTML('afterbegin', '<div class="dl-lm-dock__bar"><i class="fa-solid fa-comment-dots"></i><b>Chat</b>'
            + '<button type="button" class="dl-lm__ib" data-lm-undock title="Kembalikan ke panel kiri" aria-label="Kembalikan chat ke panel kiri"><i class="fa-solid fa-table-columns"></i></button>'
            + '<button type="button" class="dl-lm__ib" data-lm-dockx title="Tutup chat" aria-label="Tutup chat"><i class="fa-solid fa-xmark"></i></button></div>');
          this.dockHost.addEventListener('click', e => { if (e.target.closest('[data-lm-undock]')) this.setDock(false); else if (e.target.closest('[data-lm-dockx]')) this.closeChat(); });
        }
        this.dockHost.appendChild(this.chatEl);
        Object.assign(this.st, {dock: true, show: this.st.show === 'chat' ? 'menu' : this.st.show});
      } else {
        this.chatHost.appendChild(this.chatEl);
        Object.assign(this.st, {dock: false, show: 'chat'});
      }
      // Memindah elemen mereset posisi gulir: kembalikan ke pesan terakhir.
      const box = this.chatEl.querySelector('[data-wa-msgs]'); if (box) box.scrollTop = box.scrollHeight;
      this.apply();
    }

    openProfile(pageKey = null) {
      if (!this.profOv) return;
      this.closePage(false);
      this.st.show = 'profile'; this.apply();
      if (pageKey) this.openPage(pageKey);
    }

    // Halaman profil = <section data-lm-page> dari slot, DIPINDAH (bukan disalin) supaya isian form tidak hilang.
    openPage(k) {
      const s = this.page(k); if (!s) return;
      this.closePage(false);
      this.pdet.innerHTML = BACK + `<div class="dl-lm__title"><i class="${esc(this.faOf(s.dataset.icon))}"></i><b>${esc(s.dataset.label)}</b></div><div class="dl-lm__crumb">Profile <i class="fa-solid fa-angle-right"></i> ${esc(s.dataset.label)}</div>`;
      this.pdet.appendChild(s);
      this.slide(this.ptrack, 1);
      this.emit('dl:layer-menu-page', {key: k});
    }

    closePage(animate = true) {
      if (!this.pdet) return;
      const s = this.pdet.querySelector('[data-lm-page]'); if (s) this.pages.appendChild(s);
      if (animate) this.slide(this.ptrack, 0); else { this.slide(this.ptrack, 0, false); this.pdet.innerHTML = ''; }
    }

    async action(btn) {
      const k = btn.dataset.lmAct;
      if (this.page(k)) return this.openProfile(k);
      if (btn.dataset.confirm && !await confirmBox({title: btn.dataset.label, message: btn.dataset.confirm, tone: btn.classList.contains('is-danger') ? 'danger' : 'warn', ok: btn.dataset.label})) return;
      if (this.emit('dl:layer-menu-action', {key: k}, true)) toast(`${btn.dataset.label} (pratinjau)`);
    }
  }
  Devlib.define("LayerMenu", LayerMenu);
})(window.Devlib);
