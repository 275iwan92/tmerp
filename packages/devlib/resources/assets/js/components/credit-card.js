/* DevLib · CreditCard */
(function (Devlib) {
  'use strict';
  const {$, CASES, CASE_LABEL, applyCase, TZ, fieldErr, CC, luhn} = Devlib.h;

  class CreditCard extends Devlib.Component {
    static selector = "[data-dl-cc]";
    init() {
      const box = this.el;
      const num = $(box, '[data-dl-cc-num]'), exp = $(box, '[data-dl-cc-exp]'), cvv = $(box, '[data-dl-cc-cvv]'), hol = $(box, '[data-dl-cc-holder]');
      const q = s => $(box, s), brandIn = q('[data-dl-cc-brand]');
      let brand = null, caseIdx = 2;
      const upd = () => {
        const d = num.value.replace(/\D/g, '');
        brand = CC.find(b => b[1].test(d)) || null;
        const groups = brand ? brand[2] : [4, 4, 4, 4, 3], maxLen = brand ? Math.max(...brand[3]) : 19, dd = d.slice(0, maxLen);
        let out = [], k = 0; for (const g of groups) { if (k >= dd.length) break; out.push(dd.slice(k, k + g)); k += g; }
        num.value = out.join(' ');
        brandIn.value = brand ? brand[0] : '';
        q('[data-dl-cc-icon]').className = (brand ? brand[5] : 'fa-regular fa-credit-card') + ' dl-cc__icon';
        q('[data-dl-cc-brandname]').textContent = brand ? brand[6] : '';
        cvv.maxLength = brand ? brand[4] : 3; cvv.placeholder = '•'.repeat(cvv.maxLength);
        const card = q('[data-dl-cc-card]');
        if (card) {
          card.dataset.brand = brand ? brand[0] : '';
          q('[data-dl-cc-logo]').className = (brand ? brand[5] : 'fa-solid fa-credit-card') + ' dl-cc__logo';
          const mask = groups.map((g, i) => { const part = dd.slice(groups.slice(0, i).reduce((a, b) => a + b, 0)).slice(0, g); return part.padEnd(g, '•'); });
          let used = 0; const shownGroups = mask.filter((_, i) => { const st = used; used += groups[i]; return st < Math.max(dd.length, (brand ? Math.min(...brand[3]) : 16)); });
          q('[data-dl-cc-shown]').textContent = shownGroups.join(' ');
          q('[data-dl-cc-exp-shown]').textContent = exp.value || 'MM/YY';
          if (hol) q('[data-dl-cc-holder-shown]').textContent = hol.value.toUpperCase() || 'NAMA PEMEGANG';
        }
      };
      const check = () => {
        const d = num.value.replace(/\D/g, ''), msgs = [];
        if (d) {
          if (!brand) msgs.push('Jenis kartu tidak dikenal');
          else if (!brand[3].includes(d.length)) msgs.push(`Nomor ${brand[6]} harus ${brand[3].join(' / ')} digit`);
          else if (!luhn(d)) msgs.push('Nomor kartu tidak valid (cek kembali angkanya)');
        }
        const m = exp.value.match(/^(\d{2})\/(\d{2})$/);
        if (exp.value && !m) msgs.push('Masa berlaku harus MM/YY');
        else if (m) {
          const now = new Date(new Date().toLocaleString('en-US', {timeZone: TZ()})), mm = +m[1], yy = 2000 + +m[2];
          if (mm < 1 || mm > 12) msgs.push('Bulan berlaku 01–12');
          else if (yy < now.getFullYear() || yy === now.getFullYear() && mm < now.getMonth() + 1) msgs.push('Kartu sudah kedaluwarsa');
        }
        if (cvv.value && cvv.value.length !== cvv.maxLength) msgs.push(`CVV harus ${cvv.maxLength} digit`);
        fieldErr(box, msgs.join(' · '));
      };
      num.addEventListener('input', upd);
      exp.addEventListener('input', e => {
        let d = exp.value.replace(/\D/g, '').slice(0, 4);
        if (d.length === 1 && d > '1') d = '0' + d;
        exp.value = d.length > 2 || (d.length === 2 && e.inputType !== 'deleteContentBackward') ? d.slice(0, 2) + '/' + d.slice(2) : d;
        upd();
      });
      cvv.addEventListener('input', () => { cvv.value = cvv.value.replace(/\D/g, '').slice(0, cvv.maxLength); });
      if (hol) {
        const cb = q('[data-dl-cc-case]');
        cb.addEventListener('click', () => { caseIdx = (caseIdx + 1) % 3; cb.textContent = CASE_LABEL[CASES[caseIdx]]; hol.value = applyCase(hol.value, CASES[caseIdx]); upd(); });
        hol.addEventListener('input', () => { const p = hol.selectionStart; hol.value = applyCase(hol.value, CASES[caseIdx]); try { hol.setSelectionRange(p, p); } catch (e) {} upd(); });
      }
      [num, exp, cvv].forEach(i => i.addEventListener('blur', check));
      upd();
    }
  }
  Devlib.define("CreditCard", CreditCard);
})(window.Devlib);
