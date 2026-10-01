/* DevLib · Phone */
(function (Devlib) {
  'use strict';
  const {$, $$, applyMask, fieldErr, T, Tf} = Devlib.h;
  // Telepon per negara: aturan dari Devlib\PhoneFormat (data-countries).
  class Phone extends Devlib.Component {
    static selector = "[data-dl-phonebox]";
    init() {
      this.C = JSON.parse(this.el.dataset.countries);
      this.kind = this.el.dataset.kind;
      this.inp = $(this.el, '[data-dl-phone]');
      this.hid = $(this.el, '[data-dl-phone-value]');
      this.cc = $(this.el, '[data-dl-prefix-value]');
      this.f = this.el.closest('.dl-field');
      this.read = this.f && $(this.f, '[data-dl-phone-read]');
      this.last = { d: '', r: null };
      this.inp.addEventListener('input', this.upd);this.inp.addEventListener('blur', this.check);
      $$(this.el, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { this.upd(); if (this.last.d) this.check(); }));
      if (this.hid.value) this.inp.value = this.hid.value;
      this.upd();
    }

    rules(c) {
      return this.kind === 'any' ? [...this.C[c].mobile, ...this.C[c].landline] : this.C[c][this.kind];
    }

    pick(c, d) {
      return this.rules(c).find(r => new RegExp(r[0]).test(d));
    }

    clean(c, v) {
      let d = v.replace(/\D/g, ''); const dial = this.C[c].dial.slice(1);
      if (/^\s*(\+|00)/.test(v)) { d = d.replace(/^00/, ''); if (d.startsWith(dial)) d = d.slice(dial.length); }
      if (this.C[c].trunk && d.startsWith('0')) d = d.slice(1);
      return d;
    }

    kindName(c, r) {
      return this.C[c].mobile.includes(r) ? 'seluler' : 'rumah/kantor';
    }

    upd() {
      const c = this.cc.value, d = this.clean(c, this.inp.value), r = this.pick(c, d), rule = r || this.rules(c)[0];
      const alts = rule[1].split('|'), len = m => (m.match(/9/g) || []).length, dd = d.slice(0, Math.max(...alts.map(len)));
      this.inp.value = applyMask(alts.find(m => len(m) >= dd.length) || alts[alts.length - 1], dd);
      this.hid.value = dd ? this.C[c].dial + dd : '';
      this.inp.placeholder = this.C[c].example[this.kind === 'landline' ? 'landline' : 'mobile'];
      if (this.read) this.read.textContent = dd ? `Terbaca: ${this.C[c].dial} ${this.inp.value}` + (this.C[c].trunk ? ` · lokal 0${this.inp.value}` : '') + ` · ${this.C[c].name}${r ? ', ' + this.kindName(c, r) : ''}` : '';
      this.last = { d: dd, r };
    }

    check() {
      const c = this.cc.value, { d, r } = this.last, what = T(this.kind === 'landline' ? 'ph_landline' : this.kind === 'mobile' ? 'ph_mobile' : 'ph_phone');
      fieldErr(this.el, !d ? '' : !r ? Tf('ph_bad_pattern', {what, country: this.C[c].name, ex: this.C[c].dial + ' ' + this.inp.placeholder}) : d.length < r[2] ? Tf('ph_too_short', {n: r[2], dial: this.C[c].dial}) : '');
    }
  }
  Devlib.define("Phone", Phone);
})(window.Devlib);
