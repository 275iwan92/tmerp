/* MockupParts · Sales · InquiryWorklist (turunan Grid DevLib: hanya method yang berbeda) */
(window.DevlibReady ||= []).push(Devlib => {
  const {esc} = Devlib.h;

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
      if (this.open['*']) return;
      document.addEventListener('click', e => {
        const d = e.target.closest('[data-inq-dup]');
        if (d) {
          const r = this.rows.find(v => String(v.id) === d.dataset.inqDup);
          const m = document.getElementById('dup-form');
          if (!r || !m) return;
          m.querySelector('[data-dup-source]').value = r.id;
          m.querySelector('[data-dup-label]').textContent = `${r.inquiry_no || '(draft)'} — ${r.product_draft_name} · customer asal: ${r.customer}`;
        }
      });
    }
    // Sel aksi = baku Grid Transactional (Open); worklist utama menambah Duplicate ke customer lain.
    actionsCell(r) {
      const cell = super.actionsCell(r);
      if (this.open['*']) return cell;
      return cell.replace('</td>', `<button type="button" class="dl-iconbtn" data-inq-dup="${esc(r[this.cfg.rowKey])}" data-dl-open="dup-modal" title="Duplicate ke customer lain"><i class="fa-solid fa-copy"></i></button></td>`);
    }
  }
  Devlib.register(InquiryWorklist);

  // Wizard Create Inquiry: tombol langkah berikut/Back menutup modal tempatnya berada (core membuka tujuannya).
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-inq-next]');
    if (b) b.closest('[data-dl-modal-id]')?.classList.remove('is-on');
  }, true);
});
