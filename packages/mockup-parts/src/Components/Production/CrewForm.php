<?php

/*
 * REMARK
 * Tujuan       : Objek form khusus Assign Crew: satu mesin, satu kotak crew per shift produksi aktif (jumlah ikut Master Shift), dropdown Shift per kotak (tukar otomatis), nama crew besar, anggota + peran.
 * Sumber hulu  : Array dari App\Support\V2\Data\CrewForm::desk() (mesin dari grid, crew + periode berlaku, Master Shift, karyawan produksi).
 * Input        : $crew = [title, status, action, back, hidden, from, to, source, nick, shifts[id => nama], employees, roles, max, boxes[[shift, rows]], flash, error].
 * Output       : x-dl::form panel top (Undo, Save, Back) berisi periode dan kotak crew (x-dl::member-list crews[i][members]); perilaku tukar shift = data-dl-swap / data-dl-echo di DevLib core.
 * Dipakai oleh : resources/views/v2/screens/crew-form.blade.php; demo devlib.test > Special Forms > Assign Crew.
 * Status       : Aktif (dummy). Turunan DevlibComponent seperti objek x-mp lain.
 */

namespace MockupParts\Components\Production;

use Devlib\Components\DevlibComponent;

class CrewForm extends DevlibComponent
{
    public function __construct(
        public array $crew = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::production.crew-form';
    }

    public function render()
    {
        return view('mp::production.crew-form');
    }
}
