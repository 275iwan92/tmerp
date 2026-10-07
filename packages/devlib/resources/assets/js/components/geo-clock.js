/* DevLib · GeoClock — absen berbasis lokasi. Posisi perangkat -> server (jarak & keputusan di server) -> Absen Masuk / Pulang.
 * Server: check-url menerima {lat, lng} -> {distance, in_radius}; clock-url menerima {type, lat, lng} -> {message, tone, history}.
 * Tanpa server (demo): tanpa data-check-url jarak dihitung dari data-demo-distance. Event: dl:geo-clock {type, lat, lng}. */
(function (Devlib) {
  'use strict';
  const {esc} = Devlib.h;

  class GeoClock extends Devlib.Component {
    static selector = "[data-dl-geo]";

    init() {
      this.t = JSON.parse(this.el.dataset.t || '{}');
      this.radius = +this.el.dataset.radius || 200;
      this.pos = null;
      this.inRadius = false;
      this.buttons = this.$$('[data-geo-clock]');
      this.on('click', '[data-geo-recheck]', () => this.locate());
      this.on('click', '[data-geo-clock]', (e, b) => this.clock(b.dataset.geoClock));
      this.history(JSON.parse(this.el.dataset.history || '[]'));
      this.locate();
    }

    csrfHeaders() {
      return {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.DL_CSRF || ''};
    }

    async post(url, body) {
      const r = await fetch(url, {method: 'POST', credentials: 'same-origin', headers: this.csrfHeaders(), body: JSON.stringify(body)});
      if (!r.ok) throw new Error((await r.json().catch(() => ({}))).message || r.statusText);
      return r.json();
    }

    setMessage(text, danger = false) {
      const m = this.$('[data-geo-msg]');
      m.textContent = text;
      m.classList.toggle('is-danger', danger);
      m.hidden = !text;
    }

    locate() {
      this.pos = null;
      this.enable(false);
      this.setMessage(this.t.waiting);
      if (!navigator.geolocation) return this.fail(this.t.unsupported);
      navigator.geolocation.getCurrentPosition(
        p => this.measure(p.coords.latitude, p.coords.longitude),
        () => this.fail(this.t.denied),
        {enableHighAccuracy: true, timeout: 10000});
    }

    fail(text) {
      this.setMessage(text, true);
      ['[data-geo-dist]', '[data-geo-bar]', '[data-geo-badge]'].forEach(s => { this.$(s).hidden = true; });
    }

    async measure(lat, lng) {
      this.pos = {lat, lng};
      try {
        const r = this.el.dataset.checkUrl ? await this.post(this.el.dataset.checkUrl, {lat, lng}) : {distance: +this.el.dataset.demoDistance || 80, in_radius: (+this.el.dataset.demoDistance || 80) <= this.radius};
        this.show(r.distance, r.in_radius);
      } catch (e) { this.fail(e.message || this.t.failed); }
    }

    show(distance, inRadius) {
      this.inRadius = inRadius;
      this.setMessage('');
      this.$('[data-geo-meters]').textContent = distance;
      this.$('[data-geo-dist]').hidden = false;
      const bar = this.$('[data-geo-bar]');
      bar.hidden = false;
      bar.classList.toggle('is-out', !inRadius);
      this.$('[data-geo-fill]').style.width = Math.min(100, distance / this.radius * 100) + '%';
      const b = this.$('[data-geo-badge]');
      b.hidden = false;
      b.className = 'dl-geo__badge ' + (inRadius ? 'is-in' : 'is-out');
      b.textContent = inRadius ? this.t.inside : this.t.outside;
      this.enable(true);
    }

    enable(on) { this.buttons.forEach(b => { b.disabled = !on; }); }

    async clock(type) {
      if (!this.pos) return;
      this.enable(false);
      this.emit('dl:geo-clock', {type, ...this.pos});
      try {
        if (!this.el.dataset.clockUrl) return this.enable(true);
        const r = await this.post(this.el.dataset.clockUrl, {type, ...this.pos});
        const n = this.$('[data-geo-note]');
        n.hidden = false;
        n.className = 'dl-geo__note is-' + (r.tone || 'info');
        n.textContent = r.message || '';
        this.history(r.history || []);
      } catch (e) {
        this.setMessage(e.message || this.t.failed, true);
      }
      this.enable(true);
    }

    history(rows) {
      this.$('[data-geo-history]').innerHTML = rows.length
        ? rows.map(r => `<div class="dl-geo__row"><span>${esc(r.type === 'IN' ? this.t.in : this.t.out)}</span><code>${esc(r.time)}</code><i class="is-${esc(r.tone || 'neutral')}">${esc(r.status)}</i></div>`).join('')
        : `<div class="dl-geo__empty">${esc(this.t.empty)}</div>`;
    }
  }
  Devlib.define("GeoClock", GeoClock);
})(window.Devlib);
