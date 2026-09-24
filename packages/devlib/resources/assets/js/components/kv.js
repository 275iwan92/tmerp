/* DevLib · Kv */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class Kv extends Devlib.Component {
    static selector = "[data-dl-kv]";
    init() {
      const root = this.el;
      const body = $(root, '[data-dl-kv-body]'), name = root.dataset.name;
      const renumber = () => $$(body, 'tr').forEach((tr, i) => { const [k, v] = $$(tr, 'input'); k.name = `${name}[${i}][key]`; v.name = `${name}[${i}][value]`; });
      $(root, '[data-dl-kv-add]').addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text"></td><td><input type="text"></td><td><button type="button" data-dl-kv-x title="Hapus baris">&times;</button></td>';
        body.appendChild(tr); renumber(); $(tr, 'input').focus();
      });
      body.addEventListener('click', e => { if (e.target.closest('[data-dl-kv-x]')) { e.target.closest('tr').remove(); renumber(); } });
    }
  }
  Devlib.define("Kv", Kv);
})(window.Devlib);
