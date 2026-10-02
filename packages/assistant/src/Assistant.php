<?php

namespace Assistant;

use Assistant\Contracts\Asker;
use Closure;

// Kait aplikasi pemakai (pola yang sama dengan Chat\Chat): siapa yang bertanya, dari mana setelan dibaca, dan bagaimana teks
// jawaban baku diterjemahkan. Tanpa kait: config('assistant.*') dan teks English apa adanya.
final class Assistant
{
    private static ?Closure $asker = null;

    private static ?Closure $settings = null;

    private static ?Closure $translator = null;

    // fn (mixed $user): Asker -- membungkus user aplikasi (level, hak modul, bahasa).
    public static function askerUsing(?Closure $fn): void
    {
        self::$asker = $fn;
    }

    // fn (string $key, mixed $default): mixed -- key = kunci config('assistant.*') (model_enabled, history_days_top, ...).
    public static function settingsUsing(?Closure $fn): void
    {
        self::$settings = $fn;
    }

    // fn (string $english): string
    public static function translateUsing(?Closure $fn): void
    {
        self::$translator = $fn;
    }

    public static function asker(mixed $user): Asker
    {
        if (! self::$asker) {
            throw new \LogicException('Assistant::askerUsing() has not been set by the application.');
        }

        return (self::$asker)($user);
    }

    public static function setting(string $key): mixed
    {
        $default = config('assistant.'.$key);

        return self::$settings ? ((self::$settings)($key, $default) ?? $default) : $default;
    }

    // Teks baku jawaban (:name diganti nilai).
    public static function text(string $english, array $replace = []): string
    {
        $text = self::$translator ? (self::$translator)($english) : $english;
        foreach ($replace as $k => $v) {
            $text = str_replace(':'.$k, (string) $v, $text);
        }

        return $text;
    }
}
