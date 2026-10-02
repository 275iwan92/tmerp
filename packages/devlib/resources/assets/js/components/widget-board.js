/* DevLib · WidgetBoard */
(function (Devlib) {
  'use strict';
  const {$, $$, closeLayer} = Devlib.h;

  // Papan widget: Customize (pindah / lebar / lepas / tambah / reset). Setiap aksi dikirim ke server, yang mengembalikan
  // HTML papan terbaru -- susunan di layar selalu = yang tersimpan. Event dl:refresh ('board') memuat ulang papan.
  class WidgetBoard extends Devlib.Component {
    static selector = "[data-dl-wboard]";
    init() {
      try { this.cfg = JSON.parse(this.el.dataset.config || '{}'); } catch (e) { this.cfg = {}; }
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-wb-edit]')) { this.el.classList.toggle('is-edit'); return; }
        const per = e.target.closest('[data-wb-period]');
        if (per) { if (!per.classList.contains('is-active')) this.send({action: 'period', key: per.dataset.wbPeriod}); return; }
        const add = e.target.closest('[data-wb-add]');
        if (add) { this.send({action: 'add', key: add.dataset.wbAdd}); return; }
        const act = e.target.closest('[data-wb-act]');
        if (act) this.send({action: act.dataset.wbAct, key: act.closest('[data-key]')?.dataset.key || null});
      });
      this.refresh = e => { if (e.detail === 'board' && document.contains(this.el)) this.send(null); };
      document.addEventListener('dl:refresh', this.refresh);
    }

    // body null = ambil ulang saja (GET).
    send(body) {
      if (!this.cfg.action || this.busy) return;
      this.busy = true; this.el.classList.add('is-busy');
      const opt = {credentials: 'same-origin', headers: {'Accept': 'application/json'}};
      if (body) Object.assign(opt, {method: 'POST', body: JSON.stringify(body)}, {headers: {...opt.headers, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf || window.DL_CSRF || ''}});
      fetch(this.cfg.action, opt).then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => Promise.reject(j.message || '')))
        .then(res => this.swap(res.html))
        .catch(msg => { this.busy = false; this.el.classList.remove('is-busy'); Devlib.toast(typeof msg === 'string' && msg ? msg : Devlib.h.T('wb_failed'), 'danger'); });
    }

    swap(html) {
      const box = document.createElement('div'); box.innerHTML = html;
      const next = $(box, '[data-dl-wboard]'); if (!next) { this.busy = false; this.el.classList.remove('is-busy'); return; }
      next.classList.toggle('is-edit', this.el.classList.contains('is-edit'));
      $$(this.el, '[data-dl-modal-id].is-on').forEach(closeLayer);
      document.removeEventListener('dl:refresh', this.refresh);
      this.el.replaceWith(next);
      Devlib.mount(next.parentNode);
    }
  }
  Devlib.define("WidgetBoard", WidgetBoard);
})(window.Devlib);
