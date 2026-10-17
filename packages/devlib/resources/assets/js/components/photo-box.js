/* DevLib · PhotoBox: kotak pas foto 3:4 + dialog crop (geser, zoom) -> JPEG ke input file form */
(function (Devlib) {
  'use strict';
  const {$, layer, closeLayer} = Devlib.h;

  class PhotoBox extends Devlib.Component {
    static selector = "[data-dl-photobox]";
    init() {
      this.out = {w: +this.el.dataset.outW || 600, h: +this.el.dataset.outH || 800};
      this.result = $(this.el, '[data-pb-result]');
      this.img = null; this.x = 0; this.y = 0; this.s = 1; this.base = 1;
      // Modal dirender sebagai bagian objek; dicari lewat id-nya (lapisan bisa dipindah ke body oleh pustaka modal).
      this.layer = layer(this.el.dataset.cropId);
      if (!this.layer) return;
      this.cv = $(this.layer, '[data-pb-canvas]'); this.ctx = this.cv.getContext('2d');
      this.zoom = $(this.layer, '[data-pb-zoom]'); this.msg = $(this.layer, '[data-pb-msg]'); this.empty = $(this.layer, '[data-pb-empty]');
      this.pick = $(this.layer, '[data-pb-pick]');
      $(this.el, '[data-pb-pic]').addEventListener('click', () => { if (!this.img && this.el.dataset.photo) this.load(this.el.dataset.photo); });
      $(this.layer, '[data-pb-choose]').addEventListener('click', () => this.pick.click());
      this.pick.addEventListener('change', () => { const f = this.pick.files[0]; if (f) this.load(URL.createObjectURL(f)); });
      this.zoom.addEventListener('input', () => this.rezoom());
      $(this.layer, '[data-pb-use]').addEventListener('click', () => this.use());
      this.drag();
    }

    load(src) {
      const im = new Image();
      im.onload = () => {
        this.img = im; this.msg.textContent = ''; this.empty.hidden = true;
        this.base = Math.max(this.cv.width / im.naturalWidth, this.cv.height / im.naturalHeight);
        this.zoom.value = 1; this.s = this.base;
        this.x = (this.cv.width - im.naturalWidth * this.s) / 2; this.y = (this.cv.height - im.naturalHeight * this.s) / 2;
        this.draw();
      };
      im.src = src;
    }

    // Foto selalu menutupi seluruh bingkai: tidak ada tepi kosong di hasil.
    clamp() {
      const w = this.img.naturalWidth * this.s, h = this.img.naturalHeight * this.s;
      this.x = Math.min(0, Math.max(this.cv.width - w, this.x));
      this.y = Math.min(0, Math.max(this.cv.height - h, this.y));
    }

    draw() {
      if (!this.img) return;
      this.clamp();
      this.ctx.clearRect(0, 0, this.cv.width, this.cv.height);
      this.ctx.drawImage(this.img, this.x, this.y, this.img.naturalWidth * this.s, this.img.naturalHeight * this.s);
    }

    rezoom() {
      if (!this.img) return;
      const cx = (this.cv.width / 2 - this.x) / this.s, cy = (this.cv.height / 2 - this.y) / this.s;
      this.s = this.base * +this.zoom.value;
      this.x = this.cv.width / 2 - cx * this.s; this.y = this.cv.height / 2 - cy * this.s;
      this.draw();
    }

    drag() {
      let last = null;
      this.cv.addEventListener('pointerdown', e => { last = {x: e.clientX, y: e.clientY}; this.cv.setPointerCapture(e.pointerId); });
      this.cv.addEventListener('pointermove', e => {
        if (!last || !this.img) return;
        const k = this.cv.width / this.cv.getBoundingClientRect().width;
        this.x += (e.clientX - last.x) * k; this.y += (e.clientY - last.y) * k; last = {x: e.clientX, y: e.clientY};
        this.draw();
      });
      ['pointerup', 'pointercancel'].forEach(ev => this.cv.addEventListener(ev, () => { last = null; }));
    }

    use() {
      if (!this.img) { this.msg.textContent = 'Choose a photo first.'; return; }
      const f = this.out.w / this.cv.width, c = document.createElement('canvas');
      c.width = this.out.w; c.height = this.out.h;
      c.getContext('2d').drawImage(this.img, this.x * f, this.y * f, this.img.naturalWidth * this.s * f, this.img.naturalHeight * this.s * f);
      c.toBlob(b => {
        const dt = new DataTransfer(); dt.items.add(new File([b], 'profile.jpg', {type: 'image/jpeg'}));
        this.result.files = dt.files;
        $(this.el, '[data-pb-img]').src = URL.createObjectURL(b);
        this.el.classList.add('has-photo');
        this.result.dispatchEvent(new Event('change', {bubbles: true}));
        closeLayer(this.layer);
      }, 'image/jpeg', 0.9);
    }
  }
  Devlib.define("PhotoBox", PhotoBox);
})(window.Devlib);
