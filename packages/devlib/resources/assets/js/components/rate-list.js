/* DevLib · RateList — daftar hasil kerja dengan bintang yang langsung tersimpan (x-dl::rate-list) */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class RateList extends Devlib.Component {
    static selector = "[data-dl-rate-list]";
    init() {
      this.rows = $$(this.el, '[data-rl-row]');
      this.type = ''; this.flags = new Set(); this.q = '';
      this.el.addEventListener('click', e => {
        const t = e.target.closest('[data-rl-type]'), f = e.target.closest('[data-rl-flag]');
        if (t) { this.type = t.dataset.rlType; $$(this.el, '[data-rl-type]').forEach(b => b.classList.toggle('is-active', b === t)); this.filter(); }
        if (f) { const k = f.dataset.rlFlag; this.flags.has(k) ? this.flags.delete(k) : this.flags.add(k); f.classList.toggle('is-active', this.flags.has(k)); this.filter(); }
      });
      const q = $(this.el, '[data-rl-q]');
      if (q) q.addEventListener('input', () => { this.q = q.value.trim().toLowerCase(); this.filter(); });
      // Bintang (objek Rating) memicu change pada isian tersembunyinya; catatan disimpan saat keluar dari kotak / Enter.
      this.el.addEventListener('change', e => {
        const row = e.target.closest('[data-rl-row]');
        if (!row) return;
        if (e.target.matches('[data-dl-value]')) { row.dataset.rating = +e.target.value; if (+e.target.value > 0) this.save(row); }
        else if (e.target.matches('[data-rl-note]') && +row.dataset.rating > 0) this.save(row);
      });
      this.el.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-rl-note]')) e.target.blur(); });
    }
    shown(row) {
      const d = row.dataset;
      if (this.type && d.type !== this.type) return false;
      if (this.q && !d.text.includes(this.q)) return false;
      for (const f of this.flags) {
        if (f === 'unrated' && +d.rating > 0) return false;
        if (f === 'low' && !(+d.rating > 0 && +d.rating <= 3)) return false;
      }
      const agents = [...this.flags].filter(f => f.startsWith('agent:')).map(f => f.slice(6));
      return !agents.length || agents.includes(d.agent);
    }
    filter() {
      let n = 0;
      this.rows.forEach(r => { const ok = this.shown(r); r.hidden = !ok; if (ok) n++; });
      const c = $(this.el, '[data-rl-count]'), none = $(this.el, '[data-rl-none]');
      if (c) c.textContent = n;
      if (none) none.hidden = n > 0;
    }
    save(row) {
      const d = this.el.dataset, note = $(row, '[data-rl-note]');
      if (!d.action) return;
      fetch(d.action, {method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': d.csrf || ''},
        body: JSON.stringify({item: +row.dataset.id, rating: +row.dataset.rating, note: note ? note.value : ''})})
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(j => { this.saved(row, j); Devlib.toast(d.tSaved, 'success'); })
        .catch(() => Devlib.toast(d.tFailed, 'danger'));
    }
    saved(row, j) {
      row.classList.add('is-saved'); setTimeout(() => row.classList.remove('is-saved'), 1200);
      const meta = $(row, '[data-rl-meta]');
      if (meta && j.meta) meta.textContent = j.meta;
    }
  }
  Devlib.define("RateList", RateList);
})(window.Devlib);
