<?php

namespace Tests\Feature;

use App\Http\Middleware\KeepScreensInShell;
use Illuminate\Http\Request;
use Tests\TestCase;

// Layar menu tidak boleh tampil sendirian (tanpa shell) di situs v2: dialihkan ke /menu?open=<kode>.
// Tidak menyentuh database (rute v2.item & peta MenuRoutes saja).
class KeepScreensInShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.ui' => 'v2']);
    }

    private function visit(string $url, string $dest = 'document', string $method = 'GET'): string
    {
        $request = Request::create($url, $method, server: ['HTTP_SEC_FETCH_DEST' => $dest]);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        $response = (new KeepScreensInShell)->handle($request, fn () => response('next'));

        return $response->isRedirect() ? $response->headers->get('Location') : $response->getContent();
    }

    public function test_v2_screen_opened_as_full_page_is_sent_back_into_the_shell(): void
    {
        $this->assertSame(route('menu').'?open=SALES_INQ', $this->visit(route('v2.item', 'SALES_INQ')));
    }

    public function test_v1_menu_screen_opened_as_full_page_is_sent_back_into_the_shell(): void
    {
        $this->assertSame(route('menu').'?open=SALES_SO', $this->visit(route('co.list')));
    }

    public function test_screen_inside_a_tab_iframe_is_served_normally(): void
    {
        $this->assertSame('next', $this->visit(route('v2.item', 'SALES_INQ'), 'iframe'));
    }

    public function test_non_menu_pages_and_posts_are_left_alone(): void
    {
        $this->assertSame('next', $this->visit(route('menu')));
        $this->assertSame('next', $this->visit(route('v2.inquiry.save'), 'document', 'POST'));
    }

    public function test_v1_site_is_unchanged(): void
    {
        config(['app.ui' => 'v1']);

        $this->assertSame('next', $this->visit(route('co.list')));
    }
}
