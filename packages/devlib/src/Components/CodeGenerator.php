<?php

namespace Devlib\Components;

use Devlib\Lang;

// Pembuat kode QR dan barcode: pilih jenis, ketik isinya, hasil tampil langsung, lalu unduh PNG / SVG atau cetak.
// QR memakai qrcodejs, barcode 1D memakai JsBarcode (sama dengan x-dl::qr dan x-dl::barcode). Tiap jenis punya
// aturan isi sendiri (angka saja, panjang tetap, dst); isi yang tidak sah ditolak dengan pesan, bukan gambar rusak.
class CodeGenerator extends DevlibComponent
{
    protected const TEXT_PROPS = ['title', 'hint'];

    // kunci => [label, JsBarcode format (null = QR), petunjuk isi, contoh isi]
    public const TYPES = [
        'QR' => ['QR Code', null, 'Any text, link or ID', 'https://tmerp.test/doc/CO2600011'],
        'CODE128' => ['Code 128 (auto)', 'CODE128', 'Any letters, digits and symbols; the densest 1D code', 'RM-KRT-0012'],
        'CODE128A' => ['Code 128 A', 'CODE128A', 'Capital letters, digits and control characters', 'LOT-2608-A'],
        'CODE128B' => ['Code 128 B', 'CODE128B', 'Letters (upper and lower case), digits and symbols', 'Lot-2608-a'],
        'CODE128C' => ['Code 128 C', 'CODE128C', 'Digits only, in pairs (even count)', '20260811'],
        'CODE39' => ['Code 39', 'CODE39', 'Capital letters, digits and - . $ / + % space', 'RM-KRT-0012'],
        'EAN13' => ['EAN-13', 'EAN13', '12 or 13 digits (check digit is added when 12)', '8991234567895'],
        'EAN8' => ['EAN-8', 'EAN8', '7 or 8 digits (check digit is added when 7)', '12345670'],
        'UPC' => ['UPC-A', 'UPC', '11 or 12 digits (check digit is added when 11)', '123456789012'],
        'UPCE' => ['UPC-E', 'UPCE', '6, 7 or 8 digits (compressed UPC)', '01234565'],
        'ITF14' => ['ITF-14 (carton)', 'ITF14', '13 or 14 digits, for outer cartons', '15400141288763'],
        'ITF' => ['Interleaved 2 of 5', 'ITF', 'Digits only, even count', '12345678'],
        'MSI' => ['MSI', 'MSI', 'Digits only', '1234567'],
        'CODABAR' => ['Codabar', 'codabar', 'Digits and - $ : / . +, usually wrapped in A-D', 'A12345B'],
        'PHARMA' => ['Pharmacode', 'pharmacode', 'A number from 3 to 131070', '1234'],
    ];

    public function __construct(
        public string $type = 'QR',
        public ?string $value = null,
        public ?string $title = null,
        public ?string $hint = null,
    ) {
        $this->type = array_key_exists($type, self::TYPES) ? $type : 'QR';
        $this->value ??= self::TYPES[$this->type][3];
    }

    public function tag(): string
    {
        return 'x-dl::code-generator type='.$this->type;
    }

    public function typeOptions(): array
    {
        return array_map(fn ($t) => $t[0], self::TYPES);
    }

    // Dikirim ke JS: format JsBarcode + petunjuk + contoh per jenis.
    public function typeMap(): array
    {
        return array_map(fn ($t) => ['format' => $t[1], 'hint' => Lang::text($t[2]), 'example' => $t[3]], self::TYPES);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-codegen') }} {!! $dl() !!} data-dl-codegen data-types="{{ json_encode($typeMap()) }}">
                @if ($title)<h3 class="dl-codegen__title">{{ $title }}</h3>@endif
                <div class="dl-codegen__grid">
                    <div class="dl-codegen__form">
                        <x-dl::field.select name="type" label="Code type" :options="$typeOptions()" :value="$type" />
                        <x-dl::field.text name="value" label="Content" :value="$value" :case-toggle="false" code data-cg-value />
                        <div class="dl-field__hint" data-cg-hint></div>
                        <div class="dl-codegen__opts" data-cg-qr-opts>
                            <x-dl::field.select name="ec" label="Error correction" :options="['L' => 'L · 7%', 'M' => 'M · 15%', 'Q' => 'Q · 25%', 'H' => 'H · 30% (survives a logo or a scratch)']" value="M" />
                            <x-dl::field.text name="size" label="Size (px)" type="number" value="200" :case-toggle="false" />
                        </div>
                        <div class="dl-codegen__opts" data-cg-bar-opts>
                            <x-dl::field.text name="height" label="Bar height (px)" type="number" value="70" :case-toggle="false" />
                            <x-dl::field.text name="width" label="Bar width" type="number" value="2" :case-toggle="false" />
                            <x-dl::field.toggle name="text" label="Number below the bars" on-label="Show" off-label="Hide" value="1" />
                        </div>
                        @if ($hint)<small class="dl-codegen__note">{{ $hint }}</small>@endif
                    </div>
                    <div class="dl-codegen__result">
                        <div class="dl-codegen__canvas" data-cg-out>
                            <span data-cg-qr></span>
                            <svg data-cg-bar role="img" aria-label="Barcode"></svg>
                        </div>
                        <div class="dl-codegen__err" data-cg-err hidden></div>
                        <div class="dl-codegen__actions">
                            <x-dl::button label="PNG" variant="primary" icon="fa-solid fa-download" data-cg-png />
                            <x-dl::button label="SVG" variant="secondary" icon="fa-solid fa-file-code" data-cg-svg />
                            <x-dl::button preset="print" data-cg-print />
                        </div>
                    </div>
                </div>
            </div>
            BLADE;
    }
}
