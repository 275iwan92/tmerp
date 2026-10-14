<?php

/*
 * REMARK
 * Tujuan       : Teks lingkungan untuk x-dl::env-badge: akhiran judul logo (" - mockup ver 0.20") dan host/IP; kosong di situs live.
 * Sumber hulu  : host permintaan (sama dengan bootstrap/app.php: mockup.test), berkas VERSION di akar repo, gethostname / SERVER_ADDR / request ip.
 * Input        : host (opsional, untuk tes), server addr, client ip.
 * Output       : string; '' bila bukan mockup.test.
 * Dipakai oleh : AppServiceProvider (binding devlib.env_title dan devlib.env_where).
 * Status       : Aktif. Tidak menyimpan apa pun ke database.
 */

namespace App\Support;

final class EnvBadge
{
    private const HOSTS = ['mockup.test', 'www.mockup.test'];

    /** " - mockup ver 0.20" appended to the logo title (ERP TM); '' when the site is not mockup.test. */
    public static function title(?string $host = null): string
    {
        return self::isMockup($host) ? ' - mockup ver '.self::versionNumber() : '';
    }

    /** "Host: NAME / IP · Client: IP" (under the user name); '' when the site is not mockup.test. */
    public static function where(?string $host = null, ?string $serverAddr = null, ?string $clientIp = null): string
    {
        if (! self::isMockup($host)) {
            return '';
        }
        $name = (string) gethostname();

        return 'Host: '.$name.' / '.self::serverIp($serverAddr ?? ($_SERVER['SERVER_ADDR'] ?? ''), $name).' · Client: '.($clientIp ?? request()->ip());
    }

    private static function isMockup(?string $host): bool
    {
        return in_array($host ?? strtolower((string) request()->getHost()), self::HOSTS, true);
    }

    /** The one line in the VERSION file at the repo root. */
    public static function versionNumber(): string
    {
        $v = @file_get_contents(base_path('VERSION'));

        return $v === false ? '?' : trim($v);
    }

    /** SERVER_ADDR unless loopback, else the IP of the host name unless loopback, else as it is (never invented). */
    private static function serverIp(string $addr, string $name): string
    {
        $loop = fn (string $ip) => $ip === '' || str_starts_with($ip, '127.') || $ip === '::1';
        if (! $loop($addr)) {
            return $addr;
        }
        $byName = $name !== '' ? (string) gethostbyname($name) : '';

        return ! $loop($byName) && $byName !== $name ? $byName : ($addr !== '' ? $addr : '127.0.0.1');
    }
}
