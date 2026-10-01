/* MockupParts · Sales · InquiryWorklist (turunan Grid DevLib: hanya method yang berbeda) */
(window.DevlibReady ||= []).push(Devlib => {
  class InquiryWorklist extends Devlib.classes.Grid {
    init() {
      // data-open='{"NEW":"/s/x?id={id}", ...}' per jenis, atau {"*": ...} untuk semua (pemilih inquiry sumber).
      // Diisi sebelum super.init(): induk langsung menggambar baris (actionsCell membacanya).
      this.open = JSON.parse(this.el.dataset.open || '{}');
      super.init();
      this.el.addEventListener('dl:grid-open', e => {
        const r = this.rows.find(v => String(v[this.cfg.rowKey]) === String(e.detail.id)), url = r && (this.open[r.inquiry_type] || this.open['*']);
        if (!url) return;
        e.preventDefault();
        location.href = url.replace('{id}', encodeURIComponent(r.id)) + (this.open['*'] || r.sub_state === 'DRAFT' ? '' : '&view=1');
      });
      // + New Record = wizard jenis inquiry (modal), bukan pindah layar.
      this.el.addEventListener('dl:grid-new', e => {
        const m = document.querySelector(`[data-dl-modal-id="${this.el.dataset.newModal}"]`);
        if (!m) return;
        e.preventDefault();
        m.classList.add('is-on');
      });
    }
  }
  Devlib.register(InquiryWorklist);

  // Wizard Create Inquiry: tombol langkah berikut/Back menutup modal tempatnya berada (core membuka tujuannya).
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-inq-next]');
    if (b) b.closest('[data-dl-modal-id]')?.classList.remove('is-on');
  }, true);
});
