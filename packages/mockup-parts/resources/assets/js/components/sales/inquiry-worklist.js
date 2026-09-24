/* MockupParts · Sales · InquiryWorklist (turunan Grid DevLib: hanya method yang berbeda) */
(window.DevlibReady ||= []).push(Devlib => {
  const {esc} = Devlib.h;
  class InquiryWorklist extends Devlib.classes.Grid {
    init() {
      // data-open='{"NEW":"/s/x?id={id}", ...}' per jenis, atau {"*": ...} untuk semua (pemilih produk existing).
      // Diisi sebelum super.init(): induk langsung menggambar baris (actionsCell membacanya).
      this.open = JSON.parse(this.el.dataset.open || '{}');
      super.init();
      this.el.addEventListener('dl:grid-open', e => {
        const r = this.rows.find(v => String(v[this.cfg.rowKey]) === String(e.detail.id)), url = r && (this.open[r.inquiry_type] || this.open['*']);
        if (!url) return;
        e.preventDefault();
        location.href = url.replace('{id}', encodeURIComponent(r.id)) + (this.open['*'] || r.sub_state === 'DRAFT' ? '' : '&view=1');
      });
      // Tombol "Pilih data tersimpan" di kartu jenis: pindah ke tab jenis itu.
      if (this.cfg.tabs.length) document.addEventListener('click', e => {
        const b = e.target.closest('[data-inq-tab]'); if (!b) return;
        const i = this.cfg.tabs.findIndex(t => t.where && t.where.inquiry_type === b.dataset.inqTab);
        if (i < 0) return;
        Object.assign(this.st, {tab: i, page: 1});
        this.redraw();
        this.el.scrollIntoView({behavior: 'smooth', block: 'start'});
      });
    }
    // DRAFT = edit (pensil), selain itu lihat-saja (panah). Tanpa Confirm: confirm dilakukan di dalam form.
    actionsCell(r) {
      const draft = r.sub_state === 'DRAFT', pick = !!this.open['*'];
      return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="open" data-id="${esc(r[this.cfg.rowKey])}" title="${pick ? 'Pilih produk ini' : draft ? 'Buka untuk diedit' : 'Buka (lihat-saja)'} — klik 2x baris"><i class="fa-solid ${pick ? 'fa-arrow-right' : draft ? 'fa-pen' : 'fa-up-right-from-square'}"></i></button></td>`;
    }
  }
  Devlib.register(InquiryWorklist);
});
