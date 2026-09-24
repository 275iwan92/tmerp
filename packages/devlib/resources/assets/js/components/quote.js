/* DevLib · Quote */
(function (Devlib) {
  'use strict';
  const {$, pickIndex} = Devlib.h;

  class Quote extends Devlib.Component {
    static selector = "[data-dl-quote]";
    init() {
      this.list = JSON.parse(this.el.dataset.quotes || '[]');
      if (!this.list.length) { this.el.style.display = 'none'; return; }
      this.i = pickIndex(this.list.length, this.el.dataset.order, 'quote');
      this.show();
      this.sec = +this.el.dataset.interval;
      if (this.sec > 0) setInterval(() => { this.i = (this.i + 1) % this.list.length; this.show(); }, this.sec * 1000);
    }
    show() { $(this.el, '[data-dl-quote-text]').textContent = this.list[this.i].t; const a = $(this.el, '[data-dl-quote-author]'); a.textContent = this.list[this.i].a; a.style.display = this.list[this.i].a ? '' : 'none'; }
  }
  Devlib.define("Quote", Quote);
})(window.Devlib);
