<?php

/*
 * REMARK
 * Tujuan       : Menguji lencana lingkungan: tampil di host mockup (versi dari berkas VERSION, host, IP client) dan tidak ada di host live.
 * Sumber hulu  : App\Support\EnvBadge, berkas VERSION, objek x-dl::env-badge.
 * Input        : host / alamat dikirim ke EnvBadge::title dan EnvBadge::where.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Support\EnvBadge;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EnvBadgeTest extends TestCase
{
    public function test_texts_on_mockup_host(): void
    {
        $this->assertSame(' - mockup', EnvBadge::title('mockup.test'));

        $where = EnvBadge::where('mockup.test', '192.168.1.20', '10.0.0.7');
        $this->assertStringContainsString('Host: '.gethostname().' / 192.168.1.20', $where);
        $this->assertStringContainsString('Client: 10.0.0.7', $where);
        $this->assertStringNotContainsString('mockup', $where);
    }

    public function test_texts_are_empty_on_the_live_host(): void
    {
        $this->assertSame('', EnvBadge::title('tmerp.test'));
        $this->assertSame('', EnvBadge::where('tmerp.test', '192.168.1.20', '10.0.0.7'));
    }

    public function test_object_renders_each_part_and_nothing_when_empty(): void
    {
        $this->app->bind('devlib.env_title', fn () => ' - mockup ver 9.9');
        $this->app->bind('devlib.env_where', fn () => 'Host: H / 1.2.3.4 · Client: 5.6.7.8');
        $this->assertStringContainsString(' - mockup ver 9.9', Blade::render('<x-dl::env-badge part="title" />'));
        $this->assertStringContainsString('Host: H / 1.2.3.4', Blade::render('<x-dl::env-badge part="where" />'));

        $this->app->bind('devlib.env_title', fn () => '');
        $this->assertStringNotContainsString('dl-envbadge', Blade::render('<x-dl::env-badge part="title" />'));
    }
}
