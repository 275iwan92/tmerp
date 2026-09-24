<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Scope Revisi (tmerp revise.blade.php kartu 2): pilih yang berubah, boleh >1; tiap scope membuka blok edit-nya
// (data-scope-block) dan menentukan Konsekuensi (x-mp::sales.revise-impact). Tersimpan CSV di iqp_inquiry.revise_scope.
class ReviseScope extends DevlibComponent
{
    public const OPTIONS = [
        'MATERIAL' => ['🧱 Material', 'Bahan/gramasi berubah. → costing ulang.'],
        'DIMENSION' => ['📐 Dimensi', 'Ukuran P×L×T berubah. → imposisi ulang + costing.'],
        'ARTWORK' => ['🎨 Artwork', 'Desain berubah. → proof warna ulang.'],
        'COLOR' => ['🖌️ Warna', 'Tinta/plate berubah. → costing ulang.'],
        'PROCESS' => ['⚙️ Proses', 'Urutan/finishing berubah. → costing ulang.'],
    ];

    public function __construct(public array $value = []) {}

    public function tag(): string
    {
        return 'x-mp::sales.revise-scope';
    }

    public function source(): string
    {
        return \MockupParts\Src::of('tm2sdi.iqp_inquiry.revise_scope');
    }

    public function options(): array
    {
        return self::OPTIONS;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-scope') }} {!! $dl() !!}>
                <input type="hidden" name="revise_scope" value="{{ implode(',', $value) }}" data-scope-value>
                @foreach ($options() as $key => [$title, $desc])
                    <label class="mp-scope__opt {{ in_array($key, $value, true) ? 'is-on' : '' }}" data-scope="{{ $key }}">
                        <input type="checkbox" @checked(in_array($key, $value, true))>
                        <span><b>{{ $title }}</b><small>{{ $desc }}</small></span>
                    </label>
                @endforeach
            </div>
            BLADE;
    }
}
