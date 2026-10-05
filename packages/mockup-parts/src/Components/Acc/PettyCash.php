<?php

namespace MockupParts\Components\Acc;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Kas Kecil (sistem imprest, user 2026-10-01): kepala kas dengan empat angka (plafon, saldo, kasbon terbuka, yang harus
// diisi ulang), lalu SATU mode: tx = satu transaksi (+ Cancel Transaction), po = PO Kontan (Cash Advance, lalu Advance Settlement
// atau Reimburse), topup = isi ulang dari bank, setup = nama / pemegang / plafon, list = daftar (grid diserahkan layar lewat slot).
// Layar hanya menyerahkan $desk (App\Support\V2\Data\PettyCash::desk()); teks dan angkanya sudah jadi. Aturan dan jurnal tetap di
// App\Services\Acc\PettyCashService. Mengubah objek ini = mengubah form Kas Kecil di semua situs.
class PettyCash extends DevlibComponent
{
    public function __construct(
        public array $desk = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::acc.petty-cash';
    }

    public function mode(): string
    {
        return $this->desk['mode'] ?? 'list';
    }

    // Judul dan tombol kartu pertanggungjawaban: Settlement bila ada kasbon, Reimburse bila pembeli memakai uang sendiri dulu.
    public function settleTitle(): string
    {
        return ($this->desk['po']['settle']['reimburse'] ?? false) ? 'Reimburse' : 'Advance Settlement';
    }

    public function settleButton(): string
    {
        return ($this->desk['po']['settle']['reimburse'] ?? false) ? 'Pay Reimburse' : 'Post Settlement';
    }

    // Pertanyaan konfirmasi tombol kirim (teksnya sudah diterjemahkan pemilik data).
    public function ask(?string $question): ?string
    {
        return $question ? 'return confirm('.json_encode($question).')' : null;
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::acc.petty-cash');
    }
}
