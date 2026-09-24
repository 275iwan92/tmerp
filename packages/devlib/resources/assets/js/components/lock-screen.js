/* DevLib · LockScreen */
(function (Devlib) {
  'use strict';
  const {$, TZ, fmt, fieldMsg} = Devlib.h;
  /* ============================ LOCK SCREEN ============================ */
  class LockScreen extends Devlib.Component {
    static selector = "[data-dl-lockscreen]";
    init() {
      const el = this.el;
      const key = 'dl-lock-' + el.dataset.id, idle = +el.dataset.idle || 0, form = $(el, '[data-lk-form]'), pw = $(el, '[name=unlock_password]');
      let timer, clock;
      const tick = () => { const d = new Date(); $(el, '[data-lk-time]').textContent = d.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ()}).replace('.', ':'); $(el, '[data-lk-date]').textContent = fmt.date(d, 'long'); };
      const store = v => { try { v ? sessionStorage.setItem(key, '1') : sessionStorage.removeItem(key); } catch (e) {} };
      function lock() { if (!el.hidden) return; el.hidden = false; document.documentElement.classList.add('dl-locked'); store(true); tick(); clock = setInterval(tick, 10000); pw.value = ''; fieldMsg(pw.closest('.dl-field'), ''); setTimeout(() => pw.focus(), 50); el.dispatchEvent(new CustomEvent('dl:lock', {bubbles: true})); }
      function unlock() { el.hidden = true; document.documentElement.classList.remove('dl-locked'); store(false); clearInterval(clock); arm(); el.dispatchEvent(new CustomEvent('dl:unlocked', {bubbles: true})); }
      const fail = m => { fieldMsg(pw.closest('.dl-field'), m || 'Password salah.', false); pw.select(); };
      function arm() { clearTimeout(timer); if (idle > 0 && el.hidden) timer = setTimeout(lock, idle * 60000); }
      ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(n => document.addEventListener(n, () => { if (el.hidden) arm(); }, {passive: true}));
      document.addEventListener('keydown', e => { if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'l') { e.preventDefault(); lock(); } });
      document.addEventListener('click', e => { if (e.target.closest('[data-dl-lock]')) lock(); });
      form.addEventListener('submit', e => {
        if (e.submitter?.hasAttribute('data-lk-out')) { store(false); return; }
        e.preventDefault();
        if (!pw.value) return fail('Isi password.');
        if (el.dispatchEvent(new CustomEvent('dl:unlock', {bubbles: true, cancelable: true, detail: {password: pw.value}}))) unlock();
      });
      el.dlLock = {lock, unlock, fail};
      try { if (sessionStorage.getItem(key)) lock(); } catch (e) {}
      arm();
    }
  }
  Devlib.define("LockScreen", LockScreen);
})(window.Devlib);
