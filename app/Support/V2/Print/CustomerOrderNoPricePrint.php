<?php

namespace App\Support\V2\Print;

// Customer Order tanpa harga (format A, user 2026-10-01): field dan posisi sama; kolom Harga / Subtotal, kotak total, dan
// Terbilang tidak dicetak; tempat kolom harga dipakai Keterangan.
final class CustomerOrderNoPricePrint extends CustomerOrderPrint
{
    public ?string $subtitle = 'Sales Order · No Prices';

    public array $extra = [
        ['key' => 'cfg', 'label' => 'CFG', 'width' => 8],
        ['key' => 'type', 'label' => 'Type', 'width' => 9],
        ['key' => 'due_date', 'label' => 'Ship Date', 'width' => 21, 'type' => 'date'],
        ['key' => 'remark', 'label' => 'Remarks', 'width' => 46],
    ];

    public bool $priced = false;
}
