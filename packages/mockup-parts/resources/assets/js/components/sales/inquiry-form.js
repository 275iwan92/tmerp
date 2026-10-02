/* MockupParts · Sales · InquiryForm — aturan layar Inquiry Product (sama dengan tmerp pages/inquiry/form.blade.php).
   Simpan/Confirm/Batalkan/Tandai Kalah/Kirim ke ProDev/Customer baru/Artwork -> route v2.inquiry.* -> database situs
   (ddt* di mockup.test, tm2* live) lewat App\Services\Sales\InquiryWriter. Setelah simpan halaman dimuat ulang dari database. */
(function (Devlib) {
  'use strict';
  const {$, $$, esc, toast, confirmBox, openLayer, closeLayer, setField, setOptions, fieldErr} = Devlib.h;
  const InquiryDoc = Devlib.classes.InquiryDoc;
  const CUSTOM = '__CUSTOM__', CUSTOM_DETAIL = Devlib.h.t('➕ Custom (not in the list yet)'), CUSTOM_SIZE = Devlib.h.t('✎ Custom size (fill in the dimensions in the part)');

  class InquiryForm extends InquiryDoc {
    static selector = '[data-mp-inquiry]';

    init() {
      super.init();
      this.tpl = $(this.el, 'template[data-inq-part-tpl]');
      this.seq = this.parts().length;
      this.type = this.input('type_code')?.value || '';
      // Kategori Produk -> Detail Kategori -> Ukuran Pasaran (master Lookup Product Market, dikirim layar sebagai JSON).
      try { this.markets = JSON.parse($(this.el, 'script[data-inq-markets]')?.textContent || '[]'); } catch (err) { this.markets = []; }
      if (this.doc.id && this.doc.market) this.showMarket(this.doc.market);
      // Bahan slot 0 Paper / 1 Sheet-SF: Jenis -> Gramatur (mat_category level 2 -> 3).
      try { this.chains = JSON.parse($(this.el, 'script[data-inq-matchains]')?.textContent || '[]'); } catch (err) { this.chains = []; }
      this.on('change', null, e => this.changed(e));
      this.on('click', null, e => this.clicked(e));
      this.on('input', '[data-inq-name] input', (e, t) => { this.title(t.closest('[data-inq-part]')); });
      this.el.addEventListener('dl:lookup-create', e => { if (e.target.closest('[data-dl-field="customer_id"]')) { setField(this.el, 'new_customer_name', e.detail.text); openLayer('inq-newcust'); } });
      this.parts().forEach(p => this.gate(p));
      this.template();
      this.custMaterial();
      this.removable();
      if (this.cfg.readonly || this.doc.stat_confirm_sales) this.lock();
      // Artwork part tersimpan pada dokumen terkunci langsung dikirim (tidak ada tombol Simpan lagi).
      // Artwork dipilih/ditarik/dihapus -> pratinjau transparan di dieline & 3D; dokumen terkunci -> langsung tersimpan.
      this.el.addEventListener('dl:upload', e => {
        const inner = !!e.target.closest('[data-inq-artwork-inner]');
        const p = e.target.closest('[data-inq-artwork], [data-inq-artwork-inner]')?.closest('[data-inq-part]'); if (!p) return;
        const f = e.detail.files[0];
        this.artPreview(p, f, inner);
        if (f && this.locked()) this.uploadArtwork(p, f, inner);
      });
      try { const m = sessionStorage.getItem('inq-flash'); if (m) { sessionStorage.removeItem('inq-flash'); this.flash(m); } } catch (err) { /* tanpa storage: pesan dilewati */ }
    }

    locked() { return !!(this.cfg.readonly || this.doc.stat_confirm_sales); }

    // POST ke server (CSRF + JSON). 422 -> pesan per field; selain itu pesan umum.
    async call(url, body) {
      const res = await fetch(url, {method: 'POST', body, headers: {'X-CSRF-TOKEN': this.cfg.urls.csrf, Accept: 'application/json'}});
      const json = await res.json().catch(() => ({}));
      if (res.ok && !res.redirected && json.message) return json;
      if (res.redirected) { toast(Devlib.h.t('The session has ended — reload the page and log in again. The data is NOT saved yet.'), 'danger'); throw new Error('redirected'); }
      if (res.status === 422 && json.errors) {
        Object.entries(json.errors).forEach(([k, msg]) => { const el = this.input(k); if (el) fieldErr(el, msg[0]); });
        const first = Object.values(json.errors)[0][0];
        toast(first, 'danger'); this.flash(first, 'danger');
      } else {
        toast(json.message || Devlib.h.t('Failed to save (HTTP :status).').replace(':status', res.status), 'danger');
      }
      throw new Error('request failed');
    }

    url(key) { return this.cfg.urls[key].replace('__ID__', this.doc.id); }

    reload(json) {
      try { sessionStorage.setItem('inq-flash', json.message); } catch (err) { /* abaikan */ }
      location.href = json.url || location.href;
    }

    // Semua isian bernama di form ini (kecuali isian modal), termasuk file artwork & baris Lines (input hidden).
    payload(action) {
      const fd = new FormData();
      $$(this.el, 'input[name], select[name], textarea[name]').forEach(x => {
        if (x.closest('.dl-mov')) return;
        if ((x.type === 'checkbox' || x.type === 'radio') && !x.checked) return;
        if (x.type === 'file') { [...x.files].forEach(f => fd.append(x.name, f)); return; }
        fd.append(x.name, x.value);
      });
      if (this.doc.id) fd.append('id', this.doc.id);
      fd.append('action', action);
      return fd;
    }

    parts() { return $$(this.el, '[data-inq-part]'); }
    needsRole(code) { return !!this.cfg.templates[code]; }

    changed(e) {
      const n = e.target.name || '';
      if (n === 'type_code') return this.setType(e.target.value);
      if (n === 'product_group') return this.pickGroup(e.target.value);
      if (n === 'product_detail') return this.pickDetail(e.target.value);
      if (n === 'market_size') return this.pickSize(e.target.value);
      if (n === 'product_detail_custom') return this.parts().forEach(p => { const nm = $(p, '[data-inq-name] input'); if (nm) { nm.value = e.target.value; nm.dispatchEvent(new Event('input', {bubbles: true})); } });
      if (n === 'material_source') return this.custMaterial();
      if (n.endsWith('[cfg_color]')) return this.derived(e.target.closest('[data-inq-part]'), e.target.value);
      const row = e.target.closest('[data-inq-mat]');
      if (row && n.endsWith('[chain]')) return this.chain(row);
      if (row && n.endsWith('[jenis]')) return this.jenis(row, e.target.value);
    }

    // Baris bahan: Kategori (Paper / Sheet-SF) -> Jenis -> Gramatur (kosong = "semua").
    matField(row, k) { return $(row, `[name$="[${k}]"]`); }
    matChain(row) { return this.matField(row, 'chain')?.value === 'S' ? 1 : 0; }
    chain(row) {
      setOptions(row, this.matField(row, 'jenis').name, this.chains[this.matChain(row)]?.jenis || []);
      this.jenis(row, '');
    }
    jenis(row, v) {
      const g = this.matField(row, 'gramatur'); if (!g) return;
      setOptions(row, g.name, (this.chains[this.matChain(row)]?.gram || {})[v] || [], {disabled: !v});
    }
    // + Tambah Bahan: lapis tambahan (mis. Paper 2 lapis / Sheet 2 lapis), default Paper.
    addMaterial(p) {
      const box = $(p, '[data-inq-mats]'), tpl = $(p, 'template[data-inq-mat-tpl]');
      const i = +(box.dataset.seq || box.children.length); box.dataset.seq = i + 1;
      const w = document.createElement('div');
      w.innerHTML = tpl.innerHTML.replaceAll('__M__', String(i)).trim();
      const row = w.firstElementChild; box.appendChild(row);
      Devlib.mount(row);
    }

    clicked(e) {
      const x = e.target, part = x.closest('[data-inq-part]');
      if (x.closest('[data-inq-save]')) return this.save();
      if (x.closest('[data-inq-confirm]')) return this.confirm();
      if (x.closest('[data-inq-add-part]')) return this.addPart();
      if (x.closest('[data-inq-mat-add]')) return this.addMaterial(part);
      if (x.closest('[data-inq-mat-del]')) return x.closest('[data-inq-mat]').remove();
      if (x.closest('[data-inq-template]')) return this.applyTemplate();
      if (x.closest('[data-inq-remove]')) return confirmBox({message: Devlib.h.t('Delete this part?'), tone: 'danger'}).then(ok => { if (ok) { part.remove(); this.removable(); this.template(); } });
      if (x.closest('[data-inq-role-open]')) { this.rolePart = part; return openLayer('inq-role'); }
      const pick = x.closest('[data-inq-role-pick]');
      if (pick && this.rolePart) { this.applyRole(this.rolePart, pick.dataset.inqRolePick); closeLayer(pick.closest('.dl-mov')); this.template(); return; }
      const end = x.closest('[data-inq-end]'); if (end) return this.end(end.dataset.inqEnd, end.closest('.dl-mov'));
      if (x.closest('[data-inq-newcust-save]')) return this.newCustomer(x.closest('.dl-mov'));
    }

    // Tipe Produk level Inquiry berlaku untuk SEMUA part (setSharedProductType tmerp): tipe tunggal -> peran MonoForm otomatis,
    // tipe multi-part -> peran dikosongkan dulu (part yang sudah tersimpan tetap tampil apa adanya).
    // Pilih Kategori -> isi Detail (+ Custom); Tipe Produk dibuka lagi sampai Detail dipilih.
    pickGroup(code) {
      this.group = code === CUSTOM ? {code, details: []} : (this.markets.find(g => g.code === code) || null);
      this.detail = null;
      this.showCustom(false);
      setOptions(this.el, 'product_detail', [...(this.group?.details || []).map(d => [d.code, d.label]), [CUSTOM, CUSTOM_DETAIL]]);
      setOptions(this.el, 'market_size', [], {disabled: true});
      this.lockType(false);
      if (code === CUSTOM) { setField(this.el, 'product_detail', CUSTOM); this.pickDetail(CUSTOM); }
    }

    // Pilih Detail -> Tipe Produk (nama dieline internasional) otomatis & terkunci, Ukuran Pasaran milik detail (+ Custom).
    // Detail Custom -> nama produk diketik, Tipe Produk dipilih sendiri (dieline terdekat), ukuran diisi manual di part.
    pickDetail(code) {
      if (code === CUSTOM) {
        this.detail = null;
        this.showCustom(true);
        this.lockType(false);
        setOptions(this.el, 'market_size', [[CUSTOM, CUSTOM_SIZE]]);
        setField(this.el, 'market_size', CUSTOM);
        return;
      }
      this.detail = (this.group?.details || []).find(d => d.code === code) || null;
      if (!this.detail) return;
      this.showCustom(false);
      if (!this.parts().length) this.addPart();
      setField(this.el, 'type_code', this.detail.type_code);
      this.setType(this.detail.type_code);
      this.lockType(true);
      setOptions(this.el, 'market_size', [...this.detail.sizes.map((s, i) => [String(i), s.label]), [CUSTOM, CUSTOM_SIZE]]);
      this.fillParts(null);
    }

    // Dokumen tersimpan: tampilkan Kategori/Detail/Ukuran hasil tebakan server (Sales::marketOf) tanpa mengubah part/Tipe Produk.
    showMarket(m) {
      this.group = this.markets.find(g => g.code === m.group) || null;
      this.detail = (this.group?.details || []).find(d => d.code === m.detail) || null;
      if (!this.detail) return;
      const off = this.locked();
      setField(this.el, 'product_group', m.group);
      setOptions(this.el, 'product_detail', [...this.group.details.map(d => [d.code, d.label]), [CUSTOM, CUSTOM_DETAIL]], {disabled: off});
      setField(this.el, 'product_detail', m.detail);
      setOptions(this.el, 'market_size', [...this.detail.sizes.map((s, i) => [String(i), s.label]), [CUSTOM, CUSTOM_SIZE]], {disabled: off});
      setField(this.el, 'market_size', String(m.size));
    }

    // Pilih Ukuran -> dimensi semua part (Tipe Produk berlaku untuk semua part). Custom = dimensi diisi manual di part.
    pickSize(i) {
      const s = i === CUSTOM ? null : this.detail?.sizes[+i];
      if (!s) return;
      this.parts().forEach(p => { const d = $(p, '[data-dl-dieline-x]'); if (d && d.dlDielineX && d.dlDielineX.setDims) d.dlDielineX.setDims(s.dims); });
      this.fillParts(s);
    }

    showCustom(on) {
      const box = $(this.el, '[data-inq-custom]');
      if (box) box.hidden = !on;
      const inp = this.input('product_detail_custom');
      if (!on && inp) { setField(this.el, 'product_detail_custom', ''); fieldErr(inp, ''); }
    }

    lockType(on) {
      const sel = this.input('type_code')?.closest('[data-dl-select]');
      if (sel) $(sel, '[data-dl-dd-toggle]').disabled = on;
    }

    // Part terisi otomatis dari Product Market: nama part, warna, bahan (#1 Paper, #2 Sheet/SF). Tanpa ketik.
    fillParts(size) {
      const d = this.detail; if (!d) return;
      const sizeName = size ? size.label.split(' (')[0] : '';
      this.parts().forEach(p => {
        const nm = $(p, '[data-inq-name] input');
        if (nm) { nm.value = d.label.split(' (')[0] + (sizeName ? ' ' + sizeName : ''); nm.dispatchEvent(new Event('input', {bubbles: true})); }
        const col = $(p, '[data-inq-color] [data-dl-value]');
        if (col && d.color) { setField(p, col.name, d.color); col.dispatchEvent(new Event('change', {bubbles: true})); }
        (d.materials || []).forEach((m, s) => {
          const row = $(p, `[data-inq-mat="${s}"]`); if (!row || !m.jenis) return;
          setField(row, this.matField(row, 'jenis').name, m.jenis);
          this.jenis(row, String(m.jenis));
          if (m.gramatur) setField(row, this.matField(row, 'gramatur').name, m.gramatur);
        });
      });
    }

    // Nama dieline internasional (nama Tipe Produk) tampil di tiap part -- nama inilah yang dicari di Pacdora.
    showDielineName(code) {
      const sel = this.input('type_code')?.closest('[data-dl-select]');
      const item = sel && code ? $(sel, `.dl-dd__item[data-value="${CSS.escape(code)}"]`) : null;
      this.parts().forEach(p => { const t = $(p, '[data-inq-dieline-name]'); if (!t) return; t.hidden = !item; if (item) t.innerHTML = `Dieline: <b>${esc(item.textContent)}</b> <span class="mp-hint__code">${esc(code)}</span>`; });
    }

    lockType(on) {
      const sel = this.input('type_code')?.closest('[data-dl-select]');
      if (sel) $(sel, '[data-dl-dd-toggle]').disabled = on;
    }

    setType(code) {
      this.type = code;
      this.showDielineName(code);
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
      if (missing.length) $(box, '[data-inq-template-text]').innerHTML = `${Devlib.h.t('🧩 This type usually has :n parts:').replace(':n', tpl.length)} <b>${esc(missing.map(r => this.cfg.roles[r]).join(' + '))}</b>.`;
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
      this.title(p); this.removable(); this.showDielineName(this.type);
      return p;
    }

    removable() { const ps = this.parts(); ps.forEach(p => { $(p, '[data-inq-remove]').hidden = ps.length <= 1; }); }

    derived(p, code) {
      const m = /^(\d+)\/(\d+)$/.exec(code || ''), box = $(p, '[data-inq-derived] .dl-display');
      box.textContent = m ? Devlib.h.t('front: :f · back: :b · total: :n · side: :s').replace(':f', m[1]).replace(':b', m[2]).replace(':n', +m[1] + +m[2]).replace(':s', +m[2] > 0 ? '2SIDE' : '1SIDE') : '—';
    }

    // Sumber Bahan: dicentang = bahan customer -> order JASA, wajib isi spesifikasi bahan customer.
    custMaterial() {
      const c = this.input('material_source'), on = !!(c && c.checked), blk = $(this.el, '[data-inq-custmat]');
      if (blk) blk.hidden = !on;
      const hint = c && $(c.closest('.dl-field'), '.dl-field__hint');
      if (hint) hint.textContent = on ? Devlib.h.t('Ticked → material from the customer, the order is JASA (printing/finishing cost only).') : Devlib.h.t('Unticked → material from the company, the order is PRODUCT.');
    }

    err(name, msg) { const el = this.input(name); if (el) fieldErr(el, msg); return msg ? 1 : 0; }

    // headerRules() + assertTierInvariant() tmerp.
    validate() {
      let bad = 0;
      const name = this.value('product_draft_name'), date = this.value('inquiry_date'), dl = this.value('deadline_quotation');
      bad += this.err('inquiry_date', date ? '' : Devlib.h.t('Date is required.'));
      bad += this.err('source', this.value('source') ? '' : Devlib.h.t('Source must be selected.'));
      bad += this.err('customer_id', this.value('customer_id') ? '' : Devlib.h.t('Customer must be selected.'));
      bad += this.err('product_draft_name', !name ? Devlib.h.t('Product Name is required.') : /[/@#%"']/.test(name) ? Devlib.h.t('Special characters (/@#%"\') are not allowed.') : '');
      bad += this.err('deadline_quotation', dl && date && dl < date ? Devlib.h.t('Customer Need Date must not be before Date.') : '');
      // Kategori Produk wajib sampai Ukuran (dieline muncul); Custom = nama produk + Tipe Produk wajib.
      // Kategori/Detail/Ukuran hanya pemandu isian (tidak disimpan) -> wajib saat membuat, tidak ditagih lagi di dokumen tersimpan.
      const guide = !this.doc.id;
      bad += this.err('product_group', !guide || this.value('product_group') ? '' : Devlib.h.t('Product Category must be selected (choose Custom if it does not exist yet).'));
      bad += this.err('product_detail', !guide || this.value('product_detail') ? '' : Devlib.h.t('Category Detail must be selected.'));
      bad += this.err('market_size', !guide || this.value('market_size') ? '' : Devlib.h.t('Standard Size must be selected.'));
      bad += this.err('product_detail_custom', this.value('product_detail') === CUSTOM && !this.value('product_detail_custom') ? Devlib.h.t('Custom product name is required.') : '');
      bad += this.err('type_code', this.value('type_code') ? '' : Devlib.h.t('Product Type (dieline) must be selected.'));
      bad += this.err('cust_material_spec', this.input('material_source')?.checked && !this.value('cust_material_spec') ? Devlib.h.t('Required when the material is from the customer.') : '');
      const tl = $(this.el, '[data-dl-lines][data-name="tiers"]'), tiers = tl && tl.dlLines ? tl.dlLines.rows : [];
      const prim = tiers.filter(t => t.is_primary).length, zero = tiers.findIndex(t => !(+String(t.qty).replace(/\./g, '') > 0));
      const tmsg = !tiers.length ? Devlib.h.t('At least 1 qty tier row is required.') : prim !== 1 ? Devlib.h.t('There must be exactly 1 PRIMARY tier (currently: :n).').replace(':n', prim) : zero >= 0 ? Devlib.h.t('The tier qty in row :n must be a number greater than 0.').replace(':n', zero + 1) : '';
      const ta = $(this.el, '[data-inq-tier-err]'); ta.textContent = tmsg; ta.classList.toggle('is-show', !!tmsg); bad += tmsg ? 1 : 0;
      if (bad) { $(this.el, '.dl-field.is-invalid, [data-inq-tier-err].is-show')?.scrollIntoView({behavior: 'smooth', block: 'center'}); toast(Devlib.h.t(':n fields are not correct yet.').replace(':n', bad), 'danger'); }
      return !bad;
    }

    async persist(action) {
      if (this.busy || !this.validate()) return;
      this.busy = true;
      try { this.reload(await this.call(this.cfg.urls.save, this.payload(action))); } catch (err) { this.busy = false; }
    }

    save() { return this.persist('save'); }

    confirm() { return this.persist('confirm'); }

    artPreview(p, file, inner = false) {
      const k = inner ? 'dlArtInUrl' : 'dlArtUrl';
      if (p[k]) URL.revokeObjectURL(p[k]);
      p[k] = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
      const d = $(p, '[data-dl-dieline-x]')?.dlDielineX; if (!d) return;
      if (inner) d.setArtworkInner(p[k]); else d.setArtwork(p[k]);
    }

    async uploadArtwork(p, file, inner = false) {
      const fd = new FormData();
      if (inner) fd.append('inner', '1');
      fd.append('part_id', $(p, '[data-inq-part-id]').value);
      fd.append('artwork', file);
      try { const j = await this.call(this.url('artwork'), fd); toast(j.message, 'success'); this.flash(j.message); } catch (err) { /* pesan sudah tampil */ }
    }

    // Form terkunci (lihat-saja): semua isian & tombol mati kecuali yang bertanda data-inq-keep.
    lock() {
      this.lockAll();
      const s = this.doc.sub_state, done = ['CANCELLED', 'CONVERTED', 'LOST'].includes(s);
      $(this.el, '[data-inq-confirm]').hidden = true;
      $(this.el, '[data-inq-when="cancel"]').hidden = !!this.doc.stat_cancel || done;
      $(this.el, '[data-inq-when="lost"]').hidden = done;
      this.parts().forEach(p => { $(p, '[data-inq-remove]').hidden = true; });
      $$(this.el, '[data-inq-add-part]').forEach(b => { b.hidden = true; });
    }

    end(state, modal) {
      const f = state === 'CANCELLED' ? 'cancel_reason' : 'lost_reason', inp = $(modal, `[name="${f}"]`);
      if (!inp.value.trim()) return fieldErr(inp, Devlib.h.t('Required — recorded in the history.'));
      fieldErr(inp, '');
      const fd = new FormData(); fd.append('state', state); fd.append('reason', inp.value.trim());
      this.call(this.url('end'), fd).then(j => { closeLayer(modal); this.reload(j); }).catch(() => {});
    }

    // Customer baru langsung tersimpan ke master (DRAFT) lalu dipakai di lookup Customer.
    newCustomer(modal) {
      const inp = $(modal, '[name="new_customer_name"]'), val = n => ($(modal, `[name="${n}"]`)?.value || '').trim();
      if (!inp.value.trim()) return fieldErr(inp, Devlib.h.t('Customer Name is required.'));
      fieldErr(inp, '');
      const fd = new FormData();
      fd.append('name', inp.value.trim()); fd.append('type', val('new_customer_type') || 'local');
      fd.append('phone', val('new_customer_phone')); fd.append('email', val('new_customer_email')); fd.append('address', val('new_customer_address'));
      this.call(this.cfg.urls.customer, fd).then(j => {
        const box = $(this.el, '[data-dl-field="customer_id"]');
        $(box, '[data-dl-value]').value = j.id; $(box, '[data-dl-dd-text]').textContent = j.label;
        fieldErr($(box, '[data-dl-value]'), '');
        closeLayer(modal); toast(j.message, 'success');
      }).catch(() => {});
    }
  }
  Devlib.define('InquiryForm', InquiryForm);
})(window.Devlib);
