/* MockupParts · Sales · Revisi v2 — centang scope membuka blok isian yang sama (fieldset[data-rv-block]); blok lain terkunci.
   Server tetap hanya menerapkan scope terpilih (InquiryDerivedWriter). */
(function () {
  'use strict';
  const sync = form => {
    if (form.dataset.locked === '1') return;
    const on = [...form.querySelectorAll('[data-rv-scope] input[type=checkbox]:checked')].map(x => x.value);
    form.querySelectorAll('fieldset[data-rv-block]').forEach(f => { f.disabled = !on.includes(f.dataset.rvBlock); f.classList.toggle('is-on', !f.disabled); });
  };
  document.addEventListener('change', e => { const f = e.target.closest('[data-rv-form]'); if (f && e.target.closest('[data-rv-scope]')) sync(f); });
  const boot = () => document.querySelectorAll('[data-rv-form]').forEach(sync);
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
})();
