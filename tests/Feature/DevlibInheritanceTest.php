<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Arah warisan (user 2026-10-07): DevLib membuat objek / helper, tmerp mewarisinya -- bukan sebaliknya.
// Paket packages/devlib harus berdiri sendiri: tanpa memakai kode App\ milik tmerp, dan helper global
// yang dipakai demo / komponen x-dl::* (raw()) datang dari paket, bukan dari aplikasi.
class DevlibInheritanceTest extends TestCase
{
    // use App\..., new App\..., atau panggilan statis App\Kelas\Nama::; sebutan di komentar / teks tidak dihitung.
    private const TMERP_USAGE = '/^\s*use\s+\\\\?App\\\\|\bnew\s+\\\\?App\\\\|[=(,\s]\\\\?App\\\\\w+\\\\[\w\\\\]*::/';

    public function test_pattern_catches_tmerp_usage(): void
    {
        foreach (['use App\Models\X;', '$a = new \App\Support\Y();', '$a = \App\Support\V2\Z::run();'] as $line) {
            $this->assertSame(1, preg_match(self::TMERP_USAGE, $line), $line);
        }
        $this->assertSame(0, preg_match(self::TMERP_USAGE, "return 'lewat App\\Support\\V2\\Data\\DeliveryTrack';"));
    }

    public function test_package_code_does_not_use_tmerp_classes(): void
    {
        $offenders = [];
        foreach ((new Finder)->files()->in(base_path('packages/devlib/src'))->name('*.php') as $file) {
            foreach (explode("\n", $file->getContents()) as $n => $line) {
                if (preg_match(self::TMERP_USAGE, $line)) {
                    $offenders[] = $file->getRelativePathname().':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'packages/devlib tidak boleh bergantung pada kode App\ (tmerp).');
    }

    public function test_raw_helper_is_defined_by_the_package(): void
    {
        $this->assertTrue(function_exists('raw'));
        $this->assertSame(realpath(base_path('packages/devlib/src/helpers.php')), realpath((new \ReflectionFunction('raw'))->getFileName()));
        $this->assertStringContainsString('CO2600001', raw('CO2600001'));
    }
}
