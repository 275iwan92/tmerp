/* DevLib · Frame */
(function (Devlib) {
  'use strict';
  const {$, $$, pickIndex} = Devlib.h;

  class Frame extends Devlib.Component {
    static selector = "[data-dl-frame]";
    init() {
      const root = this.el;
      const imgs = JSON.parse(root.dataset.images || '[]'), layers = $$(root, '.dl-frame__bg img');
      const portrait = () => window.matchMedia('(orientation: portrait)').matches;
      let i = pickIndex(imgs.length, root.dataset.order, 'bg-' + root.dataset.key), front = 0;
      const show = () => {
        if (i < 0) return;
        const src = portrait() && imgs[i].portrait ? imgs[i].portrait : imgs[i].src, next = layers[1 - front];
        next.onload = () => { next.classList.add('is-on'); layers[front].classList.remove('is-on'); front = 1 - front; };
        next.src = src;
      };
      show();
      const sec = +root.dataset.interval;
      if (sec > 0 && imgs.length > 1) setInterval(() => { i = root.dataset.order === 'random' ? Math.floor(Math.random() * imgs.length) : (i + 1) % imgs.length; show(); }, sec * 1000);
      window.matchMedia('(orientation: portrait)').addEventListener('change', show);

      // Grip: lebarkan box sisi kiri/kanan (15%–40% layar).
      const grip = $(root, '[data-dl-frame-grip]');
      if (grip) {
        const left = root.classList.contains('dl-frame--box-left');
        let drag = false;
        grip.addEventListener('mousedown', e => { drag = true; root.classList.add('is-resizing'); e.preventDefault(); });
        window.addEventListener('mousemove', e => {
          if (!drag) return;
          const min = Math.max(window.innerWidth * 0.15, 260), max = window.innerWidth * 0.40;
          root.style.setProperty('--dl-box-w', Math.max(min, Math.min(max, left ? e.clientX : window.innerWidth - e.clientX)) + 'px');
        });
        window.addEventListener('mouseup', () => { drag = false; root.classList.remove('is-resizing'); });
      }
    }
  }
  Devlib.define("Frame", Frame);
})(window.Devlib);
