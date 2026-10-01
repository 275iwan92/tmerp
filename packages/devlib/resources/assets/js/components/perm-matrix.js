/* DevLib · PermMatrix */
(function (Devlib) {
  'use strict';
  const {$, esc, toast, T, Tf} = Devlib.h;
  /* ============================ PERMISSION MATRIX ============================ */
  class PermMatrix extends Devlib.Component {
    static selector = "[data-dl-perm]";
    init() {
      this.OBJ = JSON.parse(this.el.dataset.objects || '[]');
      this.LV = JSON.parse(this.el.dataset.levels || '[]');
      this.DEF = +this.el.dataset.default || 0;
      this.saved = JSON.parse(this.el.dataset.perms || '{}');
      this.work = JSON.parse(JSON.stringify(this.saved));
      this.gSel = $(this.el, '[data-pm-group]');
      this.mSel = $(this.el, '[data-pm-module]');
      this.q = $(this.el, '[data-pm-q]');
      this.el.addEventListener('change', e => {
        if (e.target.matches('[data-pm-obj]')) { this.set(e.target.dataset.pmObj, e.target.value); return this.draw(); }
        if (e.target.matches('[data-pm-bulk]') && e.target.value !== '') { this.shown().forEach(o => this.set(o.key, e.target.value)); toast(Tf('pmx_bulk_set', {n: this.shown().length, level: this.LV[+e.target.value]})); e.target.value = ''; return this.draw(); }
        if (e.target === this.gSel || e.target === this.mSel) this.draw();
      });
      this.q.addEventListener('input', this.draw);
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-pm-reset]')) { Object.keys(this.work).forEach(k => delete this.work[k]); Object.assign(this.work, JSON.parse(JSON.stringify(this.saved))); return this.draw(); }
        if (e.target.closest('[data-pm-save]')) {
          const perms = Object.fromEntries(this.OBJ.map(o => [o.key, this.get(this.work, this.gSel.value, o.key)]));
          Object.keys(this.work).forEach(g => { this.saved[g] = {...(this.saved[g] || {}), ...this.work[g]}; });
          this.el.dispatchEvent(new CustomEvent('dl:permissions-save', {bubbles: true, detail: {group: this.gSel.value, perms, all: this.work}}));
          toast('Hak akses grup disimpan.', 'success'); this.draw();
        }
      });
      this.draw();
    }

    get(src, g, k) {
      return src[g]?.[k] ?? this.DEF;
    }

    shown() {
      return this.OBJ.filter(o => (!this.mSel.value || o.module === this.mSel.value) && (!this.q.value.trim() || o.name.toLowerCase().includes(this.q.value.trim().toLowerCase())));
    }

    dirtyCount() {
      return Object.keys(this.work).reduce((n, g) => n + this.OBJ.filter(o => this.get(this.work, g, o.key) !== this.get(this.saved, g, o.key)).length, 0);
    }

    draw() {
      const g = this.gSel.value;
      $(this.el, '[data-pm-body]').innerHTML = this.shown().map(o => { const v = this.get(this.work, g, o.key), ch = v !== this.get(this.saved, g, o.key); return `<tr class="${ch ? 'is-dirty' : ''}"><td>${esc(o.name)}</td><td><span class="dl-perm__pill is-${esc(String(o.type).toLowerCase())}">${esc(o.type)}</span></td><td>${esc(o.module)}</td><td><select data-pm-obj="${esc(o.key)}" aria-label="Hak akses ${esc(o.name)}" class="is-l${v}">${this.LV.map((l, i) => `<option value="${i}" ${i === v ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></td></tr>`; }).join('') || '<tr><td colspan="4" class="dl-perm__none">Tidak ada objek.</td></tr>';
      const n = this.dirtyCount(); $(this.el, '[data-pm-dirty]').textContent = n ? Tf('pmx_dirty', {n}) : T('pmx_clean');
      this.el.classList.toggle('is-dirty', n > 0);
    }

    set(k, v) { (this.work[this.gSel.value] ||= {})[k] = +v; }
  }
  Devlib.define("PermMatrix", PermMatrix);
})(window.Devlib);
