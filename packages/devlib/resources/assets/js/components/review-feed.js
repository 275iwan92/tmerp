/* DevLib · ReviewFeed — umpan review gaya blog: saring + cari, tombol keputusan langsung tersimpan (x-dl::review-feed).
 * PHP mewarisi Feed (kartu article + isi dl-prose); JS berdiri sendiri karena kartu review selalu terbuka penuh (tanpa Read more). */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class ReviewFeed extends Devlib.Component {
    static selector = "[data-dl-review-feed]";
    init() {
      this.posts = $$(this.el, '[data-rf-post]');
      this.chips = $$(this.el, '[data-rf-filter]');
      this.q = '';
      this.field = this.el.dataset.field || 'decision';
      this.on('click', '[data-rf-filter]', (e, chip) => {
        this.chips.forEach(c => c.classList.toggle('is-active', c === chip));
        this.filter();
      });
      const q = $(this.el, '[data-rf-q]');
      if (q) q.addEventListener('input', () => { this.q = q.value.trim().toLowerCase(); this.filter(); });
      this.on('click', '[data-rf-choice]', (e, btn) => this.choose(btn.closest('[data-rf-post]'), btn.dataset.rfChoice));
      this.filter();
      this.counts();
    }

    // Satu saringan cocok dengan satu butir bila setiap kolom where bernilai salah satu dari daftarnya.
    fits(post, where) {
      const f = JSON.parse(post.dataset.f || '{}');
      return Object.keys(where).every(k => [].concat(where[k]).map(String).includes(String(f[k] ?? '')));
    }

    filter() {
      const chip = this.chips.find(c => c.classList.contains('is-active')), where = chip ? JSON.parse(chip.dataset.where || '{}') : {};
      let n = 0;
      this.posts.forEach(p => {
        const ok = this.fits(p, where) && (!this.q || (p.dataset.text || '').includes(this.q));
        p.hidden = !ok;
        if (ok) n++;
      });
      const shown = $(this.el, '[data-rf-shown]'), none = $(this.el, '[data-rf-none]');
      if (shown) shown.textContent = n;
      if (none) none.hidden = n > 0;
    }

    counts() {
      this.chips.forEach(c => {
        const where = JSON.parse(c.dataset.where || '{}'), b = $(c, '[data-rf-n]');
        if (b) b.textContent = this.posts.filter(p => this.fits(p, where)).length;
      });
    }

    // Klik pilihan = simpan; klik pilihan yang sedang terpilih = batal pilih (kosongkan).
    choose(post, code) {
      const was = post.dataset.decision || '', next = was === code ? '' : code, d = this.el.dataset;
      if (!d.action) return;
      post.classList.add('is-saving');
      fetch(d.action, {method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': d.csrf || ''},
        body: JSON.stringify({changed: [{id: +post.dataset.id, [this.field]: next}]})})
        .then(r => r.json().then(j => ({ok: r.ok, j})))
        .then(({ok, j}) => {
          if (!ok || j.error) throw new Error(j.error || j.message || d.tFailed);
          this.apply(post, next);
          Devlib.toast(d.tSaved, 'success');
        })
        .catch(err => Devlib.toast(err.message || d.tFailed, 'danger'))
        .finally(() => post.classList.remove('is-saving'));
    }

    apply(post, code) {
      const d = this.el.dataset, f = JSON.parse(post.dataset.f || '{}');
      f[this.field] = code;
      post.dataset.f = JSON.stringify(f);
      post.dataset.decision = code;
      post.classList.toggle('is-decided', !!code);
      let label = '';
      $$(post, '[data-rf-choice]').forEach(b => {
        const on = b.dataset.rfChoice === code;
        b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (on) label = b.textContent.trim();
      });
      const st = $(post, '[data-rf-state]');
      if (st) {
        st.textContent = code ? `${d.tDone}: ` : d.tNone;
        if (code) { const b = document.createElement('b'); b.textContent = label; st.appendChild(b); }
      }
      this.counts();
    }
  }
  Devlib.define("ReviewFeed", ReviewFeed);
})(window.Devlib);
