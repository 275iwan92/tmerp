/* MockupParts · Sales · InquiryWorklist (turunan Grid DevLib: hanya method yang berbeda) */
(window.DevlibReady ||= []).push(Devlib => {
  const {esc} = Devlib.h;
  // Tombol Continue Draft / Ready to Quote (di luar grid) = saringan tambahan, klik lagi = lepas.
  const PRESETS = {draft: {sub_state: 'DRAFT'}, ready: {ready: true}};

  class InquiryWorklist extends Devlib.classes.Grid {
    init() {
      // data-open='{"NEW":"/s/x?id={id}", ...}' per jenis, atau {"*": ...} untuk semua (pemilih inquiry sumber).
      // Diisi sebelum super.init(): induk langsung menggambar baris (actionsCell membacanya).
      this.open = JSON.parse(this.el.dataset.open || '{}');
      this.preset = null;
      super.init();
      this.el.addEventListener('dl:grid-open', e => {
        const r = this.rows.find(v => String(v[this.cfg.rowKey]) === String(e.detail.id)), url = r && (this.open[r.inquiry_type] || this.open['*']);
        if (!url) return;
        e.preventDefault();
        location.href = url.replace('{id}', encodeURIComponent(r.id)) + (this.open['*'] || r.sub_state === 'DRAFT' ? '' : '&view=1');
      });
      if (this.open['*']) return;
      document.addEventListener('click', e => {
        const p = e.target.closest('[data-inq-preset]');
        if (p) {
          this.preset = this.preset === p.dataset.inqPreset ? null : p.dataset.inqPreset;
          document.querySelectorAll('[data-inq-preset]').forEach(b => b.classList.toggle('is-on', b.dataset.inqPreset === this.preset));
          Object.assign(this.st, {page: 1});
          this.redraw();
          return;
        }
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
    filtered(opts = {}) {
      const rows = super.filtered(opts);
      return this.preset ? rows.filter(r => this.match(r, PRESETS[this.preset])) : rows;
    }
    // DRAFT = edit (pensil), selain itu lihat-saja (panah); + Duplicate ke customer lain. Confirm dilakukan di dalam form.
    actionsCell(r) {
      const draft = r.sub_state === 'DRAFT', pick = !!this.open['*'], id = esc(r[this.cfg.rowKey]);
      const dup = pick ? '' : `<button type="button" class="dl-iconbtn" data-inq-dup="${id}" data-dl-open="dup-modal" title="Duplicate ke customer lain"><i class="fa-solid fa-copy"></i></button>`;
      return `<td class="dl-grid__act"><button type="button" class="dl-iconbtn" data-g-act="open" data-id="${id}" title="${pick ? 'Pilih inquiry ini' : draft ? 'Buka untuk diedit' : 'Buka (lihat-saja)'} — klik 2x baris"><i class="fa-solid ${pick ? 'fa-arrow-right' : draft ? 'fa-pen' : 'fa-up-right-from-square'}"></i></button>${dup}</td>`;
    }
  }
  Devlib.register(InquiryWorklist);

  // Wizard Create Inquiry: tombol langkah berikut/Back menutup modal tempatnya berada (core membuka tujuannya).
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-inq-next]');
    if (b) b.closest('[data-dl-modal-id]')?.classList.remove('is-on');
  }, true);
});
