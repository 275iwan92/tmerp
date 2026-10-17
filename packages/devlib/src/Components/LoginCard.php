<?php

/*
 * REMARK
 * Tujuan       : Kartu login karyawan baru yang bisa dicetak: nama, kode, username, password awal. Dipakai di dalam x-dl::modal; tombol Print hanya mencetak kartunya.
 * Sumber hulu  : Hasil pembuatan login (nama, login, password) dari layar pemanggil; tidak membaca database dan tidak menyimpan apa pun.
 * Input        : name, code, login, password, title, note (teks catatan; kosong = catatan baku "ganti password saat login pertama, tampil sekali").
 * Output       : <div class="dl-logincard" data-dl-logincard> ... tombol Print (data-lc-print) dan slot untuk tombol penutup.
 * Dipakai oleh : resources/views/v2/screens/hrm-employee-form.blade.php; demo devlib.test > Status > Login Card.
 * Status       : Aktif.
 */

namespace Devlib\Components;

use Devlib\Lang;

// Kartu login yang diserahkan ke karyawan baru. Password hanya dititipkan pemanggil saat kartu dirender (sekali tampil);
// objek ini tidak menyimpan. Cetak: CSS @media print menyembunyikan seisi halaman kecuali kartu.
class LoginCard extends DevlibComponent
{
    public function __construct(
        public string $name = '',
        public string $code = '',
        public string $login = '',
        public string $password = '',
        public string $title = 'Login card',
        public ?string $note = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::login-card';
    }

    public function source(): string
    {
        return 'tm2sys.sec_user (dibuat EmployeeLoginProvisionService); kartu hanya menampilkan hasilnya satu kali';
    }

    public function jsClass(): ?string
    {
        return 'LoginCard';
    }

    public function noteText(): string
    {
        return $this->note ?? Lang::text('The employee must change the password at first login. This password is shown only once: print or write it down before closing.');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-logincard') }} {!! $dl() !!} data-dl-logincard>
                <div class="dl-logincard__head">{{ $title }}</div>
                <div class="dl-logincard__body">
                    <div class="dl-logincard__org">{{ \Devlib\Lang::text('TELASIH MAKMUR ERP — hand this card to the employee') }}</div>
                    <div><small>{{ \Devlib\Lang::text('Name') }}</small><b>{{ $name }}@if ($code) ({{ $code }})@endif</b></div>
                    <div><small>{{ \Devlib\Lang::text('Username') }}</small><span class="dl-logincard__mono">{{ $login }}</span></div>
                    <div><small>{{ \Devlib\Lang::text('Initial password') }}</small><span class="dl-logincard__mono">{{ $password }}</span></div>
                    <div class="dl-logincard__note">{{ $noteText() }}</div>
                    <div class="dl-logincard__bar">
                        <x-dl::button type="button" icon="print" label="Print" data-lc-print />
                        {{ $slot }}
                    </div>
                </div>
            </div>
            BLADE;
    }
}
