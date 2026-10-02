/* DevLib · WidgetBoard */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  // Papan widget: Customize (pindah / lebar / lepas / tambah / reset). Setiap aksi dikirim ke server, yang mengembalikan
  // HTML papan terbaru -- susunan di layar selalu = yang tersimpan. Event dl:refresh ('board') memuat ulang papan.
  class WidgetBoard extends Devlib.Component {
    static selector = "[data-dl-wboard]";
    init() {
      try { this.cfg = JSON.parse(this.el.dataset.config || '{}'); } catch (e) { this.cfg = {}; }
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-wb-edit]')) { this.el.classList.toggle('is-edit'); this.draggable(); return; }
        const per = e.target.closest('[data-wb-period]');
        if (per) { if (!per.classList.contains('is-active')) this.send({action: 'period', key: per.dataset.wbPeriod}); return; }
        const dash = e.target.closest('[data-wb-dash]');
        if (dash) { if (!dash.classList.contains('is-active')) this.send({action: 'dashboard', key: dash.dataset.wbDash}); return; }
        const add = e.target.closest('[data-wb-add]');
        if (add) { this.send({action: 'add', key: add.dataset.wbAdd}); return; }
        const act = e.target.closest('[data-wb-act]');
        if (act) this.send({action: act.dataset.wbAct, key: act.closest('[data-key]')?.dataset.key || null});
      });
      // Seperti layar ponsel: tahan lama sebuah widget = masuk mode atur; di mode atur widget bisa diseret ke tempat widget lain.
      let hold = null;
      this.el.addEventListener('pointerdown', e => {
        if (!this.el.querySelector('[data-wb-edit]') || this.el.classList.contains('is-edit') || !e.target.closest('.dl-wb__item') || e.target.closest('button,a,input')) return;
        hold = setTimeout(() => { hold = null; this.el.classList.add('is-edit'); this.draggable(); }, 600);
      });
      ['pointerup', 'pointerleave', 'pointermove'].forEach(t => this.el.addEventListener(t, () => { if (hold) { clearTimeout(hold); hold = null; } }));
      this.el.addEventListener('dragstart', e => {
        const it = e.target.closest?.('.dl-wb__item'); if (!it || !this.el.classList.contains('is-edit')) return;
        this.drag = it.dataset.key; it.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', this.drag);
      });
      this.el.addEventListener('dragend', () => { this.drag = null; $$(this.el, '.is-drag,.is-over').forEach(x => x.classList.remove('is-drag', 'is-over')); });
      this.el.addEventListener('dragover', e => {
        const it = e.target.closest?.('.dl-wb__item,.dl-wb__addtile'); if (!this.drag || !it) return;
        e.preventDefault(); $$(this.el, '.is-over').forEach(x => x.classList.remove('is-over')); if (it.dataset.key !== this.drag) it.classList.add('is-over');
      });
      this.el.addEventListener('drop', e => {
        const it = e.target.closest?.('.dl-wb__item,.dl-wb__addtile'); if (!this.drag || !it) return;
        e.preventDefault(); const key = this.drag, to = it.dataset.key || null; this.drag = null;
        if (to !== key) this.send({action: 'move', key, to});
      });
      // Cari di jendela pilihan widget: kartu yang tidak cocok disembunyikan, judul kelompok tanpa kartu ikut hilang.
      this.el.addEventListener('input', e => {
        const s = e.target.closest('[data-wb-search]'); if (!s) return;
        const q = s.value.trim().toLowerCase(), box = s.closest('[data-dl-modal-id]');
        $$(box, '[data-wb-find]').forEach(c => { c.hidden = q !== '' && !c.dataset.wbFind.includes(q); });
        $$(box, '[data-wb-group]').forEach(g => { const list = g.nextElementSibling; g.hidden = list.hidden = !$(list, '[data-wb-find]:not([hidden])'); });
      });
      this.draggable();
      // Daftar centang Choose Widgets: centang = pasang, lepas centang = lepas.
      this.el.addEventListener('change', e => {
        const t = e.target.closest('[data-wb-toggle]');
        if (t) this.send({action: t.checked ? 'add' : 'remove', key: t.dataset.wbToggle});
      });
      this.refresh = e => { if (e.detail === 'board' && document.contains(this.el)) this.send(null); };
      document.addEventListener('dl:refresh', this.refresh);
    }

    // Widget hanya bisa diseret selama mode atur.
    draggable() { const on = this.el.classList.contains('is-edit'); $$(this.el, '.dl-wb__item').forEach(i => { i.draggable = on; }); }

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
      // Jendela yang sedang terbuka (Choose Widgets) tetap terbuka di papan baru, di posisi gulir yang sama.
      $$(this.el, '[data-dl-modal-id].is-on').forEach(m => {
        const again = $(next, `[data-dl-modal-id="${m.dataset.dlModalId}"]`); if (!again) return;
        again.classList.add('is-on');
        const q = $(m, '[data-wb-search]'), q2 = $(again, '[data-wb-search]');
        if (q && q2 && q.value) { q2.value = q.value; setTimeout(() => q2.dispatchEvent(new Event('input', {bubbles: true})), 0); }
        const from = $(m, '.dl-modal__body'), to = $(again, '.dl-modal__body'); if (from && to) setTimeout(() => { to.scrollTop = from.scrollTop; }, 0);
      });
      document.removeEventListener('dl:refresh', this.refresh);
      this.el.replaceWith(next);
      Devlib.mount(next.parentNode);
    }
  }
  Devlib.define("WidgetBoard", WidgetBoard);
})(window.Devlib);
