<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;

// Layar v1 Notification Center: teks ditulis English + t(); terjemahan Indonesia = teks lama, supaya tampilan bahasa id tetap.
return new class extends Migration
{
    private const ID = [
        'Notification Center' => 'Notification Center',
        ':n unread' => ':n belum dibaca',
        'Back to Main Menu' => 'Kembali ke Menu Utama',
        'Search title / message...' => 'Cari judul / isi...',
        'All Types' => 'Semua Tipe',
        'Info' => 'Info',
        'Success' => 'Sukses',
        'Warning' => 'Peringatan',
        'Error' => 'Error',
        'Unread only' => 'Hanya belum dibaca',
        'Mark all as read' => 'Tandai semua terbaca',
        'Open' => 'Buka',
        'No notifications yet.' => 'Belum ada notifikasi.',
    ];

    public function up(): void
    {
        Dictionary::seed(self::ID);
    }

    public function down(): void {}
};
