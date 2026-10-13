<?php

/*
 * REMARK
 * Tujuan       : Objek layar Production Output (entry hasil produksi di mesin): Master Card compact, target, dua timer, LIST OF JOBS, input GOOD / BAD.
 * Sumber hulu  : Array dari App\Support\V2\Data\ProductionOutput::desk() (antrean CONFIRMED PPIC Planning milik crew login).
 * Input        : $desk (judul, card, target, timers, saved, jobs, urls) atau ['empty' => alasan].
 * Output       : Markup + data-desk untuk output-desk.js (timer berdetak, estimasi, POST timer / hasil).
 * Dipakai oleh : resources/views/v2/screens/production-operator.blade.php.
 * Status       : Aktif di mockup (ddt*). Turunan DevlibComponent seperti objek x-mp lain.
 */

namespace MockupParts\Components\Production;

use Devlib\Components\DevlibComponent;

class OutputDesk extends DevlibComponent
{
    public function __construct(
        public array $desk = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::production.output-desk';
    }

    public function render()
    {
        return view('mp::production.output-desk');
    }
}
