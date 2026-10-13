/*
 * REMARK
 * Tujuan       : Menyaring daftar antrean di browser menurut Process lalu Machine (dropdown bertaut).
 * Sumber hulu  : Baris yang sudah dirender server (data-q-process, data-q-machine).
 * Input        : Perubahan dropdown queue_process dan queue_machine_*.
 * Output       : Baris tampil/sembunyi, jumlah baris, pesan kosong.
 * Dipakai oleh : x-dl::queue-board.
 * Status       : Aktif (DevLib).
 */
/* DevLib · QueueBoard */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;

  class QueueBoard extends Devlib.Component {
    static selector = "[data-dl-queue]";
    init() {
      const sel0 = $(this.el, '[name="queue_process"]');
      this.proc = sel0 ? sel0.value : '';
      const shown = $(this.el, '[data-q-mach]:not([hidden]) [name^="queue_machine_"]');
      this.mach = shown ? shown.value : '';
      this.el.addEventListener('change', e => {
        const n = e.target.name || '';
        if (n === 'queue_process') {
          this.proc = e.target.value; this.mach = '';
          $$(this.el, '[data-q-mach]').forEach(w => { w.hidden = w.dataset.qMach !== this.proc; });
          const s = $(this.el, `[data-q-mach="${this.proc}"] [name^="queue_machine_"]`); if (s) s.value = '';
        } else if (n.startsWith('queue_machine_')) {
          this.mach = e.target.value;
        } else return;
        this.apply();
      });
      this.apply();
    }
    apply() {
      let n = 0;
      $$(this.el, 'tbody tr[data-q-process]').forEach(r => {
        const on = (!this.proc || r.dataset.qProcess === this.proc) && (!this.mach || r.dataset.qMachine === this.mach);
        r.hidden = !on; if (on) n++;
      });
      const e = $(this.el, '[data-q-empty]'); if (e) e.hidden = n > 0;
      const c = $(this.el, '[data-q-count]'); if (c) c.textContent = n + ' ' + (n === 1 ? 'item' : 'items');
    }
  }
  Devlib.define("QueueBoard", QueueBoard);
})(window.Devlib);
