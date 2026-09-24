/* DevLib · Weather */
(function (Devlib) {
  'use strict';
  const {$, WMO} = Devlib.h;

  class Weather extends Devlib.Component {
    static selector = "[data-dl-weather]";
    init() {
      const root = this.el;
      const url = `https://api.open-meteo.com/v1/forecast?latitude=${root.dataset.lat}&longitude=${root.dataset.lon}&current=temperature_2m,weather_code&timezone=auto`;
      let w = null;
      const paint = () => {
        if (!w) return;
        const m = WMO.find(x => w.code <= x[0]) || WMO[WMO.length - 1];
        $(root, '[data-dl-weather-icon]').textContent = m[1];
        $(root, '[data-dl-weather-temp]').textContent = Math.round(w.temp) + '°C';
        $(root, '[data-dl-weather-desc]').textContent = Devlib.lang === 'id' ? m[2] : m[3];
      };
      fetch(url).then(r => r.json()).then(j => { w = {temp: j.current.temperature_2m, code: j.current.weather_code}; paint(); })
        .catch(() => { $(root, '[data-dl-weather-desc]').textContent = Devlib.lang === 'id' ? 'cuaca tak tersedia' : 'weather unavailable'; });
      document.addEventListener('dl:lang', paint);
    }
  }
  Devlib.define("Weather", Weather);
})(window.Devlib);
