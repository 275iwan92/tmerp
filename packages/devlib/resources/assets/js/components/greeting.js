/* DevLib · Greeting */
(function (Devlib) {
  'use strict';

  class Greeting extends Devlib.Component {
    static selector = "[data-dl-greeting]";
    init() {
      const root = this.el;
      const G = {id: ['Selamat pagi', 'Selamat siang', 'Selamat sore', 'Selamat malam'], en: ['Good morning', 'Good afternoon', 'Good evening', 'Good night']};
      const upd = () => {
        const h = +new Date().toLocaleString('en-US', {hour: 'numeric', hour12: false, timeZone: root.dataset.tz || undefined}) % 24;
        const i = h < 11 ? 0 : h < 15 ? 1 : h < 18 ? 2 : 3;
        root.textContent = G[Devlib.lang][i] + (root.dataset.name ? ', ' + root.dataset.name : '') + ' 👋';
      };
      upd(); setInterval(upd, 60000); document.addEventListener('dl:lang', upd);
    }
  }
  Devlib.define("Greeting", Greeting);
})(window.Devlib);
