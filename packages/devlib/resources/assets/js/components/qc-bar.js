/* DevLib · QcBar — Approved / Need improvement dengan catatan, per tahap (x-dl::qc-bar) */
(function (Devlib) {
  'use strict';
  const {$} = Devlib.h;

  class QcBar extends Devlib.Component {
    static selector = "[data-dl-qc]";
    init() {
      this.dlg = $(this.el, '[data-qc-dialog]');
      this.note = $(this.el, '[data-qc-note]');
      this.err = $(this.el, '[data-qc-err]');
      this.el.addEventListener('click', e => {
        if (e.target.closest('[data-qc-ok]')) this.send('APPROVED', '');
        else if (e.target.closest('[data-qc-improve]')) this.open();
        else if (e.target.closest('[data-qc-cancel]') || e.target === this.dlg) this.close();
        else if (e.target.closest('[data-qc-save]')) this.saveNote();
      });
      this.note.addEventListener('input', () => { this.err.textContent = ''; });
    }
    open() { this.dlg.classList.add('is-on'); setTimeout(() => this.note.focus(), 50); }
    close() { this.dlg.classList.remove('is-on'); this.err.textContent = ''; }
    saveNote() {
      const v = this.note.value.trim();
      if (!v) { this.err.textContent = this.el.dataset.tEmpty; return; }
      this.send('NEEDS_IMPROVEMENT', v);
    }
    send(status, note) {
      const d = this.el.dataset;
      if (!d.action) return;
      fetch(d.action, {method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': d.csrf || ''},
        body: JSON.stringify({key: d.key, name: d.name, stage: d.stage, status, note})})
        .then(r => r.ok ? r.json() : Promise.reject(r.status))
        .then(j => {
          const ok = status === 'APPROVED';
          this.el.classList.toggle('is-approved', ok); this.el.classList.toggle('is-improve', !ok);
          $(this.el, '[data-qc-state]').innerHTML = ok
            ? '<i class="fa-solid fa-circle-check"></i> ' + d.tApproved : '<i class="fa-solid fa-pen-ruler"></i> ' + d.tImprove;
          $(this.el, '[data-qc-meta]').textContent = (j.meta || '') + (ok ? '' : ' · ' + note);
          const here = $(this.el, '.dl-qc__chip.is-here');
          if (here) { here.classList.toggle('is-approved', ok); here.classList.toggle('is-improve', !ok); }
          this.note.value = ''; this.close();
          Devlib.toast(d.tSaved, 'success');
        })
        .catch(() => Devlib.toast(d.tFailed, 'danger'));
    }
  }
  Devlib.define("QcBar", QcBar);
})(window.Devlib);
