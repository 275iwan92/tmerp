/* DevLib · Weather */
(function (Devlib) {
  'use strict';
  const {$, WMO} = Devlib.h;

  class Weather extends Devlib.Component {
    static selector = "[data-dl-weather]";
    init() {
      this.url = `https://api.open-meteo.com/v1/forecast?latitude=${this.el.dataset.lat}&longitude=${this.el.dataset.lon}&current=temperature_2m,weather_code&timezone=auto`;
      this.w = null;
      fetch(this.url).then(r => r.json()).then(j => { this.w = {temp: j.current.temperature_2m, code: j.current.weather_code}; this.paint(); })
        .catch(() => { $(this.el, '[data-dl-weather-desc]').textContent = Devlib.h.T('wx_unavailable'); });
      document.addEventListener('dl:lang', this.paint);
    }

    paint() {
      if (!this.w) return;
      const m = WMO.find(x => this.w.code <= x[0]) || WMO[WMO.length - 1];
      $(this.el, '[data-dl-weather-icon]').textContent = m[1];
      $(this.el, '[data-dl-weather-temp]').textContent = Math.round(this.w.temp) + '°C';
      $(this.el, '[data-dl-weather-desc]').textContent = Devlib.lang === 'id' ? m[2] : m[3];
    }
  }
  Devlib.define("Weather", Weather);
})(window.Devlib);
