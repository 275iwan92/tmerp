/* DevLib · Lookup */
(function (Devlib) {
  'use strict';
  const {$, $$, bindPanel, toast} = Devlib.h;
  /* ======================= SMALL COMPONENTS ======================= */
  // Lookup: cari di semua kolom, pilih baris -> nilai tersimpan + field lain terisi otomatis.
  class Lookup extends Devlib.Component {
    static selector = "[data-dl-lookup]";
    init() {
      const root = this.el;
      const rows = JSON.parse(root.dataset.rows || '[]'), fill = JSON.parse(root.dataset.fill || '{}');
      const p = bindPanel(root, () => { if (p.search) { p.search.value = ''; p.search.dispatchEvent(new Event('input')); p.search.focus(); } });
      $$(root, 'tr.dl-dd__item').forEach(tr => tr.addEventListener('click', () => {
        const r = rows[+tr.dataset.i];
        $(root, '[data-dl-value]').value = r[root.dataset.key];
        $(root, '[data-dl-dd-text]').textContent = r[root.dataset.label];
        p.close();
        $(root, '[data-dl-value]').dispatchEvent(new Event('change', {bubbles: true}));
        const scope = root.closest('form') || document;
        Object.entries(fill).forEach(([field, col]) => {
          const box = scope.querySelector(`[data-dl-field="${field}"]`);
          const input = box ? (box.querySelector('[data-dl-value]') || box.querySelector('input,textarea,select')) : scope.querySelector(`[name="${field}"]`);
          if (!input) return;
          input.value = r[col] ?? '';
          input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true}));
          const txt = box && box.querySelector('[data-dl-dd-text]');
          if (txt) txt.textContent = r[col] ?? '';
          if (box) { box.classList.remove('is-filled'); void box.offsetWidth; box.classList.add('is-filled'); }
        });
      }));
      if (root.hasAttribute('data-create')) {
        const btn = $(root, '[data-dl-lookup-create]'), label = root.dataset.label;
        p.search.addEventListener('input', () => {
          const q = p.search.value.trim();
          btn.hidden = !q || rows.some(r => String(r[label]).toLowerCase() === q.toLowerCase());
          $(btn, '[data-dl-lookup-new]').textContent = q;
        });
        // Tambah baru dari dalam pilihan: baris baru langsung terpilih; aplikasi menyimpannya ke master lewat event dl:lookup-create.
        btn.addEventListener('click', () => {
          const q = p.search.value.trim(), r = {[root.dataset.key]: 'new:' + q, [label]: q};
          rows.push(r);
          const tr = document.createElement('tr'); tr.className = 'dl-dd__item'; tr.dataset.i = rows.length - 1;
          tr.innerHTML = $$(root, 'thead th').map((th, i) => `<td>${i === 0 ? '(baru)' : ''}</td>`).join('');
          tr.children[Math.min(1, tr.children.length - 1)].textContent = q;
          $(root, '[data-dl-dd-list]').appendChild(tr);
          $(root, '[data-dl-value]').value = r[root.dataset.key]; $(root, '[data-dl-dd-text]').textContent = q; btn.hidden = true; p.close();
          root.dispatchEvent(new CustomEvent('dl:lookup-create', {bubbles: true, detail: {text: q}}));
          toast('Ditambahkan: ' + q);
        });
      }
    }
  }
  Devlib.define("Lookup", Lookup);
})(window.Devlib);
