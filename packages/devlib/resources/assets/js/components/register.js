/* DevLib · Register */
(function (Devlib) {
  'use strict';
  const {$, $$, hasValue, at, authLang, fieldMsg, fld, val, passCheck, emit} = Devlib.h;

  class Register extends Devlib.Component {
    static selector = "[data-dl-register]";
    init() {
      this.form = $(this.el, '[data-rg-form]');
      this.alertBox = $(this.el, '[data-dl-alert]');
      this.ok = {email: false, wa: false};
      this.idField = $(this.el, '[data-rg-idno]');
      this.lang = authLang(this.el);
      document.addEventListener('dl:lang', this.idLabel);
      this.el.addEventListener('input', e => {
        const n = e.target.name;
        if (n === 'email') { $(this.el, '[name=username]').value = (e.target.value.split('@')[0] || '').toLowerCase().replace(/[^a-z0-9._-]/g, ''); this.ok.email = false; $(this.el, '[data-rg-verify-email]').hidden = false; fieldMsg(fld(this.el, 'email'), ''); }
        if (n === 'id_no') this.chkId();
        if (n === 'password' || n === 'password_confirmation') passCheck(this.el, 'password', 'password_confirmation');
        if (e.target.closest('[data-dl-field="whatsapp"]') && this.ok.wa) { this.ok.wa = false; $(this.el, '[data-rg-send-otp]').hidden = false; fieldMsg(fld(this.el, 'whatsapp'), ''); }
      });
      this.el.addEventListener('change', e => { if (e.target.name === 'reg_type') this.idLabel(); });
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-rg-verify-email]')) {
          const em = val(this.el, 'email');
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return fieldMsg(fld(this.el, 'email'), at('email_bad'), false);
          if (emit(this.el, 'dl:verify-email', {email: em})) this.verified('email', true);
        }
        if (e.target.closest('[data-rg-send-otp]')) {
          const ph = val(this.el, 'whatsapp');
          if (!ph) return fieldMsg(fld(this.el, 'whatsapp'), at('wa_bad'), false);
          if (emit(this.el, 'dl:send-otp', {phone: ph})) { $(this.el, '[data-rg-otp]').hidden = false; fieldMsg(fld(this.el, 'whatsapp'), at('otp_sent'), true); $(this.el, '[data-rg-otp] input:not([type=hidden])')?.focus(); }
        }
        if (e.target.closest('[data-rg-verify-otp]')) {
          const code = val(this.el, 'otp');
          if (code.length < 4) return fieldMsg(fld(this.el, 'otp'), at('otp_bad'), false);
          fieldMsg(fld(this.el, 'otp'), '');
          if (emit(this.el, 'dl:verify-otp', {phone: val(this.el, 'whatsapp'), code})) this.verified('wa', true);
        }
      });
      this.form.addEventListener('submit', e => {
        const req = $$(this.el, '[data-rg-box] .dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
        req.forEach(f => fieldMsg(f, at('need_fill'), false));
        const idOk = this.internal() ? !!val(this.el, 'id_no') : val(this.el, 'id_no').replace(/\D/g, '').length === 16;
        const passOk = passCheck(this.el, 'password', 'password_confirmation');
        if (req.length || !idOk || !passOk) { e.preventDefault(); if (!idOk) this.chkId(); return this.alert(at('need_fill')); }
        if (!this.ok.email || !this.ok.wa) { e.preventDefault(); return this.alert(at('need_verify')); }
        this.alert('');
        const data = {}; new FormData(this.form).forEach((v, k) => { if (!/_token|password/.test(k)) data[k] = v; });
        if (!emit(this.el, 'dl:register', {data}) || this.form.getAttribute('action')) { if (!this.form.getAttribute('action')) e.preventDefault(); return; }
        e.preventDefault(); $(this.el, '[data-rg-box]').hidden = true; $(this.el, '[data-rg-done]').hidden = false;
      });
      this.el.dlRegister = {verified: this.verified, done: () => { $(this.el, '[data-rg-box]').hidden = true; $(this.el, '[data-rg-done]').hidden = false; }};
      this.idLabel();this.lang();
    }
    alert(m) { if (!this.alertBox) return; this.alertBox.textContent = m; this.alertBox.classList.toggle('is-show', !!m); }

    internal() {
      return val(this.el, 'reg_type') === 'internal';
    }

    idLabel() { $(this.idField, '.dl-field__label').textContent = at(this.internal() ? 'nrp' : 'nik'); $(this.idField, 'input').placeholder = this.internal() ? '—' : '16 digit'; this.chkId(); }
    chkId() { const v = val(this.el, 'id_no'); if (!v || this.internal()) return fieldMsg(this.idField, ''); const good = v.replace(/\D/g, '').length === 16; fieldMsg(this.idField, at(good ? 'nik_ok' : 'nik_bad'), good); }

    verified(kind, good, msg) {
      this.ok[kind] = good;
      if (kind === 'email') { fieldMsg(fld(this.el, 'email'), msg || at(good ? 'email_sent' : 'email_bad'), good); $(this.el, '[data-rg-verify-email]').hidden = good; }
      else { fieldMsg(fld(this.el, 'whatsapp'), msg || at(good ? 'wa_ok' : 'otp_bad'), good); if (good) { $(this.el, '[data-rg-otp]').hidden = true; $(this.el, '[data-rg-send-otp]').hidden = true; } }
    }
  }
  Devlib.define("Register", Register);
})(window.Devlib);
