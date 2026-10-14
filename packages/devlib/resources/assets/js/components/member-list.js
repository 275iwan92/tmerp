/* REMARK
 * Tujuan       : Perilaku x-dl::member-list: daftar peserta ber-batas (pilih, atur peran, hapus) yang ikut dikirim bersama form.
 * Sumber hulu  : config JSON dari Devlib\Components\MemberList (rows, options, roles, max, text).
 * Input        : klik Add / Remove (tombol ikon tong sampah, nama lewat title), ubah peran, pilih orang di kotak pilih.
 * Output       : input hidden name[i][employee_id] dan name[i][role] di dalam form; form diberi tanda belum disimpan (dirty-guard DevLib).
 * Dipakai oleh : x-dl::member-list (screens/crew-form). Undo form mengembalikan isi awal lewat fillForm -> el.dlMembers.reset().
 * Status       : Aktif.
 */
(function (Devlib) {
  'use strict';
  const {$, esc, dirtyForms} = Devlib.h;

  class MemberList extends Devlib.Component {
    static selector = "[data-dl-members]";
    init() {
      this.cfg = JSON.parse(this.el.dataset.config);
      this.initial = this.cfg.rows.map(r => ({...r}));
      this.rows = this.initial.map(r => ({...r}));
      this.el.dlMembers = this;
      this.pick = $(this.el, '[data-m-pick]');
      this.add = $(this.el, '[data-m-add]');
      this.pick.addEventListener('change', () => { this.add.disabled = !this.pick.value; });
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-m-add]')) {
          const v = this.pick.value;
          if (v && this.rows.length < this.cfg.max && !this.rows.some(r => String(r.id) === v)) { this.rows.push({id: v, role: this.cfg.roles[1] || this.cfg.roles[0] || ''}); this.changed(); }
        }
        const rm = e.target.closest('[data-m-rm]');
        if (rm) { this.rows.splice(+rm.dataset.mRm, 1); this.changed(); }
      });
      this.el.addEventListener('change', e => {
        const r = e.target.closest('[data-m-role]');
        if (r) { this.rows[+r.dataset.mRole].role = r.value; this.sync(); this.markDirty(); }
      });
      this.draw();
    }

    // Isi awal (saat form dibuka) kembali; dipanggil Undo form lewat fillForm.
    reset() {
      this.rows = this.initial.map(r => ({...r}));
      this.draw();
    }

    changed() {
      this.draw();
      this.markDirty();
    }

    markDirty() {
      const form = this.el.closest('[data-dl-guard]');
      if (form) { dirtyForms.add(form); form.classList.add('is-dirty'); }
    }

    // Input yang dikirim bersama form: dibangun ulang dari state.
    sync() {
      this.el.querySelectorAll('[data-m-field]').forEach(n => n.remove());
      this.rows.forEach((r, i) => {
        const h = document.createElement('span');
        h.hidden = true;
        h.dataset.mField = '';
        h.innerHTML = `<input type="hidden" name="${esc(this.cfg.name)}[${i}][employee_id]" value="${esc(r.id)}"><input type="hidden" name="${esc(this.cfg.name)}[${i}][role]" value="${esc(r.role)}">`;
        this.el.appendChild(h);
      });
    }

    draw() {
      const c = this.cfg, n = this.rows.length, full = n >= c.max, T = c.text;
      $(this.el, '[data-m-count]').textContent = T.count.replace(':n', n).replace(':max', c.max);
      $(this.el, '[data-m-bar]').style.width = Math.min(100, n / c.max * 100) + '%';
      $(this.el, '[data-m-rows]').innerHTML = this.rows.map((r, i) => `<div class="dl-members__row"><span class="dl-members__no">${i + 1}</span>`
        + `<span class="dl-members__who">${esc(c.options[r.id] || r.id)}</span>`
        + `<select class="dl-members__role" data-m-role="${i}" aria-label="${esc(T.role)}">${c.roles.map(x => `<option${x === r.role ? ' selected' : ''}>${esc(x)}</option>`).join('')}</select>`
        + `<button type="button" class="dl-iconbtn dl-iconbtn--danger" data-m-rm="${i}" title="${esc(T.remove)}" aria-label="${esc(T.remove)}" data-dl-confirm="${esc(T.confirm)}" data-dl-confirm-tone="warn"><i class="fa-solid fa-trash-can"></i></button></div>`).join('');
      const used = new Set(this.rows.map(r => String(r.id)));
      this.pick.innerHTML = `<option value="">${esc(full ? T.full.replace(/:max/g, c.max) : T.pick)}</option>`
        + Object.entries(c.options).filter(([k]) => !used.has(k)).map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('');
      this.pick.disabled = full;
      this.add.disabled = true;  // aktif hanya setelah seseorang dipilih
      this.add.textContent = '+ ' + T.add;
      this.el.classList.toggle('is-over', n > c.max);
      this.sync();
    }
  }
  Devlib.define("MemberList", MemberList);
})(window.Devlib);
