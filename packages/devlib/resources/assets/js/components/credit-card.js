/* DevLib · CreditCard */
(function (Devlib) {
  'use strict';
  const {$, CASES, CASE_LABEL, applyCase, TZ, fieldErr, CC, luhn, T, Tf} = Devlib.h;

  class CreditCard extends Devlib.Component {
    static selector = "[data-dl-cc]";
    init() {
      this.num = $(this.el, '[data-dl-cc-num]');
      this.exp = $(this.el, '[data-dl-cc-exp]');
      this.cvv = $(this.el, '[data-dl-cc-cvv]');
      this.hol = $(this.el, '[data-dl-cc-holder]');
      this.q = s => $(this.el, s);
      this.brandIn = this.q('[data-dl-cc-brand]');
      this.brand = null;
      this.caseIdx = 2;
      this.num.addEventListener('input', this.upd);
      this.exp.addEventListener('input', e => {
        let d = this.exp.value.replace(/\D/g, '').slice(0, 4);
        if (d.length === 1 && d > '1') d = '0' + d;
        this.exp.value = d.length > 2 || (d.length === 2 && e.inputType !== 'deleteContentBackward') ? d.slice(0, 2) + '/' + d.slice(2) : d;
        this.upd();
      });
      this.cvv.addEventListener('input', () => { this.cvv.value = this.cvv.value.replace(/\D/g, '').slice(0, this.cvv.maxLength); });
      if (this.hol) {
        const cb = this.q('[data-dl-cc-case]');
        cb.addEventListener('click', () => { this.caseIdx = (this.caseIdx + 1) % 3; cb.textContent = CASE_LABEL[CASES[this.caseIdx]]; this.hol.value = applyCase(this.hol.value, CASES[this.caseIdx]); this.upd(); });
        this.hol.addEventListener('input', () => { const p = this.hol.selectionStart; this.hol.value = applyCase(this.hol.value, CASES[this.caseIdx]); try { this.hol.setSelectionRange(p, p); } catch (e) {} this.upd(); });
      }
      [this.num, this.exp, this.cvv].forEach(i => i.addEventListener('blur', this.check));
      this.upd();
    }

    upd() {
      const d = this.num.value.replace(/\D/g, '');
      this.brand = CC.find(b => b[1].test(d)) || null;
      const groups = this.brand ? this.brand[2] : [4, 4, 4, 4, 3], maxLen = this.brand ? Math.max(...this.brand[3]) : 19, dd = d.slice(0, maxLen);
      let out = [], k = 0; for (const g of groups) { if (k >= dd.length) break; out.push(dd.slice(k, k + g)); k += g; }
      this.num.value = out.join(' ');
      this.brandIn.value = this.brand ? this.brand[0] : '';
      this.q('[data-dl-cc-icon]').className = (this.brand ? this.brand[5] : 'fa-regular fa-credit-card') + ' dl-cc__icon';
      this.q('[data-dl-cc-brandname]').textContent = this.brand ? this.brand[6] : '';
      this.cvv.maxLength = this.brand ? this.brand[4] : 3; this.cvv.placeholder = '•'.repeat(this.cvv.maxLength);
      const card = this.q('[data-dl-cc-card]');
      if (card) {
        card.dataset.brand = this.brand ? this.brand[0] : '';
        this.q('[data-dl-cc-logo]').className = (this.brand ? this.brand[5] : 'fa-solid fa-credit-card') + ' dl-cc__logo';
        const mask = groups.map((g, i) => { const part = dd.slice(groups.slice(0, i).reduce((a, b) => a + b, 0)).slice(0, g); return part.padEnd(g, '•'); });
        let used = 0; const shownGroups = mask.filter((_, i) => { const st = used; used += groups[i]; return st < Math.max(dd.length, (this.brand ? Math.min(...this.brand[3]) : 16)); });
        this.q('[data-dl-cc-shown]').textContent = shownGroups.join(' ');
        this.q('[data-dl-cc-exp-shown]').textContent = this.exp.value || 'MM/YY';
        if (this.hol) this.q('[data-dl-cc-holder-shown]').textContent = this.hol.value.toUpperCase() || T('cc_holder');
      }
    }

    check() {
      const d = this.num.value.replace(/\D/g, ''), msgs = [];
      if (d) {
        if (!this.brand) msgs.push(T('cc_unknown'));
        else if (!this.brand[3].includes(d.length)) msgs.push(Tf('cc_digits', {brand: this.brand[6], n: this.brand[3].join(' / ')}));
        else if (!luhn(d)) msgs.push(T('cc_invalid'));
      }
      const m = this.exp.value.match(/^(\d{2})\/(\d{2})$/);
      if (this.exp.value && !m) msgs.push(T('cc_exp_format'));
      else if (m) {
        const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()})), mm = +m[1], yy = 2000 + +m[2];
        if (mm < 1 || mm > 12) msgs.push(T('cc_exp_month'));
        else if (yy < now.getFullYear() || yy === now.getFullYear() && mm < now.getMonth() + 1) msgs.push(T('cc_expired'));
      }
      if (this.cvv.value && this.cvv.value.length !== this.cvv.maxLength) msgs.push(Tf('cc_cvv', {n: this.cvv.maxLength}));
      fieldErr(this.el, msgs.join(' · '));
    }
  }
  Devlib.define("CreditCard", CreditCard);
})(window.Devlib);
