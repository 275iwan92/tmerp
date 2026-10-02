/* DevLib · LockScreen */
(function (Devlib) {
  'use strict';
  const {$, TZ, fmt, fieldMsg, T} = Devlib.h;
  /* ============================ LOCK SCREEN ============================ */
  class LockScreen extends Devlib.Component {
    static selector = "[data-dl-lockscreen]";
    init() {
      this.key = 'dl-lock-' + this.el.dataset.id;
      this.idle = +this.el.dataset.idle || 0;
      this.form = $(this.el, '[data-lk-form]');
      this.pw = $(this.el, '[name=unlock_password]');
      this.timer = undefined;
      this.clock = undefined;
      ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(n => document.addEventListener(n, () => { if (this.el.hidden) this.arm(); }, {passive: true}));
      document.addEventListener('keydown', e => { if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'l') { e.preventDefault(); this.lock(); } });
      document.addEventListener('click', e => { if (e.target.closest('[data-dl-lock]')) this.lock(); });
      this.form.addEventListener('submit', e => {
        if (e.submitter?.hasAttribute('data-lk-out')) { this.store(false); return; }
        e.preventDefault();
        if (!this.pw.value) return this.fail(T('lk_enter_pw'));
        if (this.el.dispatchEvent(new CustomEvent('dl:unlock', {bubbles: true, cancelable: true, detail: {password: this.pw.value}}))) this.unlock();
      });
      this.el.dlLock = {lock: this.lock, unlock: this.unlock, fail: this.fail};
      try { if (sessionStorage.getItem(this.key)) this.lock(); } catch (e) {}
      this.arm();
    }
    tick() { const d = new Date(); $(this.el, '[data-lk-time]').textContent = d.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ()}).replace('.', ':'); $(this.el, '[data-lk-date]').textContent = fmt.date(d, 'long'); }
    store(v) { try { v ? sessionStorage.setItem(this.key, '1') : sessionStorage.removeItem(this.key); } catch (e) {} }
    lock() { if (!this.el.hidden) return; this.el.hidden = false; document.documentElement.classList.add('dl-locked'); this.store(true); this.tick(); this.clock = setInterval(this.tick, 10000); this.pw.value = ''; fieldMsg(this.pw.closest('.dl-field'), ''); setTimeout(() => this.pw.focus(), 50); this.el.dispatchEvent(new CustomEvent('dl:lock', {bubbles: true})); }
    unlock() { this.el.hidden = true; document.documentElement.classList.remove('dl-locked'); this.store(false); clearInterval(this.clock); this.arm(); this.el.dispatchEvent(new CustomEvent('dl:unlocked', {bubbles: true})); }
    fail(m) { fieldMsg(this.pw.closest('.dl-field'), m || T('lk_wrong_pw'), false); this.pw.select(); }
    arm() { clearTimeout(this.timer); if (this.idle > 0 && this.el.hidden) this.timer = setTimeout(this.lock, this.idle * 60000); }
  }
  Devlib.define("LockScreen", LockScreen);
})(window.Devlib);
