/* DevLib · Lookup */
(function (Devlib) {
  'use strict';
  const {$, $$, bindPanel, toast} = Devlib.h;
  /* ======================= SMALL COMPONENTS ======================= */
  // Lookup: cari di semua kolom, pilih baris -> nilai tersimpan + field lain terisi otomatis.
  class Lookup extends Devlib.Component {
    static selector = "[data-dl-lookup]";
    init() {
      this.rows = JSON.parse(this.el.dataset.rows || '[]');
      this.fill = JSON.parse(this.el.dataset.fill || '{}');
      this.p = bindPanel(this.el, () => { if (this.p.search) { this.p.search.value = ''; this.p.search.dispatchEvent(new Event('input')); this.p.search.focus(); } });
      // Mode remote (data-url): ketik min. 2 huruf -> cari ke server, baris diganti hasilnya.
      if (this.el.dataset.url && this.p.search) {
        const cols = JSON.parse(this.el.dataset.columns || '[]'), list = $(this.el, '[data-dl-dd-list]');
        let timer = null, seq = 0;
        this.p.search.addEventListener('input', () => {
          clearTimeout(timer);
          const q = this.p.search.value.trim(); if (q.length < 2) return;
          timer = setTimeout(async () => {
            const my = ++seq, u = new URL(this.el.dataset.url, location.origin); u.searchParams.set('q', q);
            const res = await fetch(u, {headers: {Accept: 'application/json'}, credentials: 'same-origin'}).then(x => x.ok ? x.json() : []).catch(() => []);
            if (my !== seq) return;
            this.rows = res;
            list.innerHTML = res.map((r, i) => `<tr class="dl-dd__item" data-i="${i}">${cols.map(c => `<td>${Devlib.h.esc(r[c] ?? '')}</td>`).join('')}</tr>`).join('')
              || `<tr><td colspan="${cols.length}">Tidak ditemukan.</td></tr>`;
          }, 250);
        });
      }
      $(this.el, '[data-dl-dd-list]').addEventListener('click', e => {
        const tr = e.target.closest('tr.dl-dd__item'); if (!tr) return;
        const r = this.rows[+tr.dataset.i];
        $(this.el, '[data-dl-value]').value = r[this.el.dataset.key];
        $(this.el, '[data-dl-dd-text]').textContent = r[this.el.dataset.label];
        this.p.close();
        $(this.el, '[data-dl-value]').dispatchEvent(new Event('change', {bubbles: true}));
        const scope = this.el.closest('form') || document;
        Object.entries(this.fill).forEach(([field, col]) => {
          const box = scope.querySelector(`[data-dl-field="${field}"]`);
          const input = box ? (box.querySelector('[data-dl-value]') || box.querySelector('input,textarea,select')) : scope.querySelector(`[name="${field}"]`);
          if (!input) return;
          input.value = r[col] ?? '';
          input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true}));
          const txt = box && box.querySelector('[data-dl-dd-text]');
          if (txt) txt.textContent = r[col] ?? '';
          if (box) { box.classList.remove('is-filled'); void box.offsetWidth; box.classList.add('is-filled'); }
        });
      });
      if (this.el.hasAttribute('data-create')) {
        const btn = $(this.el, '[data-dl-lookup-create]'), label = this.el.dataset.label;
        this.p.search.addEventListener('input', () => {
          const q = this.p.search.value.trim();
          btn.hidden = !q || this.rows.some(r => String(r[label]).toLowerCase() === q.toLowerCase());
          $(btn, '[data-dl-lookup-new]').textContent = q;
        });
        // Tambah baru dari dalam pilihan: baris baru langsung terpilih; aplikasi menyimpannya ke master lewat event dl:lookup-create.
        btn.addEventListener('click', () => {
          const q = this.p.search.value.trim(), r = {[this.el.dataset.key]: 'new:' + q, [label]: q};
          this.rows.push(r);
          const tr = document.createElement('tr'); tr.className = 'dl-dd__item'; tr.dataset.i = this.rows.length - 1;
          tr.innerHTML = $$(this.el, 'thead th').map((th, i) => `<td>${i === 0 ? '(baru)' : ''}</td>`).join('');
          tr.children[Math.min(1, tr.children.length - 1)].textContent = q;
          $(this.el, '[data-dl-dd-list]').appendChild(tr);
          $(this.el, '[data-dl-value]').value = r[this.el.dataset.key]; $(this.el, '[data-dl-dd-text]').textContent = q; btn.hidden = true; this.p.close();
          this.el.dispatchEvent(new CustomEvent('dl:lookup-create', {bubbles: true, detail: {text: q}}));
          toast('Ditambahkan: ' + q);
        });
      }
    }
  }
  Devlib.define("Lookup", Lookup);
})(window.Devlib);
