<?php

namespace MockupParts\Components\Planning;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Material Booking (docs/materialbooking_tier1.md). Dua mode:
//   list   = penjelasan kapan stok dipesan, tombol Recalculate All + pintasan, lalu GRID Customer Order (slot).
//   detail = kepala booking satu CO: nomor CO + statusnya + status booking, tombol kerja menurut status (Recalculate dan Confirm
//            selama belum dikunci, Back to Draft sesudah dikunci), empat keterangan, satu pesan keadaan; GRID baris bahan (slot);
//            lalu kartu Pick Item untuk baris yang itemnya belum dipilih.
// Layar hanya menyerahkan $booking (App\Support\V2\Data\MaterialPlanning::bookingDesk()) dan grid-nya lewat slot. Booking hanya
// menulis transaksi; Master Product tidak diubah.
class MaterialBooking extends DevlibComponent
{
    public function __construct(
        public array $booking = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::planning.material-booking';
    }

    public function detail(): bool
    {
        return ($this->booking['mode'] ?? 'list') === 'detail';
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::planning.material-booking');
    }
}
