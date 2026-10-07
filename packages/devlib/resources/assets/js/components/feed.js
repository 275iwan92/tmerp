/* DevLib · Feed */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  // Umpan blog: tiap kartu menampilkan cuplikan; tombol Read more / Show less membuka isi penuh.
  // Tombol hanya muncul bila isinya memang lebih panjang dari cuplikan.
  class Feed extends Devlib.Component {
    static selector = "[data-dl-feed]";
    init() {
      this.el.querySelectorAll('.dl-feed__post').forEach(p => {
        const body = $(p, '.dl-feed__body'), btn = $(p, '[data-feed-toggle]');
        if (body && btn && body.scrollHeight <= body.clientHeight + 4) btn.hidden = true;
      });
      this.on('click', '[data-feed-toggle]', (e, b) => {
        const post = b.closest('.dl-feed__post'), open = post.classList.toggle('is-open');
        b.textContent = open ? b.dataset.less : b.dataset.more;
      });
    }
  }
  Devlib.define("Feed", Feed);
})(window.Devlib);
