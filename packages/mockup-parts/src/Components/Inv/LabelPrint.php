<?php

namespace MockupParts\Components\Inv;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus cetak label gudang (user 2026-10-02): SATU objek untuk Print Location Label dan Print Item Label. Urutan tetap:
// pesan pengantar (mis. "label untuk penerimaan ...") -> saringan (gedung, area) yang memuat ulang layar -> pilih yang dicetak +
// ukuran label + kertas + jumlah lembar -> Print (riwayat cetak dicatat pemilik data) -> panduan warna label.
// Layar hanya menyerahkan $form (App\Support\V2\Data\Labels::locationForm() / itemForm()): title, icon, description, layout
// ('row' = empat isian sebaris, 'stack' = dua baris dua isian), empty? [judul, pesan], notice? [message, buttons[[icon, label, url]]],
// filter? [action, fields[[name, label, options, value, sources[]]]], nothing? pesan, print? [action, hidden[], keysLabel, options,
// picked[], sources[], copiesHint?, sizes, papers, historyUrl], legend. Mengubah objek ini = mengubah kedua layar label.
class LabelPrint extends DevlibComponent
{
    public function __construct(
        public array $form = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::inv.label-print';
    }

    public function stacked(): bool
    {
        return ($this->form['layout'] ?? 'row') === 'stack';
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::inv.label-print');
    }
}
