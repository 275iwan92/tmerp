/* DevLib · Forgot */
(function (Devlib) {
  'use strict';
  const {$, $$, hasValue, at, authLang, fieldMsg, val, passCheck, emit} = Devlib.h;

  class Forgot extends Devlib.Component {
    static selector = "[data-dl-forgot]";
    init() {
      const root = this.el;
      const form = $(root, '[data-fg-form]'), alertBox = $(root, '[data-dl-alert]'), mode = root.dataset.mode;
      let cur = '1';
      const step = n => { cur = String(n); $$(root, '[data-fg-step]').forEach(s => { s.hidden = s.dataset.fgStep !== cur; }); $$(root, '[data-fg-dot]').forEach(d => { d.classList.toggle('is-active', d.dataset.fgDot === cur); d.classList.toggle('is-done', cur === 'done' || +d.dataset.fgDot < +cur); }); $(root, `[data-fg-step="${cur}"] input:not([type=hidden])`)?.focus(); };
      const fail = m => { if (alertBox) { alertBox.textContent = m || at('f_wrong'); alertBox.classList.add('is-show'); } };
      authLang(root);
      root.addEventListener('input', e => { if (/^new_password/.test(e.target.name)) passCheck(root, 'new_password', 'new_password_confirmation'); });
      form.addEventListener('submit', e => {
        if (form.getAttribute('action') && mode === 'email') return;
        e.preventDefault();
        if (alertBox) alertBox.classList.remove('is-show');
        const box = $(root, `[data-fg-step="${cur}"]`), req = $$(box, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
        $$(box, '.dl-field').forEach(f => fieldMsg(f, ''));
        if (req.length) { req.forEach(f => fieldMsg(f, at('need_fill'), false)); return; }
        if (mode === 'email') { if (emit(root, 'dl:forgot', {email: val(root, 'email')})) step('done'); return; }
        if (cur === '1') { const answers = {}; ['login', 'birth_date', 'phone', 'mother_name', 'security_answer'].forEach(k => { answers[k] = val(root, k); }); if (emit(root, 'dl:recover', {answers})) step(2); return; }
        if (cur === '2') { if (!passCheck(root, 'new_password', 'new_password_confirmation')) return; if (emit(root, 'dl:reset', {password: val(root, 'new_password')})) step('done'); }
      });
      root.dlForgot = {step, fail};
    }
  }
  Devlib.define("Forgot", Forgot);
})(window.Devlib);
