<?php

namespace App\Support\V2;

// Layar pembanding Special Forms (hanya admin): daftar tunggal untuk rute v2.compare, menu Developer, dan tes.
// Isi tiap layar = resources/views/v2/compare/<slug>.blade.php (data contoh statis, tidak membaca / menulis basis data).
class Compare
{
    /** @return array<string, array{0: string, 1: string}> slug => [judul, ikon] */
    public static function screens(): array
    {
        return [
            'sf-promise-book' => ['Customer Plan Delivery (promise book)', 'fa-solid fa-truck-ramp-box'],
            'sf-self-service' => ['Edit Profile (self-service)', 'fa-solid fa-user-gear'],
            'sf-product-master' => ['Master Product (one product)', 'fa-solid fa-box'],
            'sf-customer-card' => ['Customer 360 (one customer)', 'fa-solid fa-address-card'],
            'sf-petty-cash' => ['Petty Cash', 'fa-solid fa-wallet'],
            'sf-label-print' => ['Label Printing (location and item)', 'fa-solid fa-qrcode'],
            'sf-pallet-label' => ['Pallet Label', 'fa-solid fa-pallet'],
            'sf-scan-track' => ['Track via QR / Barcode', 'fa-solid fa-magnifying-glass-location'],
            'sf-material-planning' => ['Material Planning and Booking', 'fa-solid fa-boxes-packing'],
            'sf-crew-form' => ['Assign Crew (one machine, a box per shift)', 'fa-solid fa-people-group'],
        ];
    }

    /** Item menu grup "Comparison · Special Forms" (modul Developer, hanya admin). */
    public static function menuItems(): array
    {
        $items = [];
        foreach (self::screens() as $slug => [$title, $icon]) {
            $items[] = ['code' => 'V2-'.$slug, 'label' => t($title), 'icon' => $icon, 'url' => route('v2.compare', $slug), 'type' => 'form'];
        }

        return $items;
    }
}
