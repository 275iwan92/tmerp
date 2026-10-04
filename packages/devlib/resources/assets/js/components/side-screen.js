/* DevLib · SideScreen (jendela layar penuh yang bisa mengambang, dipindah dan diubah ukurannya; user 2026-10-04)
 * + SideScreenLayout (rel bagian vertikal di dalam isinya). */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;
  const MIN_W = 640, MIN_H = 400, SNAP = 14;

  class SideScreen extends Devlib.Component {
    static selector = '[data-dl-side-screen]';
    init() {
      this.id = this.el.dataset.dlSideScreen;
      this.key = 'dl.ss.' + this.id;
      this.frame = $(this.el, 'iframe.dl-ss__frame');
      this.state = this.load();
      this.el.classList.toggle('is-float', this.state.mode === 'float');
      $(this.el, '[data-dl-ss-float]')?.addEventListener('click', this.toggleFloat);
      $(this.el, '[data-dl-ss-search]')?.addEventListener('input', e => this.search(e.target.value));
      $(this.el, '[data-dl-ss-drag]').addEventListener('pointerdown', this.dragStart);
      $(this.el, '[data-dl-ss-drag]').addEventListener('dblclick', e => { if (!e.target.closest('input,button')) this.toggleFloat(); });
      $$(this.el, '[data-dl-ss-rs]').forEach(h => h.addEventListener('pointerdown', this.resizeStart));
      document.addEventListener('keydown', e => {
        if (!this.isOpen()) return;
        if (e.key === 'Escape') { e.preventDefault(); this.close(); }
        else if (e.key === '/' && !e.target.closest('input,textarea,select,[contenteditable]')) { e.preventDefault(); $(this.el, '[data-dl-ss-search]')?.focus(); }
      });
      // Pesan dari halaman di dalam iframe (Esc di sana, atau minta tutup sesudah Save).
      window.addEventListener('message', e => {
        if (!this.isOpen() || !this.frame || e.source !== this.frame.contentWindow) return;
        if (e.data && e.data.dl === 'ss-close') this.close();
      });
      window.addEventListener('resize', () => { if (this.state.mode === 'float') this.apply(); });
      this.apply();
    }
    isOpen() { return this.el.classList.contains('is-on'); }
    open() {
      if (this.frame && !this.frame.getAttribute('src')) this.frame.src = this.el.dataset.dlSsUrl;
      this.el.classList.add('is-on'); this.apply();
      this.emit('dl:side-screen:open', {id: this.id});
    }
    close() { this.el.classList.remove('is-on'); this.emit('dl:side-screen:close', {id: this.id}); }
    toggle() { this.isOpen() ? this.close() : this.open(); }

    /* ---- keadaan: full (bawaan) / float {x, y, w, h}; diingat per browser ---- */
    load() {
      const [w, h] = (this.el.dataset.dlSsSize || '1140px 680px').split(' ').map(parseFloat);
      let s = {mode: 'full', w, h, x: null, y: null};
      try { Object.assign(s, JSON.parse(localStorage.getItem(this.key) || '{}')); } catch (e) {}
      return s;
    }
    save() { try { localStorage.setItem(this.key, JSON.stringify(this.state)); } catch (e) {} }
    toggleFloat() {
      this.state.mode = this.state.mode === 'float' ? 'full' : 'float';
      this.el.classList.toggle('is-float', this.state.mode === 'float');
      const b = $(this.el, '[data-dl-ss-float] i'); if (b) b.className = this.state.mode === 'float' ? 'fa-solid fa-expand' : 'fa-regular fa-window-restore';
      this.apply(); this.save();
    }
    // Jendela mengambang selalu di dalam viewport; x/y kosong = di tengah.
    apply() {
      const s = this.state, st = this.el.style;
      if (s.mode !== 'float') { st.cssText = ''; return; }
      const vw = window.innerWidth, vh = window.innerHeight;
      s.w = Math.max(MIN_W, Math.min(s.w, vw)); s.h = Math.max(MIN_H, Math.min(s.h, vh));
      if (s.x === null || s.y === null) { s.x = Math.round((vw - s.w) / 2); s.y = Math.round((vh - s.h) / 2); }
      s.x = Math.max(0, Math.min(s.x, vw - s.w)); s.y = Math.max(0, Math.min(s.y, vh - s.h));
      st.left = s.x + 'px'; st.top = s.y + 'px'; st.width = s.w + 'px'; st.height = s.h + 'px';
    }
    snap(v, edge) { return Math.abs(v - edge) <= SNAP ? edge : v; }

    /* ---- seret kepala (hanya saat mengambang) ---- */
    dragStart(e) {
      if (this.state.mode !== 'float' || e.button !== 0 || e.target.closest('input,button,a,kbd')) return;
      e.preventDefault();
      const s = this.state, ox = e.clientX - s.x, oy = e.clientY - s.y, vw = window.innerWidth, vh = window.innerHeight;
      this.el.classList.add('is-moving'); this.shield(true);
      const move = ev => { s.x = this.snap(this.snap(ev.clientX - ox, 0), vw - s.w); s.y = this.snap(this.snap(ev.clientY - oy, 0), vh - s.h); this.apply(); };
      const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); this.el.classList.remove('is-moving'); this.shield(false); this.save(); };
      window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
    }
    /* ---- tarik tepi / sudut ---- */
    resizeStart(e) {
      if (this.state.mode !== 'float' || e.button !== 0) return;
      e.preventDefault();
      const dir = e.currentTarget.dataset.dlSsRs, s = this.state, o = {...s, mx: e.clientX, my: e.clientY}, vw = window.innerWidth, vh = window.innerHeight;
      this.el.classList.add('is-resizing'); this.shield(true); this.sizeTag(true);
      const move = ev => {
        const dx = ev.clientX - o.mx, dy = ev.clientY - o.my;
        if (dir.includes('e')) s.w = Math.min(vw - o.x, Math.max(MIN_W, o.w + dx));
        if (dir.includes('s')) s.h = Math.min(vh - o.y, Math.max(MIN_H, o.h + dy));
        if (dir.includes('w')) { const w = Math.max(MIN_W, Math.min(o.w - dx, o.x + o.w)); s.x = o.x + o.w - w; s.w = w; }
        if (dir.includes('n')) { const h = Math.max(MIN_H, Math.min(o.h - dy, o.y + o.h)); s.y = o.y + o.h - h; s.h = h; }
        this.apply(); this.sizeTag(true);
      };
      const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); this.el.classList.remove('is-resizing'); this.shield(false); this.sizeTag(false); this.save(); };
      window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
    }
    // iframe menelan event pointer saat diseret: tutup sementara dengan lapisan transparan.
    shield(on) { this.el.classList.toggle('is-shield', on); }
    sizeTag(on) {
      let t = $(this.el, '.dl-ss__size');
      if (!on) { t && t.remove(); return; }
      if (!t) { t = document.createElement('span'); t.className = 'dl-ss__size'; this.el.appendChild(t); }
      t.textContent = Math.round(this.state.w) + ' × ' + Math.round(this.state.h);
    }
    /* ---- cari: diteruskan ke layout di dalam iframe / di slot ---- */
    search(q) {
      if (this.frame) this.frame.contentWindow?.postMessage({dl: 'ss-search', q}, '*');
      else $$(this.el, '[data-dl-ss-layout]').forEach(l => l.dlComponent?.filter(q));
    }
  }
  Devlib.define('SideScreen', SideScreen);

  class SideScreenLayout extends Devlib.Component {
    static selector = '[data-dl-ss-layout]';
    init() {
      this.on('click', '[data-dl-ss-nav]', (e, b) => this.show(b.dataset.dlSsNav));
      window.addEventListener('message', e => { if (e.data && e.data.dl === 'ss-search') this.filter(e.data.q); });
      // Esc di dalam iframe = tutup jendela induk (Side Screen mendengarkan pesan ini).
      document.addEventListener('keydown', e => { if (e.key === 'Escape' && window.name === 'dl-wide' && window.parent !== window) window.parent.postMessage({dl: 'ss-close'}, '*'); });
      if (window.ResizeObserver) new ResizeObserver(() => this.el.classList.toggle('is-narrow', this.el.clientWidth < 900)).observe(this.el);
    }
    show(key) {
      $$(this.el, '[data-dl-ss-nav]').forEach(b => b.classList.toggle('is-on', b.dataset.dlSsNav === key));
      $$(this.el, '[data-dl-ss-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.dlSsPanel === key));
      const b = $(this.el, `[data-dl-ss-nav="${key}"]`), p = $(this.el, `[data-dl-ss-panel="${key}"]`);
      if (b) { $(this.el, '[data-dl-ss-sec-title]').textContent = b.dataset.label; const i = $(this.el, '[data-dl-ss-sec-icon]'); if (i) i.className = 'fa-solid ' + (b.dataset.icon || 'fa-circle'); }
      if (p) $(this.el, '[data-dl-ss-sec-hint]').textContent = p.dataset.hint || '';
      $(this.el, '[data-dl-ss-body]').scrollTop = 0;
      try { const u = new URL(location.href); u.searchParams.set(this.el.dataset.dlSsParam || 'tab', key); history.replaceState(null, '', u); } catch (e) {}
      this.emit('dl:side-screen:section', {key});
    }
    // Saring: rel = bagian yang cocok namanya; isi = kartu (.dl-card / [data-ss-item]) di bagian aktif yang memuat teks itu.
    filter(q) {
      q = (q || '').trim().toLowerCase();
      $$(this.el, '[data-dl-ss-nav]').forEach(b => b.classList.toggle('is-dim', !!q && !b.dataset.label.toLowerCase().includes(q)));
      $$(this.el, '[data-dl-ss-panel].is-active .dl-card, [data-dl-ss-panel].is-active [data-ss-item]').forEach(c => c.classList.toggle('is-hidden', !!q && !c.textContent.toLowerCase().includes(q)));
    }
  }
  Devlib.define('SideScreenLayout', SideScreenLayout);
})(window.Devlib);
