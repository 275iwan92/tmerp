<?php

/*
 * REMARK
 * Tujuan       : Daftar peserta ber-batas (maks N baris): nomor, nama, peran, tombol Remove (dengan konfirmasi); pilih dari daftar lalu Add; penghitung "n of N filled" + bar.
 * Sumber hulu  : -
 * Input        : name (awalan nama kirim: members -> members[0][employee_id], members[0][role]), rows [[id, role]], options [id => label], roles [peran],
 *                max (bawaan 10), title.
 * Output       : Input hidden yang ikut terkirim bersama form; perubahan menandai form belum disimpan, dan Undo bawaan x-dl::form mengembalikan isi awal
 *                (fillForm memanggil el.dlMembers.reset()). Add mati sampai seseorang dipilih atau daftar penuh. Gaya .dl-members di devlib.css.
 * Dipakai oleh : screens/crew-form (anggota crew).
 * Status       : Aktif. Pasangan JS: components/member-list.js.
 */

namespace Devlib\Components;

use Devlib\Lang;

class MemberList extends DevlibComponent
{
    public function __construct(
        public string $name = 'members',
        public array $rows = [],
        public array $options = [],
        public array $roles = [],
        public int $max = 10,
        public string $title = '',
    ) {}

    public function tag(): string
    {
        return 'x-dl::member-list';
    }

    public function config(): array
    {
        return [
            'name' => $this->name, 'rows' => array_values($this->rows), 'options' => (object) $this->options, 'roles' => $this->roles, 'max' => $this->max,
            'text' => [
                'count' => Lang::text(':n of :max filled'), 'pick' => Lang::text('Choose employee...'), 'add' => Lang::text('Add'), 'remove' => Lang::text('Remove'),
                'confirm' => Lang::text('Remove this person from the list?'), 'full' => Lang::text('Crew is full (:max of :max). Make a new crew for the next people.'), 'role' => Lang::text('Role'),
            ],
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-members') }} {!! $dl() !!} data-dl-members data-config="{{ json_encode($config()) }}">
                <div class="dl-members__head"><b class="dl-members__title">{{ \Devlib\Lang::text($title) }}</b><span class="dl-badge dl-badge--pill dl-badge--neutral" data-m-count></span></div>
                <div class="dl-members__bar"><i data-m-bar></i></div>
                <div class="dl-members__rows" data-m-rows></div>
                <div class="dl-members__add">
                    <select class="dl-members__pick" data-m-pick></select>
                    <button type="button" class="dl-btn dl-btn--plain dl-btn--sm" data-m-add></button>
                </div>
            </div>
            BLADE;
    }
}
