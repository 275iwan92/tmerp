/* DevLib · DocShare — Print (PDF/JPEG/HTML online/Printer) + Email untuk dokumen/form mana pun (x-dl::doc-share). */
(function (Devlib) {
  'use strict';
  const {$, $$, toast, loadLib, openLayer, esc} = Devlib.h;
  const LIB_IMG = 'https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js';
  const LIB_PDF = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
  const LIB_QR = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
  const DROP = 'script, template, button, .dl-btn, .dl-docshare, .dl-mov, input[type=file], [data-dl-noprint], .dl-field__trigger, .dl-tip';

  class DocShare extends Devlib.Component {
    static selector = '[data-dl-docshare]';

    init() {
      const d = this.el.dataset;
      this.cfg = {target: d.target, type: d.type, id: d.id || null, no: d.no || null, title: d.title, endpoint: d.endpoint, csrf: d.csrf,
        landscape: d.orientation === 'landscape', themes: JSON.parse(d.printThemes || '{}')};
      this.share = null;
      // Modal dipindah ke <body>: isiannya tidak ikut terkirim bila tag ini ada di dalam <form> layar.
      this.mail = document.querySelector(`[data-dl-modal-id="${d.mail}"]`);
      this.online = document.querySelector(`[data-dl-modal-id="${d.online}"]`);
      [this.mail, this.online].forEach(m => m && document.body.appendChild(m));

      this.on('click', '[data-ds-toggle]', e => { e.stopPropagation(); this.el.classList.toggle('is-open'); });
      document.addEventListener('click', e => { if (!this.el.contains(e.target)) this.el.classList.remove('is-open'); });
      this.on('click', '[data-ds-do]', (e, b) => { this.el.classList.remove('is-open'); this.run(b.dataset.dsDo); });
      if (this.online) {
        $(this.online, '[data-ds-copy]').addEventListener('click', () => navigator.clipboard.writeText(this.share.url).then(() => toast('Link disalin.', 'success')));
        $(this.online, '[data-ds-open]').addEventListener('click', () => window.open(this.share.url, '_blank', 'noopener'));
      }
      if (this.mail) $(this.mail, '[data-ds-send]').addEventListener('click', this.send);
    }

    source() {
      const el = this.cfg.target ? document.querySelector(this.cfg.target) : this.el.closest('form, .dl-card, main');
      if (!el) throw new Error('Area dokumen tidak ditemukan: ' + this.cfg.target);
      return el;
    }

    // Salinan statis area dokumen: nilai isian dibekukan jadi atribut, canvas jadi gambar, tombol & kontrol dibuang.
    freeze(src) {
      const clone = src.cloneNode(true);
      const a = $$(src, 'input, select, textarea, canvas'), b = $$(clone, 'input, select, textarea, canvas');
      a.forEach((s, i) => {
        const c = b[i]; if (!c) return;
        if (s.tagName === 'CANVAS') { const img = document.createElement('img'); try { img.src = s.toDataURL('image/png'); } catch (_) {} img.style.cssText = `width:${s.offsetWidth}px;height:${s.offsetHeight}px`; c.replaceWith(img); }
        else if (s.tagName === 'SELECT') $$(c, 'option').forEach((o, k) => o.toggleAttribute('selected', s.options[k] && s.options[k].selected));
        else if (s.tagName === 'TEXTAREA') c.textContent = s.value;
        else if (s.type === 'checkbox' || s.type === 'radio') c.toggleAttribute('checked', s.checked);
        else c.setAttribute('value', s.value);
      });
      $$(clone, DROP).forEach(n => n.remove());
      clone.removeAttribute('id');
      return clone;
    }

    async post(url, body) {
      const r = await fetch(url, {method: 'POST', body, headers: {'X-CSRF-TOKEN': this.cfg.csrf, Accept: 'application/json'}, credentials: 'same-origin'});
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.message || (j.errors ? Object.values(j.errors)[0][0] : 'Gagal (' + r.status + ')'));
      return j;
    }

    // Simpan snapshot -> token + URL online (sama isi = token lama).
    async snapshot() {
      if (!this.cfg.endpoint) return null;
      const f = new FormData();
      f.append('doc_type', this.cfg.type); if (this.cfg.id) f.append('doc_id', this.cfg.id); if (this.cfg.no) f.append('doc_no', this.cfg.no);
      f.append('title', this.cfg.title); f.append('html', this.freeze(this.source()).outerHTML);
      this.share = await this.post(this.cfg.endpoint, f);
      return this.share;
    }

    async qrData(text, size = 110) {
      await loadLib(LIB_QR, () => window.QRCode);
      const box = document.createElement('div'); new window.QRCode(box, {text, width: size, height: size, correctLevel: window.QRCode.CorrectLevel.M});
      const cv = box.querySelector('canvas'); return cv ? cv.toDataURL('image/png') : box.querySelector('img').src;
    }

    // Render dokumen + kepala QR jadi JPEG (tema terang sementara, ancestor asli dipertahankan supaya CSS layar tetap berlaku).
    async jpeg() {
      await loadLib(LIB_IMG, () => window.htmlToImage);
      const src = this.source(), html = document.documentElement, theme = html.dataset.dlTheme;
      const stage = document.createElement('div'); stage.className = 'dl-docshare__stage';
      stage.style.width = Math.max(src.offsetWidth, 760) + 'px';
      const head = document.createElement('div'); head.className = 'dl-docshare__head';
      head.innerHTML = `<div><b>${esc(this.cfg.title)}${this.cfg.no ? ' · ' + esc(this.cfg.no) : ''}</b><small>Dicetak ${esc(new Date().toLocaleString('id-ID'))}</small></div>`;
      if (this.share) head.innerHTML += `<figure><img src="${await this.qrData(this.share.url)}" alt="QR"><figcaption>${esc(this.share.url)}</figcaption></figure>`;
      stage.append(head, this.freeze(src));
      src.parentNode.insertBefore(stage, src.nextSibling);
      if (this.cfg.themes[theme]) html.dataset.dlTheme = this.cfg.themes[theme];
      try {
        return await window.htmlToImage.toJpeg(stage, {quality: 0.92, pixelRatio: 2, backgroundColor: '#ffffff', style: {position: 'static', left: '0', top: '0'}});
      } finally { stage.remove(); html.dataset.dlTheme = theme; }
    }

    async pdf(img) {
      await loadLib(LIB_PDF, () => window.jspdf);
      const doc = new window.jspdf.jsPDF({orientation: this.cfg.landscape ? 'landscape' : 'portrait', unit: 'mm', format: 'a4'});
      const W = doc.internal.pageSize.getWidth(), H = doc.internal.pageSize.getHeight(), m = 8;
      const p = doc.getImageProperties(img), w = W - 2 * m, h = p.height * w / p.width, page = H - 2 * m;
      // Gambar tinggi dipotong per halaman: gambar sama digeser ke atas tiap halaman (alias = disimpan sekali).
      for (let y = 0, i = 0; y < h; y += page, i++) {
        if (i) doc.addPage();
        doc.addImage(img, 'JPEG', m, m - y, w, h, 'dl-doc', 'FAST');
        doc.setFillColor(255, 255, 255); doc.rect(0, 0, W, m, 'F'); doc.rect(0, H - m, W, m, 'F');
      }
      return doc;
    }

    fileName() { return (this.share && this.share.file) || (this.cfg.title + (this.cfg.no ? '_' + this.cfg.no : '')).replace(/[^A-Za-z0-9._-]+/g, '_'); }

    save(href, ext) { const a = document.createElement('a'); a.href = href; a.download = this.fileName() + '.' + ext; document.body.appendChild(a); a.click(); a.remove(); }

    busy(on) { this.el.classList.toggle('is-busy', on); $$(this.el, 'button').forEach(b => b.disabled = on); }

    async run(what) {
      if (what === 'email') { openLayer(this.el.dataset.mail); return; }
      this.busy(true);
      try {
        await this.snapshot();
        if (what === 'online') {
          $(this.online, '[data-ds-url] input, input[name=ds_url]').value = this.share.url;
          $(this.online, '[data-ds-qr]').innerHTML = `<img src="${await this.qrData(this.share.url, 180)}" alt="QR">`;
          openLayer(this.el.dataset.online);
          return;
        }
        const img = await this.jpeg();
        if (what === 'jpeg') this.save(img, 'jpg');
        else if (what === 'pdf') (await this.pdf(img)).save(this.fileName() + '.pdf');
        else if (what === 'printer') this.printImage(img);
      } catch (e) { toast(e.message || String(e), 'danger', 'Print gagal'); }
      finally { this.busy(false); }
    }

    printImage(img) {
      const f = document.createElement('iframe'); f.style.cssText = 'position:fixed;width:0;height:0;border:0;right:0;bottom:0';
      document.body.appendChild(f);
      f.contentDocument.write(`<!doctype html><title>${esc(this.fileName())}</title><style>@page{size:A4 ${this.cfg.landscape ? 'landscape' : 'portrait'};margin:8mm}body{margin:0}img{width:100%}</style><img src="${img}">`);
      f.contentDocument.close();
      $(f.contentDocument, 'img').onload = () => { f.contentWindow.print(); setTimeout(() => f.remove(), 1000); };
    }

    async send() {
      const box = $(this.mail, '[data-ds-mailform]'), v = n => ($(box, `[name="${n}"]`) || {}).value || '', on = n => !!($(box, `[name="${n}"]`) || {}).checked;
      if (!v('to').trim()) { toast('Isi minimal satu alamat To.', 'warn'); return; }
      const btn = $(this.mail, '[data-ds-send]'); btn.disabled = true;
      try {
        await this.snapshot();
        const f = new FormData();
        ['to', 'cc', 'bcc', 'subject', 'message'].forEach(n => f.append(n, v(n)));
        f.append('with_link', on('with_link') ? 1 : 0);
        if (on('with_image') || on('with_pdf')) {
          const img = await this.jpeg();
          if (on('with_image')) f.append('image', await (await fetch(img)).blob(), 'dokumen.jpg');
          if (on('with_pdf')) f.append('pdf', (await this.pdf(img)).output('blob'), this.fileName() + '.pdf');
        }
        const r = await this.post(this.cfg.endpoint.replace(/\/$/, '') + '/' + this.share.token + '/email', f);
        this.mail.classList.remove('is-on');
        toast(r.message, 'success', 'Email');
      } catch (e) { toast(e.message || String(e), 'danger', 'Email gagal'); }
      finally { btn.disabled = false; }
    }
  }
  Devlib.define('DocShare', DocShare);
})(window.Devlib);
