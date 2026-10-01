<?php

namespace Devlib\Components;

use Devlib\PrintDoc\Document;

// Cetakan dokumen transaksi (user 2026-10-01): A5 landscape untuk 1-5 baris, A4 portrait di atas itu, satu tata letak
// untuk semua dokumen. Layar hanya menyerahkan objek dokumen (turunan Devlib\PrintDoc\Document):
//   <x-dl::print-doc :doc="$doc" id="print-doc" />
// Print / PDF / JPEG / online + QR / Email dikerjakan x-dl::doc-share yang menunjuk elemen ini (ukuran kertas ikut lembar).
class PrintDoc extends DevlibComponent
{
    public function __construct(public Document $doc) {}

    public function tag(): string
    {
        return 'x-dl::print-doc';
    }

    public function source(): string
    {
        return 'dokumen cetak: '.$this->doc->type.' ('.class_basename($this->doc).')';
    }

    public function render()
    {
        return view('devlib::components.print-doc');
    }
}
