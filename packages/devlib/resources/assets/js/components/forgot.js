/* DevLib · Forgot */
(function (Devlib) {
  'use strict';
  const {$, $$, hasValue, at, authLang, fieldMsg, val, passCheck, emit} = Devlib.h;

  class Forgot extends Devlib.Component {
    static selector = "[data-dl-forgot]";
    init() {
      this.form = $(this.el, '[data-fg-form]');
      this.alertBox = $(this.el, '[data-dl-alert]');
      this.mode = this.el.dataset.mode;
      this.cur = '1';
      authLang(this.el);
      this.el.addEventListener('input', e => { if (/^new_password/.test(e.target.name)) passCheck(this.el, 'new_password', 'new_password_confirmation'); });
      this.form.addEventListener('submit', e => {
        if (this.form.getAttribute('action') && this.mode === 'email') return;
        e.preventDefault();
        if (this.alertBox) this.alertBox.classList.remove('is-show');
        const box = $(this.el, `[data-fg-step="${this.cur}"]`), req = $$(box, '.dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
        $$(box, '.dl-field').forEach(f => fieldMsg(f, ''));
        if (req.length) { req.forEach(f => fieldMsg(f, at('need_fill'), false)); return; }
        if (this.mode === 'email') { if (emit(this.el, 'dl:forgot', {email: val(this.el, 'email')})) this.step('done'); return; }
        if (this.cur === '1') { const answers = {}; ['login', 'birth_date', 'phone', 'mother_name', 'security_answer'].forEach(k => { answers[k] = val(this.el, k); }); if (emit(this.el, 'dl:recover', {answers})) this.step(2); return; }
        if (this.cur === '2') { if (!passCheck(this.el, 'new_password', 'new_password_confirmation')) return; if (emit(this.el, 'dl:reset', {password: val(this.el, 'new_password')})) this.step('done'); }
      });
      this.el.dlForgot = {step: this.step, fail: this.fail};
    }
    step(n) { this.cur = String(n); $$(this.el, '[data-fg-step]').forEach(s => { s.hidden = s.dataset.fgStep !== this.cur; }); $$(this.el, '[data-fg-dot]').forEach(d => { d.classList.toggle('is-active', d.dataset.fgDot === this.cur); d.classList.toggle('is-done', this.cur === 'done' || +d.dataset.fgDot < +this.cur); }); $(this.el, `[data-fg-step="${this.cur}"] input:not([type=hidden])`)?.focus(); }
    fail(m) { if (this.alertBox) { this.alertBox.textContent = m || at('f_wrong'); this.alertBox.classList.add('is-show'); } }
  }
  Devlib.define("Forgot", Forgot);
})(window.Devlib);
