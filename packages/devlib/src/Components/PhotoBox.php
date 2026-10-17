<?php

/*
 * REMARK
 * Tujuan       : Kotak pas foto 3 x 4 yang bisa diklik: membuka dialog crop (bingkai tetap 3:4, geser dan zoom) lalu menaruh hasil JPEG 600 x 800 px ke input file milik form.
 * Sumber hulu  : URL foto yang sudah ada (opsional) dari layar pemanggil; objek ini tidak membaca database dan tidak menyimpan apa pun.
 * Input        : name (nama input file, bawaan photo), photo (url), label (nama untuk inisial), caption, outW / outH (ukuran hasil, bawaan 600 x 800), size (sm | md | lg).
 * Output       : <div data-dl-photobox> kotak foto + x-dl::modal berisi kanvas crop; input file bernama $name berisi hasil crop dan ikut terkirim saat form disimpan.
 * Dipakai oleh : resources/views/v2/screens/hrm-employee-form.blade.php; demo devlib.test > Status > Photo Box.
 * Status       : Aktif.
 */

namespace Devlib\Components;

// Foto orang berukuran pas foto (3:4). Perubahan baru tersimpan ketika form induk disimpan (sama dengan isian lain);
// tanda is-dirty form ikut menyala lewat event change pada input file.
class PhotoBox extends DevlibComponent
{
    public string $cropId;

    public function __construct(
        public string $name = 'photo',
        public ?string $photo = null,
        public string $label = '',
        public ?string $caption = null,
        public int $outW = 600,
        public int $outH = 800,
        public string $size = 'md',
    ) {
        $this->cropId = 'dl-photobox-'.substr(md5($name.microtime()), 0, 6);
    }

    public function tag(): string
    {
        return 'x-dl::photo-box';
    }

    public function source(): string
    {
        return 'Foto profil (mis. tm2mst.emp_employee.photo_path); disimpan oleh layar pemanggil saat form disimpan';
    }

    public function jsClass(): ?string
    {
        return 'PhotoBox';
    }

    public function initials(): string
    {
        $w = preg_split('/\s+/', trim($this->label)) ?: [''];

        return mb_strtoupper(mb_substr($w[0], 0, 1).(isset($w[1]) ? mb_substr($w[1], 0, 1) : '')) ?: '＋';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-photobox', 'dl-photobox--'.$size, 'has-photo' => (bool) $photo]) }} {!! $dl() !!} data-dl-photobox data-crop-id="{{ $cropId }}" data-out-w="{{ $outW }}" data-out-h="{{ $outH }}" @if ($photo) data-photo="{{ $photo }}" @endif>
                <button type="button" class="dl-photobox__pic" style="aspect-ratio: {{ $outW }} / {{ $outH }}" data-dl-open="{{ $cropId }}" data-pb-pic title="{{ \Devlib\Lang::text('Profile photo') }}">
                    <img alt="" data-pb-img @if ($photo) src="{{ $photo }}" @endif>
                    <span class="dl-photobox__ini">{{ $initials() }}</span>
                    <span class="dl-photobox__cap dl-photobox__cap--add"><i class="fa-solid fa-camera"></i> {{ $caption ?? \Devlib\Lang::text('Click to add photo') }}</span>
                    <span class="dl-photobox__cap dl-photobox__cap--change"><i class="fa-solid fa-camera"></i> {{ \Devlib\Lang::text('Change photo') }}</span>
                </button>
                <input type="file" name="{{ $name }}" accept="image/*" hidden data-pb-result>
                <x-dl::modal :id="$cropId" title="Profile photo" icon="fa-camera">
                    <div class="dl-photobox__stage">
                        <div class="dl-photobox__frame">
                            <canvas class="dl-photobox__cv" width="240" height="{{ (int) round(240 * $outH / $outW) }}" data-pb-canvas></canvas>
                            <span class="dl-photobox__grid"></span>
                            <span class="dl-photobox__empty" data-pb-empty>{{ \Devlib\Lang::text('Choose a photo to start.') }}</span>
                        </div>
                    </div>
                    <label class="dl-photobox__zoom">{{ \Devlib\Lang::text('Zoom') }} <input type="range" min="1" max="3" step="0.01" value="1" data-pb-zoom></label>
                    <input type="file" accept="image/*" hidden data-pb-pick>
                    <p class="dl-photobox__hint">{{ \Devlib\Lang::text('Drag the photo to position it. The result is 600 x 800 px, ratio 3 : 4 (about 3 x 4 cm when printed).') }}</p>
                    <p class="dl-photobox__err" data-pb-msg></p>
                    <x-dl::action-bar>
                        <x-dl::button type="button" variant="secondary" icon="fa-folder-open" label="Choose File" data-pb-choose />
                        <x-dl::button type="button" variant="secondary" label="Cancel" data-dl-close-modal />
                        <x-dl::button type="button" icon="fa-check" label="Use Photo" data-pb-use />
                    </x-dl::action-bar>
                </x-dl::modal>
            </div>
            BLADE;
    }
}
