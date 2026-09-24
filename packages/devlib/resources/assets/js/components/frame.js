/* DevLib · Frame */
(function (Devlib) {
  'use strict';
  const {$, $$, pickIndex} = Devlib.h;

  class Frame extends Devlib.Component {
    static selector = "[data-dl-frame]";
    init() {
      this.imgs = JSON.parse(this.el.dataset.images || '[]');
      this.layers = $$(this.el, '.dl-frame__bg img');
      this.i = pickIndex(this.imgs.length, this.el.dataset.order, 'bg-' + this.el.dataset.key);
      this.front = 0;
      this.show();
      this.sec = +this.el.dataset.interval;
      if (this.sec > 0 && this.imgs.length > 1) setInterval(() => { this.i = this.el.dataset.order === 'random' ? Math.floor(Math.random() * this.imgs.length) : (this.i + 1) % this.imgs.length; this.show(); }, this.sec * 1000);
      window.matchMedia('(orientation: portrait)').addEventListener('change', this.show);

      this.grip = $(this.el, '[data-dl-frame-grip]');
      if (this.grip) {
        const left = this.el.classList.contains('dl-frame--box-left');
        let drag = false;
        this.grip.addEventListener('mousedown', e => { drag = true; this.el.classList.add('is-resizing'); e.preventDefault(); });
        window.addEventListener('mousemove', e => {
          if (!drag) return;
          const min = Math.max(window.innerWidth * 0.15, 260), max = window.innerWidth * 0.40;
          this.el.style.setProperty('--dl-box-w', Math.max(min, Math.min(max, left ? e.clientX : window.innerWidth - e.clientX)) + 'px');
        });
        window.addEventListener('mouseup', () => { drag = false; this.el.classList.remove('is-resizing'); });
      }
    }

    portrait() {
      return window.matchMedia('(orientation: portrait)').matches;
    }

    show() {
      if (this.i < 0) return;
      const src = this.portrait() && this.imgs[this.i].portrait ? this.imgs[this.i].portrait : this.imgs[this.i].src, next = this.layers[1 - this.front];
      next.onload = () => { next.classList.add('is-on'); this.layers[this.front].classList.remove('is-on'); this.front = 1 - this.front; };
      next.src = src;
    }
  }
  Devlib.define("Frame", Frame);
})(window.Devlib);
