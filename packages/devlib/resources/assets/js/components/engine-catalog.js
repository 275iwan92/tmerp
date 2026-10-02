/* DevLib · EngineCatalog */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  // Daftar engine: kotak cari (nama, parameter, tabel -- seluruh teks kartu), saringan grup, buka / tutup semua.
  class EngineCatalog extends Devlib.Component {
    static selector = "[data-dl-engine-catalog]";
    init() {
      this.items = $$(this.el, '[data-eng-item]');
      this.q = $(this.el, '[data-eng-q]');
      this.group = '';
      this.q.addEventListener('input', () => this.apply());
      $$(this.el, '[data-eng-group]').forEach(b => b.addEventListener('click', () => {
        this.group = b.dataset.engGroup;
        $$(this.el, '[data-eng-group]').forEach(x => x.classList.toggle('is-active', x === b));
        this.apply();
      }));
      $(this.el, '[data-eng-all]')?.addEventListener('click', () => {
        const shown = this.items.filter(i => !i.hidden), open = shown.some(i => !i.open);
        shown.forEach(i => { i.open = open; });
      });
      // Tautan langsung ke satu engine (#engine-kunci) membukanya.
      const target = location.hash && this.el.querySelector(location.hash);
      if (target?.matches('[data-eng-item]')) { target.open = true; target.scrollIntoView({block: 'start'}); }
    }

    apply() {
      const words = this.q.value.toLowerCase().split(/\s+/).filter(Boolean);
      let n = 0;
      this.items.forEach(i => {
        const text = i.textContent.toLowerCase();
        const ok = (!this.group || i.dataset.group === this.group) && words.every(w => text.includes(w));
        i.hidden = !ok; if (ok) n++;
        if (ok && words.length) i.open = true;
      });
      $$(this.el, '[data-eng-section]').forEach(s => { s.hidden = !$$(s, '[data-eng-item]').some(i => !i.hidden); });
      $(this.el, '[data-eng-count]').textContent = n;
      $(this.el, '[data-eng-none]').hidden = n > 0;
    }
  }
  Devlib.define("EngineCatalog", EngineCatalog);
})(window.Devlib);
