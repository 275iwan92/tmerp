/* DevLib · Kv */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Kv extends Devlib.Component {
    static selector = "[data-dl-kv]";
    init() {
      this.body = $(this.el, '[data-dl-kv-body]');
      this.name = this.el.dataset.name;
      $(this.el, '[data-dl-kv-add]').addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text"></td><td><input type="text"></td><td><button type="button" data-dl-kv-x title="Hapus baris">&times;</button></td>';
        this.body.appendChild(tr); this.renumber(); $(tr, 'input').focus();
      });
      this.body.addEventListener('click', e => { if (e.target.closest('[data-dl-kv-x]')) { e.target.closest('tr').remove(); this.renumber(); } });
    }

    renumber() {
      return $$(this.body, 'tr').forEach((tr, i) => { const [k, v] = $$(tr, 'input'); k.name = `${this.name}[${i}][key]`; v.name = `${this.name}[${i}][value]`; });
    }
  }
  Devlib.define("Kv", Kv);
})(window.Devlib);
