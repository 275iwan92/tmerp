<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use Devlib\Lang;
use MockupParts\Src;

// Form khusus Customer Plan Delivery = buku janji (user 2026-10-01): satu baris jadwal kirim memegang DUA janji yang harus cocok,
// slot ke customer (Sales) dan siap produksi (PPIC). Objek ini memiliki seluruh tampilan form: ringkasan janji, lalu kartu aksi
// PPIC / Sales / Customer refused / Operational Manager / Post to Production / Join lines, log negosiasi dan semua baris product.
// Layar hanya menyerahkan $book (App\Support\V2\Data\Cpdel::promise()); bagian yang bernilai null tidak ditampilkan, jadi hak akses
// dan status diputuskan pemilik data, bukan di sini. Mengubah objek ini = mengubah form CPDEL di semua situs.
// $book: home, orderUrl, printUrl, coNo, product, state, stateLabel, stateTone, posted, facts [[label, value, sources[]] x 8],
//   next, problems[], ppic?, slot?, refuse?, decide?, post?, postedInfo? [when, workOrders], merge?, log[], lines[].
class PromiseBook extends DevlibComponent
{
    public function __construct(
        public array $book = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.promise-book';
    }

    // Nada pesan "langkah berikutnya": merah bila terlambat / eskalasi, hijau bila kedua janji cocok atau sudah dikirim.
    public function nextTone(): string
    {
        return match ($this->book['state'] ?? '') {
            'LATE', 'ESCALATED' => 'danger',
            'ALIGNED', 'SHIPPED' => 'success',
            default => 'warn',
        };
    }

    public function escalated(): bool
    {
        return ($this->book['state'] ?? '') === 'ESCALATED';
    }

    /** @return list<list<array{0: string, 1: string, 2: list<string>}>> ringkasan janji, 4 nilai per baris */
    public function factRows(): array
    {
        return array_chunk($this->book['facts'] ?? [], 4);
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function slotDescription(): string
    {
        return ($this->book['posted'] ?? false)
            ? Lang::text('Posted to Production: only the slot time can change. Record who agreed and how.')
            : Lang::text('After the customer agrees by WhatsApp or phone: move the whole line, or fill Split qty to send part of it at the new slot. The slot must be inside Telasih office hours and the customer receiving hours.');
    }

    public function render()
    {
        return view('mp::sales.promise-book');
    }
}
