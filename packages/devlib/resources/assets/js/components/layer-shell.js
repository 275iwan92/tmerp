/* DevLib · LayerShell (turunan Shell) */
// Didaftarkan lewat antrean DevlibReady supaya kelas induk Shell sudah ada (berkas dibundel urut abjad).
(window.DevlibReady ||= []).push(Devlib => {
  'use strict';
  const {$} = Devlib.h;
  /* ============ LAYER SHELL: kerangka Shell + navigasi x-dl::layer-menu, tanpa top bar ============ */
  class LayerShell extends Devlib.classes.Shell {
    initNav() {
      this.lm = $(this.el, '[data-dl-layer-menu]');
      this.lm.addEventListener('dl:layer-menu', e => { e.preventDefault(); this.openTab(e.detail.key); });
      this.lm.addEventListener('dl:layer-menu-action', e => { e.preventDefault(); this.command(e.detail.key); });
      // Laci di layar kecil: tombol menu membuka, scrim / Escape / memilih layar menutup.
      const drawer = on => this.el.classList.toggle('is-lm-open', on);
      $(this.el, '[data-dl-lm-toggle]')?.addEventListener('click', () => drawer(!this.el.classList.contains('is-lm-open')));
      $(this.el, '[data-dl-lm-scrim]')?.addEventListener('click', () => drawer(false));
      this.lm.addEventListener('dl:layer-menu', () => drawer(false));
      document.addEventListener('keydown', e => { if (e.key === 'Escape') drawer(false); });
    }

    // Chat tinggal di Layer Menu (tukar / dok kanan), bukan laci Shell.
    command(a) {
      if (a === 'chat') this.lm.dlComponent.openChat();
      else super.command(a);
    }

    // Layar yang terbuka ikut disorot di Layer Menu (tab aktif = daun terpilih).
    markActiveLeaf() {
      const c = this.lm?.dlComponent; if (!c) return;
      c.sel = this.activeTab; c.draw(false);
    }
  }
  Devlib.register(LayerShell);
});
