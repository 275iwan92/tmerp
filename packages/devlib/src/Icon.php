<?php

namespace Devlib;

// Ikon baku per ARTI (Font Awesome 6). Layar menyebut artinya ("save", "delete"),
// bukan nama ikonnya -- ganti ikon di sini, semua tombol ikut.
final class Icon
{
    public const MAP = [
        // aksi dokumen
        'new' => 'fa-plus', 'edit' => 'fa-pen', 'open' => 'fa-up-right-from-square', 'view' => 'fa-eye', 'save' => 'fa-floppy-disk',
        'delete' => 'fa-trash-can', 'cancel' => 'fa-xmark', 'close' => 'fa-xmark', 'back' => 'fa-arrow-left', 'confirm' => 'fa-circle-check',
        'approve' => 'fa-check', 'reject' => 'fa-ban', 'hold' => 'fa-pause', 'submit' => 'fa-paper-plane', 'copy' => 'fa-copy', 'undo' => 'fa-rotate-left', 'eraser' => 'fa-eraser', 'signature' => 'fa-signature', 'card' => 'fa-credit-card',
        'print' => 'fa-print', 'download' => 'fa-download', 'upload' => 'fa-upload', 'export' => 'fa-file-export', 'import' => 'fa-file-import',
        'refresh' => 'fa-rotate', 'lock' => 'fa-lock', 'unlock' => 'fa-lock-open', 'activate' => 'fa-circle-check', 'deactivate' => 'fa-ban',
        // grid & navigasi
        'search' => 'fa-magnifying-glass', 'filter' => 'fa-filter', 'sort' => 'fa-sort', 'columns' => 'fa-table-columns', 'group' => 'fa-layer-group', 'density' => 'fa-table-cells', 'expand-row' => 'fa-chevron-right', 'more' => 'fa-ellipsis-vertical',
        'expand' => 'fa-chevron-down', 'collapse' => 'fa-chevron-up', 'next' => 'fa-chevron-right', 'prev' => 'fa-chevron-left', 'menu' => 'fa-bars', 'home' => 'fa-house',
        // status & info
        'info' => 'fa-circle-info', 'warning' => 'fa-triangle-exclamation', 'error' => 'fa-circle-xmark', 'success' => 'fa-circle-check', 'help' => 'fa-circle-question',
        'support' => 'fa-headset', 'notification' => 'fa-bell', 'chat' => 'fa-comment-dots', 'ai' => 'fa-wand-magic-sparkles', 'code' => 'fa-code',
        // data & modul
        'user' => 'fa-user', 'users' => 'fa-users', 'password' => 'fa-key', 'email' => 'fa-envelope', 'phone' => 'fa-phone', 'calendar' => 'fa-calendar-days',
        'time' => 'fa-clock', 'money' => 'fa-money-bill-wave', 'file' => 'fa-file-lines', 'attachment' => 'fa-paperclip', 'settings' => 'fa-gear', 'logout' => 'fa-right-from-bracket',
    ];

    public static function fa(string $name): string
    {
        return self::MAP[$name] ?? $name;
    }
}
