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
      // Breadcrumb di dalam layar (iframe, origin sama) -> buka menu pada modul / grup itu.
      window.addEventListener('message', e => {
        if (e.origin === location.origin && e.data?.dl === 'menu') this.lm.dlComponent?.goTo(e.data.path || [], e.data.leaf);
      });
      // Kembali ke halaman profil tertentu setelah kirim form dari sana (data-dl-profile-page, mis. tiket bantuan).
      const p = this.el.dataset.dlProfilePage; if (p) this.lm.dlComponent?.openProfile(p);
    }

    // Bantuan = halaman profil "help" bila ada (Back kembali ke Profile), selain itu tab Bantuan Shell.
    hasPage(k) { return !!this.lm.dlComponent?.page(k); }

    openPage(k) { this.hasPage(k) ? this.lm.dlComponent.openProfile(k) : this.command('help'); }

    // Chat tinggal di Layer Menu (tukar / dok kanan), bukan laci Shell.
    command(a) {
      if (a === 'chat') this.lm.dlComponent.openChat();
      else if (a === 'help' && this.hasPage('help')) this.lm.dlComponent.openProfile('help');
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
