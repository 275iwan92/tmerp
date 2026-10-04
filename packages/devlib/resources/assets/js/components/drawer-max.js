/* DevLib · DrawerMax: laci sempit (Chat, AI Assistant, Notifications di Shell; x-dl::drawer maximize) punya dua mode —
 * Maximize = selebar satu layar penuh, Restore = lebar semula (user 2026-10-04). Mode diingat per laci di browser. */
(function (Devlib) {
  'use strict';
  class DrawerMax extends Devlib.Component {
    static selector = '[data-dl-dr-max]';
    init() {
      this.panel = this.el.closest('.dl-drawer, .dl-sdrawer__panel');
      if (!this.panel) return;
      const host = this.panel.closest('[data-dl-drawer], [data-dl-drawer-id]');
      this.key = 'dl.drmax.' + (host ? (host.dataset.dlDrawer || host.dataset.dlDrawerId) : 'drawer');
      let on = false; try { on = localStorage.getItem(this.key) === '1'; } catch (e) {}
      this.set(on);
      this.el.addEventListener('click', () => this.set(!this.panel.classList.contains('is-max')));
    }
    set(on) {
      this.panel.classList.toggle('is-max', on);
      const i = this.el.querySelector('i'); if (i) i.className = on ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
      this.el.title = on ? (this.el.dataset.titleRestore || 'Restore') : (this.el.dataset.titleMax || 'Maximize');
      try { localStorage.setItem(this.key, on ? '1' : '0'); } catch (e) {}
      this.emit('dl:drawer:max', {max: on});
    }
  }
  Devlib.define('DrawerMax', DrawerMax);
})(window.Devlib);
