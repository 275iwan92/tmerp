/* DevLib · Upload */
(function (Devlib) {
  'use strict';
  const {$, esc} = Devlib.h;

  class Upload extends Devlib.Component {
    static selector = "[data-dl-upload]";
    init() {
      this.file = $(this.el, 'input[type=file]');
      this.list = $(this.el, '[data-dl-upload-list]');
      this.preview = this.el.hasAttribute('data-preview');
      this.files = [];
      this.el.addEventListener('click', e => { if (!e.target.closest('button') && !e.target.closest('.dl-upload__file')) this.file.click(); });
      this.file.addEventListener('click', e => e.stopPropagation());
      this.file.addEventListener('change', () => { if (this.file.files.length) this.take(this.file.files); });
      this.list.addEventListener('click', e => { const b = e.target.closest('button[data-i]'); if (b) { e.stopPropagation(); this.files.splice(+b.dataset.i, 1); this.draw(); } });
      ['dragover', 'dragenter'].forEach(ev => this.el.addEventListener(ev, e => { e.preventDefault(); this.el.classList.add('is-drag'); }));
      ['dragleave', 'drop'].forEach(ev => this.el.addEventListener(ev, e => { e.preventDefault(); this.el.classList.remove('is-drag'); }));
      this.el.addEventListener('drop', e => { if (e.dataTransfer.files.length) this.take(e.dataTransfer.files); });
    }

    draw() {
      this.el.classList.toggle('has-file', this.files.length > 0);
      this.list.innerHTML = '';
      this.files.forEach((f, i) => {
        const row = document.createElement('div'); row.className = 'dl-upload__file';
        const isImg = this.preview && f.type.startsWith('image/');
        row.innerHTML = (isImg ? '<img class="dl-upload__thumb" alt="">' : '<span>📄</span>') + `<span class="dl-upload__name">${esc(f.name)}</span><small>${Math.max(1, Math.round(f.size / 1024))} KB</small><button type="button" data-i="${i}" title="Hapus">✕</button>`;
        if (isImg) { const img = $(row, 'img'); img.src = URL.createObjectURL(f); img.onload = () => URL.revokeObjectURL(img.src); }
        this.list.appendChild(row);
      });
      const dt = new DataTransfer(); this.files.forEach(f => dt.items.add(f)); this.file.files = dt.files;
    }

    take(incoming) { this.files = this.file.multiple ? this.files.concat([...incoming]) : [...incoming].slice(0, 1); this.draw(); }
  }
  Devlib.define("Upload", Upload);
})(window.Devlib);
