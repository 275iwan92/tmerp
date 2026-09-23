<?php

namespace Devlib;

use Closure;

// Hak akses untuk komponen: <x-dl::button preset="approve" can="po.approve" />
// Tanpa hak = komponen TIDAK dirender (bukan disembunyikan CSS).
// Aplikasi memasang cara ceknya sekali: Access::resolveUsing(fn ($ability) => ...).
// tmerp: SecUser::canAccessModule()/policy; DevLib (tanpa DB): config devlib.abilities.
final class Access
{
    private static ?Closure $resolver = null;

    public static function resolveUsing(Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function can(string $ability): bool
    {
        if (self::$resolver) {
            return (bool) (self::$resolver)($ability);
        }

        $granted = config('devlib.abilities', ['*']);

        return in_array('*', $granted, true) || in_array($ability, $granted, true)
            || in_array(explode('.', $ability)[0].'.*', $granted, true);
    }
}
