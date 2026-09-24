/* DevLib · Quote */
(function (Devlib) {
  'use strict';
  const {$, pickIndex} = Devlib.h;

  class Quote extends Devlib.Component {
    static selector = "[data-dl-quote]";
    init() {
      const root = this.el;
      const list = JSON.parse(root.dataset.quotes || '[]');
      if (!list.length) { root.style.display = 'none'; return; }
      let i = pickIndex(list.length, root.dataset.order, 'quote');
      const show = () => { $(root, '[data-dl-quote-text]').textContent = list[i].t; const a = $(root, '[data-dl-quote-author]'); a.textContent = list[i].a; a.style.display = list[i].a ? '' : 'none'; };
      show();
      const sec = +root.dataset.interval;
      if (sec > 0) setInterval(() => { i = (i + 1) % list.length; show(); }, sec * 1000);
    }
  }
  Devlib.define("Quote", Quote);
})(window.Devlib);
