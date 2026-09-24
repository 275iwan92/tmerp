/* DevLib · Tags */
(function (Devlib) {
  'use strict';
  const {$, $$, esc} = Devlib.h;

  class Tags extends Devlib.Component {
    static selector = "[data-dl-tags]";
    init() {
      const root = this.el;
      const input = $(root, '.dl-tags__input'), box = $(root, '.dl-tags__box'), name = root.dataset.name;
      const add = v => {
        v = v.trim().replace(/,$/, ''); if (!v) return;
        if ($$(root, 'input[type=hidden]').some(h => h.value.toLowerCase() === v.toLowerCase())) { input.value = ''; return; }
        const chip = document.createElement('span'); chip.className = 'dl-tags__chip';
        chip.innerHTML = `${esc(v)}<input type="hidden" name="${esc(name)}[]" value="${esc(v)}"><button type="button" data-dl-tag-x>&times;</button>`;
        box.insertBefore(chip, input); input.value = '';
      };
      input.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(input.value); }
        else if (e.key === 'Backspace' && !input.value) { const last = [...box.querySelectorAll('.dl-tags__chip')].pop(); if (last) last.remove(); }
      });
      input.addEventListener('change', () => add(input.value));
      box.addEventListener('click', e => { if (e.target.closest('[data-dl-tag-x]')) e.target.closest('.dl-tags__chip').remove(); else input.focus(); });
    }
  }
  Devlib.define("Tags", Tags);
})(window.Devlib);
