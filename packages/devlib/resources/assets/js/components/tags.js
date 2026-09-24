/* DevLib · Tags */
(function (Devlib) {
  'use strict';
  const {$, $$, esc} = Devlib.h;

  class Tags extends Devlib.Component {
    static selector = "[data-dl-tags]";
    init() {
      this.input = $(this.el, '.dl-tags__input');
      this.box = $(this.el, '.dl-tags__box');
      this.name = this.el.dataset.name;
      this.input.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); this.add(this.input.value); }
        else if (e.key === 'Backspace' && !this.input.value) { const last = [...this.box.querySelectorAll('.dl-tags__chip')].pop(); if (last) last.remove(); }
      });
      this.input.addEventListener('change', () => this.add(this.input.value));
      this.box.addEventListener('click', e => { if (e.target.closest('[data-dl-tag-x]')) e.target.closest('.dl-tags__chip').remove(); else this.input.focus(); });
    }

    add(v) {
      v = v.trim().replace(/,$/, ''); if (!v) return;
      if ($$(this.el, 'input[type=hidden]').some(h => h.value.toLowerCase() === v.toLowerCase())) { this.input.value = ''; return; }
      const chip = document.createElement('span'); chip.className = 'dl-tags__chip';
      chip.innerHTML = `${esc(v)}<input type="hidden" name="${esc(this.name)}[]" value="${esc(v)}"><button type="button" data-dl-tag-x>&times;</button>`;
      this.box.insertBefore(chip, this.input); this.input.value = '';
    }
  }
  Devlib.define("Tags", Tags);
})(window.Devlib);
