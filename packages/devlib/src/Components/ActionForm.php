<?php

namespace Devlib\Components;

// Form aksi kecil: pembungkus <form> TANPA judul / panel / tombol sendiri -- untuk tombol aksi (Post, Cancel, Create),
// isian di dalam pop-up, dan saringan GET. Isinya tetap objek DevLib (x-dl::field.*, x-dl::button). Token CSRF dipasang
// sendiri untuk selain GET. Form dokumen / master yang lengkap tetap x-dl::form.
//   <x-dl::action-form action="{{ route('v2.co.create') }}"> ... </x-dl::action-form>
//   <x-dl::action-form method="get" action="{{ $home }}"> ... </x-dl::action-form>
// Atribut lain (id, enctype, data-dl-guard, hidden, style ...) diteruskan apa adanya ke <form>.
class ActionForm extends DevlibComponent
{
    public function __construct(
        public string $method = 'post',
        public ?string $action = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::action-form';
    }

    public function isGet(): bool
    {
        return strtolower($this->method) === 'get';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes }} {!! $dl() !!} method="{{ $isGet() ? 'get' : 'post' }}" @if ($action) action="{{ $action }}" @endif>@unless ($isGet())@csrf @endunless{{ $slot }}</form>
            BLADE;
    }
}
