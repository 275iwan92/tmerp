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

    // Hak akses: atribut can="modul.aksi" di komponen mana pun; tanpa hak = tidak dirender.
    // Urutan Laravel: shouldRender() -> withAttributes() -> render tampilan. Atribut can hanya
    // ada di withAttributes(), jadi ditangkap di sana dan dicek saat tampilan dirender.
    protected ?string $ability = null;

    public function withAttributes(array $attributes)
    {
        if (array_key_exists('can', $attributes)) {
            $this->ability = (string) $attributes['can'];
            unset($attributes['can']);
        }

        return parent::withAttributes($attributes);
    }

    public function resolveView()
    {
        $view = parent::resolveView();

        return function (array $data = []) use ($view) {
            if ($this->ability !== null && ! \Devlib\Access::can($this->ability)) {
                return new \Illuminate\Support\HtmlString('');
            }

            return $view instanceof \Closure ? $view($data) : $view;
        };
    }

    // Dipakai di elemen akar view: <div {!! $dl() !!}>.
    public function dl(): string
    {
        return 'data-dl="'.e($this->tag()).'" data-dl-class="'.e($this->lineage()).'" data-dl-src="'.e($this->source()).'"';
    }
}
