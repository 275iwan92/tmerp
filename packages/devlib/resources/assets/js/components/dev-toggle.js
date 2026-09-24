/* DevLib · DevToggle */
(function (Devlib) {
  'use strict';
  const {setDevMode} = Devlib.h;
  // Tombol hidup/mati Mode Developer (tooltip asal-usul objek library).
  class DevToggle extends Devlib.Component {
    static selector = "[data-dl-devtoggle]";
    init() { this.el.addEventListener('click', () => this.toggle()); }
    toggle() { setDevMode(!document.body.classList.contains('dl-dev')); }
  }
  Devlib.define("DevToggle", DevToggle);
})(window.Devlib);
