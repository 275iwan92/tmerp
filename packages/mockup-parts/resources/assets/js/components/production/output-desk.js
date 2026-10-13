/*
 * REMARK
 * Tujuan       : Perilaku layar Production Output: timer SETUP / PRODUCTION berdetak, estimasi hasil, Play / Pause / Stop, zoom Master Card, stepper, TOTAL GOOD + BAD, pop-up alasan BAD (multi baris) dan SIMPAN HASIL.
 * Sumber hulu  : data-desk dari OutputDesk (ProductionOutput::desk); aksi POST ke v2.ppic.output.timer / output.result.
 * Input        : Klik tombol timer, stepper, SIMPAN HASIL, baris job, gambar dieline.
 * Output       : Tampilan terbarui dari jawaban server (tidak menyimpan apa pun di browser).
 * Dipakai oleh : x-mp::production.output-desk.
 * Status       : Aktif di mockup (ddt*).
 */
/* MockupParts · OutputDesk */
(function (Devlib) {
  'use strict';
  const {$, $$} = Devlib.h;
  const T = s => Devlib.h.t ? Devlib.h.t(s) : s;
  const p2 = n => String(n).padStart(2, '0');
  const hms = s => { s = Math.floor(s); return [Math.floor(s / 3600), Math.floor(s / 60) % 60, s % 60].map(p2).join(':'); };
  // dd-Mmm-yyyy hh:mm:ss (month names follow the language, like every date of the app).
  const stamp = iso => { const d = new Date(iso); return Devlib.fmt.date(d) + ' ' + [d.getHours(), d.getMinutes(), d.getSeconds()].map(p2).join(':'); };
  const num = n => Devlib.fmt.number(n, 0);
  const STATUS = {idle: 'Waiting', setup: 'Setup', prod: 'Production', pause: 'Paused', done: 'Done'};
  const STATE = {idle: '', run: 'running', pause: 'paused', stop: 'stopped'};

  // Same rule as OperatorTimerService::statusKey on the server.
  function statusKey(t) {
    const a = t.setup.state, b = t.prod.state;
    if (a === 'run') return 'setup';
    if (b === 'run') return 'prod';
    if (b === 'stop') return 'done';
    if (a === 'pause' || b === 'pause' || a === 'stop') return 'pause';
    return 'idle';
  }

  class OutputDesk extends Devlib.Component {
    static selector = '[data-mp-output]';
    init() {
      this.desk = JSON.parse(this.el.dataset.desk);
      this.t = this.desk.timers; this.saved = this.desk.saved; this.at = Date.now();
      this.on('click', '[data-od-act]', (e, b) => this.act(b.closest('[data-od-timer]').dataset.odTimer, b.dataset.odAct));
      this.on('click', '[data-od-step]', (e, b) => { const i = $(this.el, `[data-od-in="${b.dataset.odStep}"]`); i.value = Math.max(0, (+i.value || 0) + +b.dataset.d); this.total(); });
      this.on('click', '[data-od-save]', () => this.save());
      this.on('input', '[data-od-in]', () => this.total());
      this.on('click', '[data-od-bad-add]', () => this.badRow());
      this.on('click', '[data-od-bad-rm]', (e, b) => { b.closest('.mp-od__badrow').remove(); this.badSum(); });
      this.on('input', '[data-od-bad-qty]', () => this.badSum());
      this.on('click', '[data-od-job]', (e, r) => { location.href = r.dataset.odJob; });
      this.on('keydown', '[data-od-job]', (e, r) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); r.click(); } });
      const layer = $(this.el, '[data-od-zoomlayer]');
      this.on('click', '[data-od-zoom]', (e, img) => { $(layer, 'img').src = img.src; layer.hidden = false; });
      layer.addEventListener('click', () => { layer.hidden = true; });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') layer.hidden = true; });
      this.draw();
      this.timer = setInterval(() => this.draw(), 500);
    }

    // Seconds of one timer now: what the server counted plus the time since it answered while the timer runs.
    secs(k) { const x = this.t[k]; return x.seconds + (x.state === 'run' ? (Date.now() - this.at) / 1000 : 0); }

    // Estimated output = min(target qty, target per hour x production seconds / 3600); counted, never stored.
    estimate() {
      const g = this.desk.target;
      return g.per_hour ? Math.min(g.qty, Math.floor(g.per_hour * this.secs('prod') / 3600)) : 0;
    }

    draw() {
      const g = this.desk.target, u = g.uom;
      $(this.el, '[data-od="perhour"]').textContent = g.per_hour ? num(g.per_hour) : '—';
      $(this.el, '[data-od="perhour-u"]').textContent = u + T('/hour');
      $(this.el, '[data-od="qty"]').textContent = num(g.qty);
      $(this.el, '[data-od="duration"]').textContent = g.duration_min == null ? '—' : `${Math.floor(g.duration_min / 60)} ${T('h')} ${p2(g.duration_min % 60)} ${T('min')}`;
      $$(this.el, '[data-od-timer]').forEach(box => {
        const k = box.dataset.odTimer, x = this.t[k];
        box.classList.toggle('is-on', x.state === 'run');
        $(box, '[data-od="clock"]').textContent = hms(this.secs(k));
        $(box, '[data-od="state"]').textContent = STATE[x.state] ? T(STATE[x.state]) : '';
        const est = k === 'prod' ? `<span class="mp-od__est">${Devlib.h.esc(T('Estimated output'))} : <b>${num(this.estimate())}</b> ${Devlib.h.esc(u)}</span>` : '';
        $(box, '[data-od="times"]').innerHTML = `<span>Start : ${x.first ? stamp(x.first) : '—'}  ·  Stop : ${x.stop ? stamp(x.stop) : '—'}</span>` + est;
        const [play, pause, stop] = $$(box, '[data-od-act]');
        play.disabled = x.state === 'run' || x.state === 'stop'; pause.disabled = x.state !== 'run'; stop.disabled = x.state === 'stop' || x.state === 'idle';
      });
      const cur = $(this.el, '.mp-od__row.is-sel');
      if (cur) {
        const s = statusKey(this.t);
        cur.dataset.odStatus = s;
        $(cur, '.mp-od__dot').className = 'mp-od__dot mp-od__dot--' + s;
      }
      $$(this.el, '.mp-od__row').forEach(r => { $(r, '[data-od="job-status"]').textContent = T(STATUS[r.dataset.odStatus]); });
      const {good, bad} = this.saved, tot = good + bad;
      $(this.el, '[data-od="sum"]').textContent = T('Saved: good :g · bad :b · progress :p%').replace(':g', num(good)).replace(':b', num(bad)).replace(':p', (good / g.qty * 100).toFixed(1))
        + (tot ? ' · ' + T('yield :y%').replace(':y', (good / tot * 100).toFixed(1)) : '');
    }

    async post(url, body) {
      const r = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.DL_CSRF || document.querySelector('meta[name="csrf-token"]')?.content || ''}, body: JSON.stringify(body)});
      const j = await r.json().catch(() => ({}));
      if (!r.ok) { Devlib.toast(j.error || j.message || r.statusText, 'danger'); return null; }
      return j;
    }

    async act(timer, action) {
      const j = await this.post(this.desk.urls.timer, {timer, action});
      if (j) { this.t = j.timers; this.at = Date.now(); this.draw(); }
    }

    // TOTAL, share bar and the reason panel follow the two inputs live.
    total() {
      const g = +$(this.el, '[data-od-in="good"]').value || 0, b = +$(this.el, '[data-od-in="bad"]').value || 0, t = g + b;
      $(this.el, '[data-od="total"]').textContent = num(t);
      const pg = t ? g / t * 100 : 0, pb = t ? b / t * 100 : 0;
      $(this.el, '[data-od="bar-good"]').style.width = pg + '%';
      $(this.el, '[data-od="bar-bad"]').style.width = pb + '%';
      $(this.el, '[data-od="pct-good"]').textContent = `GOOD ${pg.toFixed(1)}%`;
      $(this.el, '[data-od="pct-bad"]').textContent = `BAD ${pb.toFixed(1)}%`;
      this.reasons(b);
    }

    // BAD > 0 opens the reason panel under the BAD box; a single row follows BAD until the operator splits it.
    reasons(bad) {
      const panel = $(this.el, '[data-od-reasons]'), rows = $$(this.el, '.mp-od__badrow');
      panel.hidden = !bad;
      $(this.el, '[data-od="bad-want"]').textContent = num(bad);
      if (!bad) return;
      if (!rows.length) this.badRow(bad);
      else if (rows.length === 1) $(rows[0], '[data-od-bad-qty]').value = bad;
      this.badSum();
    }

    badRow(qty = 0) {
      const opts = Object.entries(this.desk.reasons || {}).map(([id, n]) => `<option value="${id}">${Devlib.h.esc(n)}</option>`).join('');
      const row = document.createElement('div');
      row.className = 'mp-od__badrow';
      row.innerHTML = `<select data-od-bad-reason>${opts}</select><input type="number" min="0" step="any" inputmode="numeric" data-od-bad-qty value="${qty || ''}" placeholder="${T('Qty')}"><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-od-bad-rm aria-label="${T('Remove')}">✕</button>`;
      $(this.el, '[data-od-bad-rows]').appendChild(row);
      this.badSum();
    }

    badSum() {
      const sum = $$(this.el, '[data-od-bad-qty]').reduce((a, i) => a + (+i.value || 0), 0);
      const bad = +$(this.el, '[data-od-in="bad"]').value || 0;
      const el = $(this.el, '[data-od="bad-total"]');
      el.textContent = num(sum);
      el.closest('.mp-od__total').classList.toggle('is-off', Math.abs(sum - bad) > 0.0001);
      return sum;
    }

    // SAVE RESULT: GOOD only is saved at once; with BAD the reason rows must be filled and add up to BAD.
    save() {
      const good = +$(this.el, '[data-od-in="good"]').value || 0, bad = +$(this.el, '[data-od-in="bad"]').value || 0;
      if (!good && !bad) return Devlib.toast(T('Enter GOOD or BAD first.'), 'warn');
      if (!bad) return this.send(good, 0, []);
      const rows = $$(this.el, '.mp-od__badrow').map(r => ({scrap_type_id: +$(r, '[data-od-bad-reason]').value, qty: +$(r, '[data-od-bad-qty]').value || 0}));
      if (!rows.length || rows.some(r => !r.scrap_type_id || r.qty <= 0)) return Devlib.toast(T('Choose a reason and a quantity for every BAD row.'), 'warn');
      if (Math.abs(this.badSum() - bad) > 0.0001) return Devlib.toast(T('The BAD reasons must add up to the BAD quantity.'), 'warn');
      return this.send(good, bad, rows);
    }

    async send(good, bad, defects) {
      const j = await this.post(this.desk.urls.result, {good, bad, defects});
      if (!j) return;
      this.saved = j.saved;
      $$(this.el, '[data-od-in]').forEach(i => { i.value = 0; });
      $(this.el, '[data-od-bad-rows]').innerHTML = '';
      this.total();
      Devlib.toast(T('Saved: +:g good, +:b bad for :j.').replace(':g', num(good)).replace(':b', num(bad)).replace(':j', this.desk.card.info[0][1]), 'success');
      this.draw();
    }
  }
  Devlib.define('OutputDesk', OutputDesk);
})(window.Devlib);
