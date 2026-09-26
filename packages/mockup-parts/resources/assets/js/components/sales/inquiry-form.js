/* MockupParts · Sales · InquiryForm — aturan layar Inquiry Product (sama dengan tmerp pages/inquiry/form.blade.php).
   Tanpa database: Simpan/Confirm hanya mengubah keadaan di layar (nomor, status, kunci form). */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, openLayer, closeLayer, setField, fieldErr} = Devlib.h;
  const InquiryDoc = Devlib.classes.InquiryDoc;
  const CATEGORY = {BOX_KOSMETIK: 'BOX_WITH_LID', BOX_SEPATU: 'BOX_WITH_LID', BOX_KUE: 'SHALLOW_BOX', BOX_MAKANAN: 'CARD_BOX', BOX_FARMASI: 'STE_BOX',
    BOX_SOUVENIR: 'GIFT_BOX', BOX_SILINDER: 'ROUND_BOX', HANG_TAG: 'HANG_TAG'};

  class InquiryForm extends InquiryDoc {
    static selector = '[data-mp-inquiry]';

    init() {
      super.init();
      this.tpl = $(this.el, 'template[data-inq-part-tpl]');
      this.seq = this.parts().length;
      this.type = this.input('type_code')?.value || '';
      // Kategori Produk -> Tipe Produk dari master Lookup Product Market (layar mengirim JSON); cadangan = daftar lama.
      try { this.categories = JSON.parse($(this.el, 'script[data-inq-categories]')?.textContent || 'null') || CATEGORY; } catch (err) { this.categories = CATEGORY; }
      this.on('change', null, e => this.changed(e));
      this.on('click', null, e => this.clicked(e));
      this.on('input', '[data-inq-name] input', (e, t) => { this.title(t.closest('[data-inq-part]')); });
      this.el.addEventListener('dl:lookup-create', e => { if (e.target.closest('[data-dl-field="customer_id"]')) { setField(this.el, 'new_customer_name', e.detail.text); openLayer('inq-newcust'); } });
      this.parts().forEach(p => this.gate(p));
      this.template();
      this.custMaterial();
      this.removable();
      if (this.cfg.readonly || this.doc.stat_confirm) this.lock();
    }

    parts() { return $$(this.el, '[data-inq-part]'); }
    needsRole(code) { return !!this.cfg.templates[code]; }

    changed(e) {
      const n = e.target.name || '';
      if (n === 'type_code') return this.setType(e.target.value);
      if (n === 'product_category' && this.categories[e.target.value]) { const t = this.categories[e.target.value]; if (!this.parts().length) this.addPart(); setField(this.el, 'type_code', t); return this.setType(t); }
      if (n === 'material_source') return this.custMaterial();
      if (n.endsWith('[cfg_color]')) return this.derived(e.target.closest('[data-inq-part]'), e.target.value);
    }

    clicked(e) {
      const x = e.target, part = x.closest('[data-inq-part]');
      if (x.closest('[data-inq-save]')) return this.save();
      if (x.closest('[data-inq-confirm]')) return this.confirm();
      if (x.closest('[data-inq-add-part]')) return this.addPart();
      if (x.closest('[data-inq-template]')) return this.applyTemplate();
      if (x.closest('[data-inq-remove]')) return confirmBox({message: 'Hapus part ini?', tone: 'danger'}).then(ok => { if (ok) { part.remove(); this.removable(); this.template(); } });
      if (x.closest('[data-inq-role-open]')) { this.rolePart = part; return openLayer('inq-role'); }
      const pick = x.closest('[data-inq-role-pick]');
      if (pick && this.rolePart) { this.applyRole(this.rolePart, pick.dataset.inqRolePick); closeLayer(pick.closest('.dl-mov')); this.template(); return; }
      const end = x.closest('[data-inq-end]'); if (end) return this.end(end.dataset.inqEnd, end.closest('.dl-mov'));
      if (x.closest('[data-inq-send]')) return this.send(part);
      if (x.closest('[data-inq-newcust-save]')) return this.newCustomer(x.closest('.dl-mov'));
    }

    // Tipe Produk level Inquiry berlaku untuk SEMUA part (setSharedProductType tmerp): tipe tunggal -> peran MonoForm otomatis,
    // tipe multi-part -> peran dikosongkan dulu (part yang sudah tersimpan tetap tampil apa adanya).
    setType(code) {
      this.type = code;
      this.parts().forEach(p => {
        const d = $(p, '[data-dl-dieline-x]'); if (code && d && d.dlDielineX) d.dlDielineX.setType(code);
        if (code && !this.needsRole(code)) this.applyRole(p, 'monoform'); else if (!p.dataset.saved) { $(p, '[data-inq-role]').value = ''; p.dataset.role = ''; }
        this.gate(p);
      });
      this.template();
    }

    gate(p) {
      const role = p.dataset.role, needRole = this.type && this.needsRole(this.type) && !role && !p.dataset.saved;
      $(p, '[data-inq-gate="type"]').classList.toggle('is-show', !this.type);
      $(p, '[data-inq-gate="role"]').classList.toggle('is-show', !!needRole);
      const body = $(p, '[data-inq-body]'), show = !!this.type && !needRole, was = !body.hidden;
      body.hidden = !show;
      if (show && !was) { const d = $(p, '[data-dl-dieline-x]'); if (d && d.dlDielineX) d.dlDielineX.redraw(); }
    }

    // Nama part = {nama-produk}-{peran}, unik; beku setelah dibuat (applyRoleToPart tmerp).
    applyRole(p, role) {
      const slug = this.value('product_draft_name').toLowerCase().replace(/[^a-z0-9]/g, '') || 'produk', base = `${slug}-${role}`;
      const taken = this.parts().filter(x => x !== p).map(x => $(x, '[data-inq-name] input').value);
      let name = base, n = 2; while (taken.includes(name)) name = `${base}-${n++}`;
      $(p, '[data-inq-role]').value = role; p.dataset.role = role;
      const inp = $(p, '[data-inq-name] input'); inp.value = name; inp.dispatchEvent(new Event('input', {bubbles: true}));
      this.gate(p);
    }

    title(p) { $(p, '[data-inq-part-title]').textContent = $(p, '[data-inq-name] input').value || 'Part ' + (this.parts().indexOf(p) + 1); }

    // Saran "Tipe ini biasanya N part: Body + Lid" + Tambahkan Otomatis (partTemplateSuggestion tmerp).
    template() {
      const box = $(this.el, '[data-inq-template-note]'); if (!box) return;
      const tpl = this.cfg.templates[this.type], have = this.parts().map(p => p.dataset.role).filter(Boolean);
      const missing = tpl ? tpl.filter(r => !have.includes(r)) : [];
      box.classList.toggle('is-show', missing.length > 0);
      if (missing.length) $(box, '[data-inq-template-text]').innerHTML = `🧩 Tipe ini biasanya ${tpl.length} part: <b>${esc(missing.map(r => this.cfg.roles[r]).join(' + '))}</b>.`;
    }

    applyTemplate() {
      const tpl = this.cfg.templates[this.type] || [], have = this.parts().map(p => p.dataset.role).filter(Boolean);
      const missing = tpl.filter(r => !have.includes(r)), first = this.parts().find(p => !p.dataset.role);
      if (first && missing.length) this.applyRole(first, missing.shift());
      missing.forEach(r => this.applyRole(this.addPart(), r));
      this.template();
    }

    addPart() {
      const box = document.createElement('div');
      box.innerHTML = this.tpl.innerHTML.replaceAll('__I__', String(this.seq++)).trim();
      const p = box.firstElementChild;
      $(this.el, '[data-inq-parts]').appendChild(p);
      Devlib.mount(p);
      const d = $(p, '[data-dl-dieline-x]'); if (this.type && d && d.dlDielineX) d.dlDielineX.setType(this.type);
      if (this.type && !this.needsRole(this.type)) this.applyRole(p, 'monoform'); else this.gate(p);
      this.title(p); this.removable();
      return p;
    }

    removable() { const ps = this.parts(); ps.forEach(p => { $(p, '[data-inq-remove]').hidden = ps.length <= 1; }); }

    derived(p, code) {
      const m = /^(\d+)\/(\d+)$/.exec(code || ''), box = $(p, '[data-inq-derived] .dl-display');
      box.textContent = m ? `front: ${m[1]} · back: ${m[2]} · total: ${+m[1] + +m[2]} · side: ${+m[2] > 0 ? '2SIDE' : '1SIDE'}` : '—';
    }

    // Sumber Bahan: dicentang = bahan customer -> order JASA, wajib isi spesifikasi bahan customer.
    custMaterial() {
      const c = this.input('material_source'), on = !!(c && c.checked), blk = $(this.el, '[data-inq-custmat]');
      if (blk) blk.hidden = !on;
      const hint = c && $(c.closest('.dl-field'), '.dl-field__hint');
      if (hint) hint.textContent = on ? 'Dicentang → bahan dari customer, order bersifat JASA (cuma ongkos cetak/finishing).' : 'Tidak dicentang → bahan dari perusahaan, order bersifat PRODUCT.';
    }

    err(name, msg) { const el = this.input(name); if (el) fieldErr(el, msg); return msg ? 1 : 0; }

    // headerRules() + assertTierInvariant() tmerp.
    validate() {
      let bad = 0;
      const name = this.value('product_draft_name'), date = this.value('inquiry_date'), dl = this.value('deadline_quotation');
      bad += this.err('inquiry_date', date ? '' : 'Tanggal wajib diisi.');
      bad += this.err('source', this.value('source') ? '' : 'Sumber wajib dipilih.');
      bad += this.err('customer_id', this.value('customer_id') ? '' : 'Customer wajib dipilih.');
      bad += this.err('product_draft_name', !name ? 'Nama Produk wajib diisi.' : /[/@#%"']/.test(name) ? 'Karakter khusus (/@#%"\') tidak diperbolehkan.' : '');
      bad += this.err('deadline_quotation', dl && date && dl < date ? 'Tgl Kebutuhan Pelanggan tidak boleh sebelum Tanggal.' : '');
      bad += this.err('cust_material_spec', this.input('material_source')?.checked && !this.value('cust_material_spec') ? 'Wajib diisi bila bahan dari customer.' : '');
      const tl = $(this.el, '[data-dl-lines][data-name="tiers"]'), tiers = tl && tl.dlLines ? tl.dlLines.rows : [];
      const prim = tiers.filter(t => t.is_primary).length, zero = tiers.findIndex(t => !(+String(t.qty).replace(/\./g, '') > 0));
      const tmsg = !tiers.length ? 'Minimal 1 baris tier qty diperlukan.' : prim !== 1 ? `Harus tepat 1 tier UTAMA (saat ini: ${prim}).` : zero >= 0 ? `Qty tier baris ${zero + 1} wajib diisi angka lebih besar dari 0.` : '';
      const ta = $(this.el, '[data-inq-tier-err]'); ta.textContent = tmsg; ta.classList.toggle('is-show', !!tmsg); bad += tmsg ? 1 : 0;
      if (bad) { $(this.el, '.dl-field.is-invalid, [data-inq-tier-err].is-show')?.scrollIntoView({behavior: 'smooth', block: 'center'}); toast(`${bad} isian belum benar.`, 'danger'); }
      return !bad;
    }

    persist() {
      if (!this.validate()) return false;
      this.number();
      $$(this.el, '[data-inq-saved-only]').forEach(b => { b.hidden = false; });
      this.parts().forEach(p => { p.dataset.saved = '1'; });
      return true;
    }

    save() { if (this.persist()) this.flash(`Tersimpan sebagai ${this.doc.inquiry_no} (${this.doc.sub_state}).`); }

    confirm() {
      if (!this.persist()) return;
      this.doc.stat_confirm = true; this.state('SUBMITTED'); this.lock();
      this.flash(`Inkuiri ${this.doc.inquiry_no} dikonfirmasi — status SUBMITTED, form terkunci.`);
    }

    // Form terkunci (lihat-saja): semua isian & tombol mati kecuali yang bertanda data-inq-keep.
    lock() {
      this.lockAll();
      const s = this.doc.sub_state, done = ['CANCELLED', 'CONVERTED', 'LOST'].includes(s);
      $(this.el, '[data-inq-confirm]').hidden = true;
      $(this.el, '[data-inq-when="cancel"]').hidden = !!this.doc.stat_cancel || done;
      $(this.el, '[data-inq-when="lost"]').hidden = done;
      this.parts().forEach(p => { $(p, '[data-inq-send]').hidden = !p.dataset.saved; $(p, '[data-inq-remove]').hidden = true; });
      $$(this.el, '[data-inq-add-part]').forEach(b => { b.hidden = true; });
    }

    end(state, modal) {
      const f = state === 'CANCELLED' ? 'cancel_reason' : 'lost_reason', inp = $(modal, `[name="${f}"]`);
      if (!inp.value.trim()) return fieldErr(inp, 'Wajib diisi — tercatat di riwayat.');
      fieldErr(inp, ''); closeLayer(modal);
      if (state === 'CANCELLED') this.doc.stat_cancel = true;
      this.state(state); this.lock();
      this.flash(state === 'CANCELLED' ? `Inkuiri ${this.doc.inquiry_no} dibatalkan.` : `Inkuiri ${this.doc.inquiry_no} ditandai kalah (LOST).`);
    }

    // Mode #1: part dikirim ke ProDev (seed prod_part) — wajib tersimpan & inkuiri sudah Confirm (sendToProDev tmerp).
    send(p) {
      if (!p.dataset.saved) return this.flash('Simpan inquiry dulu (tombol Simpan) sebelum kirim part ke ProDev.', 'warn');
      if (!this.doc.stat_confirm) return this.flash('Confirm inquiry ini dulu sebelum kirim ke ProDev (spec harus FIX).', 'warn');
      toast(`Part ${$(p, '[data-inq-name] input').value} dikirim ke ProDev.`, 'success');
      if (this.cfg.prodevUrl) location.href = this.cfg.prodevUrl.replace('{part}', encodeURIComponent($(p, '[data-inq-name] input').value)).replace('{inquiry}', encodeURIComponent(this.doc.inquiry_no));
    }

    newCustomer(modal) {
      const inp = $(modal, '[name="new_customer_name"]');
      if (!inp.value.trim()) return fieldErr(inp, 'Nama Customer wajib diisi.');
      fieldErr(inp, ''); closeLayer(modal);
      toast(`Customer "${inp.value.trim()}" dibuat (DRAFT) dan dipakai.`, 'success');
    }
  }
  Devlib.define('InquiryForm', InquiryForm);
})(window.Devlib);
