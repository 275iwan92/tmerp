<?php

/*
 * REMARK
 * Tujuan       : Breadcrumb layar v2: posisi terakhir = identitas dokumen; actions() menampung tombol sejajar breadcrumb.
 * Sumber hulu  : Rute dan parameter layar v2.
 * Input        : Rute aktif, judul dokumen, tombol aksi dari layar (Crumb::actions).
 * Output       : Teks breadcrumb dan HTML tombol di bar atas.
 * Dipakai oleh : resources/views/v2/screen.blade.php, layar production-wo.
 * Status       : Aktif.
 */

namespace App\Support\V2;

// Posisi terakhir breadcrumb layar v2 (user 2026-10-10): identitas dokumen (no. dokumen / nama part), bukan tebakan dari
// query string. Layar memanggil Crumb::set() saat tampil; layout merender isi layar dulu baru breadcrumb. Tanpa set = tanpa label.
class Crumb
{
    private static ?string $label = null;

    // Buttons of the screen shown on the breadcrumb row (right aligned); the screen hands over finished markup.
    private static ?string $actions = null;

    // Small status text shown in the middle of the bar (document status).
    private static ?string $status = null;

    public static function set(?string $label): void
    {
        self::$label = $label;
    }

    public static function actions(?string $html): void
    {
        self::$actions = $html;
    }

    public static function status(?string $html): void
    {
        self::$status = $html;
    }

    public static function statusHtml(): ?string
    {
        return self::$status !== null && trim(self::$status) !== '' ? self::$status : null;
    }

    public static function actionsHtml(): ?string
    {
        return self::$actions !== null && trim(self::$actions) !== '' ? self::$actions : null;
    }

    // ?new=1 = "New"; selain itu label yang diisi layar (null bila tidak ada).
    public static function current(): ?string
    {
        if (self::$label !== null && self::$label !== '') {
            return self::$label;
        }

        return request('new') ? t('New') : null;
    }
}
