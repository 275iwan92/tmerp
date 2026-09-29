/* MockupParts · Sales · InquiryDoc — induk layar dokumen Inquiry (Baru / Revisi / Rekalkulasi).
   Isi bersama: pesan hasil (flash), status, nomor dokumen, form terkunci. Turunan menambah aturan khasnya.
   File bernama _*.js supaya termuat sebelum turunannya (bundel diurutkan per nama). */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class InquiryDoc extends Devlib.Component {
    init() {
      this.cfg = JSON.parse(this.el.dataset.config || '{}');
      this.doc = this.cfg.doc || {};
    }

    input(name) { return $(this.el, `[name="${name}"]`); }
    value(name) { return (this.input(name)?.value || '').trim(); }

    flash(msg, tone = 'success') {
      const a = $(this.el, '[data-inq-flash]');
      a.className = `dl-alert dl-alert--${tone} is-show`;
      a.textContent = (tone === 'success' ? '✅ ' : '⚠️ ') + msg;
      a.scrollIntoView({behavior: 'smooth', block: 'center'});
    }

    state(s) {
      this.doc.sub_state = s;
      $$(this.el, '[data-inq-sub], [data-inq-state]').forEach(x => { x.textContent = s; });
    }

    // Nomor IQ[YY][NNNNN] diberikan saat pertama disimpan (tanpa database: nomor contoh).
    number() {
      if (!this.doc.inquiry_no) this.doc.inquiry_no = 'IQ' + this.cfg.yy + String(Math.floor(Math.random() * 90000) + 10000);
      this.doc.id ||= 'baru';
      $$(this.el, '[data-inq-no]').forEach(x => { x.textContent = this.doc.inquiry_no; });
    }

    // Form terkunci (lihat-saja): isian & tombol mati kecuali data-inq-keep; tombol tambah/hapus baris disembunyikan.
    lockAll() {
      // Alat tampilan dieline (tab sisi, zoom, satuan, opsi gambar) hanya mengubah tampilan -> tetap aktif.
      $$(this.el, 'input, select, textarea, button').forEach(x => { if (!x.closest('[data-inq-keep], .dl-mov, .dl-dieline__tools, .dl-dieline__opts')) x.disabled = true; });
      $$(this.el, '[data-inq-officer]').forEach(a => a.classList.remove('is-show'));
      $$(this.el, '[data-l-add], [data-l-del], [data-l-dup]').forEach(b => { b.hidden = true; });
    }
  }
  Devlib.classes.InquiryDoc = InquiryDoc;
})(window.Devlib);
