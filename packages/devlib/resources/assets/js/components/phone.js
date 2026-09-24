/* DevLib · Phone */
(function (Devlib) {
  'use strict';
  const {$, $$, applyMask, fieldErr} = Devlib.h;
  // Telepon per negara: aturan dari Devlib\PhoneFormat (data-countries).
  class Phone extends Devlib.Component {
    static selector = "[data-dl-phonebox]";
    init() {
      const box = this.el;
      const C = JSON.parse(box.dataset.countries), kind = box.dataset.kind, inp = $(box, '[data-dl-phone]'), hid = $(box, '[data-dl-phone-value]'), cc = $(box, '[data-dl-prefix-value]');
      const f = box.closest('.dl-field'), read = f && $(f, '[data-dl-phone-read]');
      const rules = c => kind === 'any' ? [...C[c].mobile, ...C[c].landline] : C[c][kind];
      const pick = (c, d) => rules(c).find(r => new RegExp(r[0]).test(d));
      const clean = (c, v) => {
        let d = v.replace(/\D/g, ''); const dial = C[c].dial.slice(1);
        if (/^\s*(\+|00)/.test(v)) { d = d.replace(/^00/, ''); if (d.startsWith(dial)) d = d.slice(dial.length); }
        if (C[c].trunk && d.startsWith('0')) d = d.slice(1);
        return d;
      };
      const kindName = (c, r) => C[c].mobile.includes(r) ? 'seluler' : 'rumah/kantor';
      let last = { d: '', r: null };
      const upd = () => {
        const c = cc.value, d = clean(c, inp.value), r = pick(c, d), rule = r || rules(c)[0];
        const alts = rule[1].split('|'), len = m => (m.match(/9/g) || []).length, dd = d.slice(0, Math.max(...alts.map(len)));
        inp.value = applyMask(alts.find(m => len(m) >= dd.length) || alts[alts.length - 1], dd);
        hid.value = dd ? C[c].dial + dd : '';
        inp.placeholder = C[c].example[kind === 'landline' ? 'landline' : 'mobile'];
        if (read) read.textContent = dd ? `Terbaca: ${C[c].dial} ${inp.value}` + (C[c].trunk ? ` · lokal 0${inp.value}` : '') + ` · ${C[c].name}${r ? ', ' + kindName(c, r) : ''}` : '';
        last = { d: dd, r };
      };
      const check = () => {
        const c = cc.value, { d, r } = last, what = kind === 'landline' ? 'telepon rumah/kantor' : kind === 'mobile' ? 'HP' : 'telepon';
        fieldErr(box, !d ? '' : !r ? `Nomor ${what} ${C[c].name} tidak sesuai pola, contoh ${C[c].dial} ${inp.placeholder}` : d.length < r[2] ? `Terlalu pendek: minimal ${r[2]} digit setelah ${C[c].dial}` : '');
      };
      inp.addEventListener('input', upd); inp.addEventListener('blur', check);
      $$(box, '.dl-dd__item').forEach(it => it.addEventListener('click', () => { upd(); if (last.d) check(); }));
      if (hid.value) inp.value = hid.value;
      upd();
    }
  }
  Devlib.define("Phone", Phone);
})(window.Devlib);
