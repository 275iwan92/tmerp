/* DevLib · Register */
(function (Devlib) {
  'use strict';
  const {$, $$, hasValue, at, authLang, fieldMsg, fld, val, passCheck, emit} = Devlib.h;

  class Register extends Devlib.Component {
    static selector = "[data-dl-register]";
    init() {
      const root = this.el;
      const form = $(root, '[data-rg-form]'), alertBox = $(root, '[data-dl-alert]');
      const ok = {email: false, wa: false};
      const alert = m => { if (!alertBox) return; alertBox.textContent = m; alertBox.classList.toggle('is-show', !!m); };
      const internal = () => val(root, 'reg_type') === 'internal';
      const idField = $(root, '[data-rg-idno]');
      function idLabel() { $(idField, '.dl-field__label').textContent = at(internal() ? 'nrp' : 'nik'); $(idField, 'input').placeholder = internal() ? '—' : '16 digit'; chkId(); }
      function chkId() { const v = val(root, 'id_no'); if (!v || internal()) return fieldMsg(idField, ''); const good = v.replace(/\D/g, '').length === 16; fieldMsg(idField, at(good ? 'nik_ok' : 'nik_bad'), good); }
      function verified(kind, good, msg) {
        ok[kind] = good;
        if (kind === 'email') { fieldMsg(fld(root, 'email'), msg || at(good ? 'email_sent' : 'email_bad'), good); $(root, '[data-rg-verify-email]').hidden = good; }
        else { fieldMsg(fld(root, 'whatsapp'), msg || at(good ? 'wa_ok' : 'otp_bad'), good); if (good) { $(root, '[data-rg-otp]').hidden = true; $(root, '[data-rg-send-otp]').hidden = true; } }
      }
      const lang = authLang(root); document.addEventListener('dl:lang', idLabel);
      root.addEventListener('input', e => {
        const n = e.target.name;
        if (n === 'email') { $(root, '[name=username]').value = (e.target.value.split('@')[0] || '').toLowerCase().replace(/[^a-z0-9._-]/g, ''); ok.email = false; $(root, '[data-rg-verify-email]').hidden = false; fieldMsg(fld(root, 'email'), ''); }
        if (n === 'id_no') chkId();
        if (n === 'password' || n === 'password_confirmation') passCheck(root, 'password', 'password_confirmation');
        if (e.target.closest('[data-dl-field="whatsapp"]') && ok.wa) { ok.wa = false; $(root, '[data-rg-send-otp]').hidden = false; fieldMsg(fld(root, 'whatsapp'), ''); }
      });
      root.addEventListener('change', e => { if (e.target.name === 'reg_type') idLabel(); });
      root.addEventListener('click', e => {
        if (e.target.closest('[data-rg-verify-email]')) {
          const em = val(root, 'email');
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) return fieldMsg(fld(root, 'email'), at('email_bad'), false);
          if (emit(root, 'dl:verify-email', {email: em})) verified('email', true);
        }
        if (e.target.closest('[data-rg-send-otp]')) {
          const ph = val(root, 'whatsapp');
          if (!ph) return fieldMsg(fld(root, 'whatsapp'), at('wa_bad'), false);
          if (emit(root, 'dl:send-otp', {phone: ph})) { $(root, '[data-rg-otp]').hidden = false; fieldMsg(fld(root, 'whatsapp'), at('otp_sent'), true); $(root, '[data-rg-otp] input:not([type=hidden])')?.focus(); }
        }
        if (e.target.closest('[data-rg-verify-otp]')) {
          const code = val(root, 'otp');
          if (code.length < 4) return fieldMsg(fld(root, 'otp'), at('otp_bad'), false);
          fieldMsg(fld(root, 'otp'), '');
          if (emit(root, 'dl:verify-otp', {phone: val(root, 'whatsapp'), code})) verified('wa', true);
        }
      });
      form.addEventListener('submit', e => {
        const req = $$(root, '[data-rg-box] .dl-field').filter(f => $(f, '.dl-field__req') && !hasValue(f));
        req.forEach(f => fieldMsg(f, at('need_fill'), false));
        const idOk = internal() ? !!val(root, 'id_no') : val(root, 'id_no').replace(/\D/g, '').length === 16;
        const passOk = passCheck(root, 'password', 'password_confirmation');
        if (req.length || !idOk || !passOk) { e.preventDefault(); if (!idOk) chkId(); return alert(at('need_fill')); }
        if (!ok.email || !ok.wa) { e.preventDefault(); return alert(at('need_verify')); }
        alert('');
        const data = {}; new FormData(form).forEach((v, k) => { if (!/_token|password/.test(k)) data[k] = v; });
        if (!emit(root, 'dl:register', {data}) || form.getAttribute('action')) { if (!form.getAttribute('action')) e.preventDefault(); return; }
        e.preventDefault(); $(root, '[data-rg-box]').hidden = true; $(root, '[data-rg-done]').hidden = false;
      });
      root.dlRegister = {verified, done: () => { $(root, '[data-rg-box]').hidden = true; $(root, '[data-rg-done]').hidden = false; }};
      idLabel(); lang();
    }
  }
  Devlib.define("Register", Register);
})(window.Devlib);
