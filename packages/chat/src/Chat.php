<?php

namespace Chat;

use Closure;

// Kait aplikasi pemakai (pola yang sama dengan Devlib\Lang::translateUsing): dari mana angka setelan dibaca dan
// bagaimana teks pesan galat diterjemahkan. Tanpa kait: config('chat.*') dan __().
final class Chat
{
    private static ?Closure $settings = null;

    private static ?Closure $translator = null;

    // fn (string $key, float $default): float|int|null -- key = attachment_max_mb | poll_seconds | online_timeout_seconds.
    public static function settingsUsing(?Closure $fn): void
    {
        self::$settings = $fn;
    }

    // fn (string $english): string
    public static function translateUsing(?Closure $fn): void
    {
        self::$translator = $fn;
    }

    public static function setting(string $key): float
    {
        $default = (float) config('chat.'.$key);

        return self::$settings ? (float) ((self::$settings)($key, $default) ?? $default) : $default;
    }

    public static function text(string $english): string
    {
        return self::$translator ? (self::$translator)($english) : __($english);
    }
}
