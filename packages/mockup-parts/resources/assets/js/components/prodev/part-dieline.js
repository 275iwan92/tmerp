/* MockupParts · Prodev · PartDieline — panel Part & Dieline di form ProDev (x-mp::prodev.part-dieline).
   Simpan = kirim form biasa (route v2.prodev.dieline); di sini hanya pratinjau artwork yang baru dipilih
   dan gambar ulang dieline saat tab-nya dibuka. */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class PartDieline extends Devlib.Component {
    static selector = '[data-mp-prodev-dieline]';

    init() {
      this.urls = {};
      this.el.addEventListener('dl:upload', e => {
        const inner = !!e.target.closest('[data-pd-artwork-inner]');
        if (!inner && !e.target.closest('[data-pd-artwork]')) return;
        this.preview(e.detail.files[0], inner);
      });
      // Panel berada di tab yang tersembunyi saat halaman dimuat: ukuran SVG baru bisa diukur setelah tab-nya tampil.
      const panel = this.el.closest('.dl-tabs__panel');
      const tab = panel && $(panel.closest('[data-dl-tabs]'), `.dl-tabs__tab[data-tab="${panel.dataset.panel}"]`);
      if (tab) tab.addEventListener('click', () => setTimeout(() => this.dieline()?.setDims({}), 0));
    }

    dieline() { return $(this.el, '[data-dl-dieline-x]')?.dlDielineX; }

    // File gambar baru -> langsung tampil transparan di dieline & kotak 3D; dihapus -> kembali ke artwork tersimpan.
    preview(file, inner) {
      const k = inner ? 'in' : 'out', d = this.dieline();
      if (this.urls[k]) URL.revokeObjectURL(this.urls[k]);
      this.urls[k] = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
      if (d) inner ? d.setArtworkInner(this.urls[k]) : d.setArtwork(this.urls[k]);
    }
  }
  Devlib.define('ProdevPartDieline', PartDieline);
})(window.Devlib);
