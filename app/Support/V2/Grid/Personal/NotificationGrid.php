<?php

/* REMARK
 * Tujuan       : Notification Center v2: seluruh riwayat notifikasi milik user login (dan siaran user_id kosong) dengan cari judul / isi, saringan tipe dan hanya belum dibaca; klik baris menandai terbaca dan membuka tautannya.
 * Sumber hulu  : sys_notifications (tm2sys) lewat SysNotification::forUser; pindahan layar v1 pages::notification.index.
 * Input        : kata cari (kotak grid), saringan type / status (grid).
 * Output       : baris grid: judul, isi, tipe (badge), waktu, status (badge).
 * Dipakai oleh : rute v2.notifications (layar notification-center) dan tautan "lihat semua" panel notifikasi.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Grid\Personal;

use App\Models\Sys\SysNotification;
use App\Support\V2\Grid\DbGrid;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class NotificationGrid extends DbGrid
{
    public function slug(): string
    {
        return 'notifications';
    }

    public function title(): string
    {
        return 'Notification Center';
    }

    public function module(): string
    {
        return 'ACCOUNT';
    }

    public function moduleLabel(): string
    {
        return 'Account';
    }

    public function group(): string
    {
        return 'Notifications';
    }

    public function icon(): string
    {
        return 'fa-solid fa-bell';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function search(): string
    {
        return 'Search title or message...';
    }

    protected function query(): Builder
    {
        return SysNotification::forUser(Auth::user()->login)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title'],
            ['key' => 'message', 'label' => 'Message'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => [t('Info') => 'info', t('Success') => 'success', t('Warning') => 'warn', t('Error') => 'danger']],
            ['key' => 'at', 'label' => 'Time', 'type' => 'datetime'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => [t('Unread') => 'warn', t('Read') => 'neutral']],
        ];
    }

    protected function row(Model $m): array
    {
        return ['title' => (string) $m->title, 'message' => (string) $m->message, 'type' => t(match (strtolower((string) $m->type)) {
            'success' => 'Success', 'warning', 'warn' => 'Warning', 'error', 'danger' => 'Error', default => 'Info',
        }), 'at' => Locale::dt($m->created_at), 'status' => t($m->is_read ? 'Read' : 'Unread')];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'status' => 'Status'];
    }

    // Klik baris: tandai terbaca lalu buka tautan notifikasinya (route v2.notifications.open).
    public function openUrl(): ?string
    {
        return str_replace('987654321', '{id}', route('v2.notifications.open', ['id' => 987654321]));
    }
}
