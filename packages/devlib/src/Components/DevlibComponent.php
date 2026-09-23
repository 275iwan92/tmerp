<?php

namespace Devlib\Components;

use Illuminate\View\Component;

// Induk SEMUA komponen DevLib. Tiap turunan otomatis membawa "KTP" (atribut
// data-dl) = nama komponen + rantai induknya + sumber datanya, yang dibaca
// Mode Developer (devlib.js) untuk tooltip asal-usul objek.
abstract class DevlibComponent extends Component
{
    // Nama tag, mis. "x-dl::login".
    abstract public function tag(): string;

    // Sumber data yang ditampilkan komponen (tabel/view/konfigurasi).
    public function source(): string
    {
        return '—';
    }

    public function lineage(): string
    {
        $chain = [];
        for ($c = static::class; $c && $c !== Component::class; $c = get_parent_class($c)) {
            $chain[] = class_basename($c);
        }

        return implode(' ← ', $chain);
    }

    // Dipakai di elemen akar view: <div {!! $dl() !!}>.
    public function dl(): string
    {
        return 'data-dl="'.e($this->tag()).'" data-dl-class="'.e($this->lineage()).'" data-dl-src="'.e($this->source()).'"';
    }
}
