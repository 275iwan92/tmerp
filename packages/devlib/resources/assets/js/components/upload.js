/* DevLib · Upload */
(function (Devlib) {
  'use strict';
  const {$, esc} = Devlib.h;

  class Upload extends Devlib.Component {
    static selector = "[data-dl-upload]";
    init() {
      const up = this.el;
      const file = $(up, 'input[type=file]'), list = $(up, '[data-dl-upload-list]'), preview = up.hasAttribute('data-preview');
      let files = [];
      const draw = () => {
        up.classList.toggle('has-file', files.length > 0);
        list.innerHTML = '';
        files.forEach((f, i) => {
          const row = document.createElement('div'); row.className = 'dl-upload__file';
          const isImg = preview && f.type.startsWith('image/');
          row.innerHTML = (isImg ? '<img class="dl-upload__thumb" alt="">' : '<span>📄</span>') + `<span class="dl-upload__name">${esc(f.name)}</span><small>${Math.max(1, Math.round(f.size / 1024))} KB</small><button type="button" data-i="${i}" title="Hapus">✕</button>`;
          if (isImg) { const img = $(row, 'img'); img.src = URL.createObjectURL(f); img.onload = () => URL.revokeObjectURL(img.src); }
          list.appendChild(row);
        });
        const dt = new DataTransfer(); files.forEach(f => dt.items.add(f)); file.files = dt.files;
      };
      const take = incoming => { files = file.multiple ? files.concat([...incoming]) : [...incoming].slice(0, 1); draw(); };
      up.addEventListener('click', e => { if (!e.target.closest('button') && !e.target.closest('.dl-upload__file')) file.click(); });
      file.addEventListener('click', e => e.stopPropagation());
      file.addEventListener('change', () => { if (file.files.length) take(file.files); });
      list.addEventListener('click', e => { const b = e.target.closest('button[data-i]'); if (b) { e.stopPropagation(); files.splice(+b.dataset.i, 1); draw(); } });
      ['dragover', 'dragenter'].forEach(ev => up.addEventListener(ev, e => { e.preventDefault(); up.classList.add('is-drag'); }));
      ['dragleave', 'drop'].forEach(ev => up.addEventListener(ev, e => { e.preventDefault(); up.classList.remove('is-drag'); }));
      up.addEventListener('drop', e => { if (e.dataTransfer.files.length) take(e.dataTransfer.files); });
    }
  }
  Devlib.define("Upload", Upload);
})(window.Devlib);
