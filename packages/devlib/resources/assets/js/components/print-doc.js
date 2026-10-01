/* DevLib · PrintDoc — cetakan dokumen transaksi (x-dl::print-doc). Teks yang terlalu panjang dikecilkan bertahap
   sampai muat (batas 6,5 pt), bukan dipotong: nama barang, baris kedua / ketiga, sel tabel, nilai field, nama penanda tangan. */
(function (Devlib) {
  'use strict';
  const {$$} = Devlib.h;
  const MIN_PT = 6.5;

  class PrintDoc extends Devlib.Component {
    static selector = '[data-dl-pdoc]';

    init() {
      this.fit();
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => this.fit());
    }

    fit() {
      const shrink = (el, over) => { let s = parseFloat(getComputedStyle(el).fontSize) * .75; while (over() && s > MIN_PT) el.style.fontSize = (s -= .25) + 'pt'; };
      const wide = el => () => el.scrollWidth > el.clientWidth + 1;
      $$(this.el, '.dl-pdoc__n1, .dl-pdoc__n2, .dl-pdoc__n3, .dl-pdoc__w, .dl-pdoc__notes').forEach(el => shrink(el, () => el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1));
      $$(this.el, '.dl-pdoc__items td:not(.is-name):not(.is-no):not(.is-wrap), .dl-pdoc__items th, .dl-pdoc__f > b, .dl-pdoc__nb, .dl-pdoc__sign > div').forEach(el => shrink(el, wide(el)));
    }
  }
  Devlib.define('PrintDoc', PrintDoc);
})(window.Devlib);
