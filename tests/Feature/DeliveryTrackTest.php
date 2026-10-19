<?php

namespace Tests\Feature;

use App\Models\Sdi\DoDeliveryPod;
use App\Services\Sales\DeliveryPromiseService;
use App\Support\V2\Data\DeliveryTrack;
use App\Support\V2\Data\ProofOfDelivery;
use Devlib\DevlibServiceProvider;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// Delivery Track (user 2026-10-07): objek DevLib x-dl::delivery-track (rute SVG skematis, tanpa GPS) + pemetaan status Delivery Order.
class DeliveryTrackTest extends TestCase
{
    public function test_delivery_order_status_maps_to_stage(): void
    {
        $this->assertSame('draft', DeliveryTrack::stage('DRAFT'));
        $this->assertSame('confirmed', DeliveryTrack::stage('CONFIRMED'));
        $this->assertSame('shipped', DeliveryTrack::stage('SHIPPED'));
        $this->assertSame('shipped', DeliveryTrack::stage('SHIPPED', 'REJECTED'));
        $this->assertSame('delivered', DeliveryTrack::stage('SHIPPED', 'RECEIVED'));
        $this->assertSame('delivered', DeliveryTrack::stage('SHIPPED', 'PARTIAL'));
        $this->assertSame('cancelled', DeliveryTrack::stage('CANCELLED', 'RECEIVED'));
        $this->assertSame('draft', DeliveryTrack::stage('UNKNOWN'));
        $this->assertSame('incident', DeliveryTrack::stage('SHIPPED', 'ACCIDENT'));
        $this->assertSame('incident', DeliveryTrack::stage('CONFIRMED', 'ACCIDENT'));
        $this->assertSame('cancelled', DeliveryTrack::stage('CANCELLED', 'ACCIDENT'));
        $this->assertSame('draft', DeliveryTrack::stage('DRAFT', 'ACCIDENT'));
    }

    public function test_component_renders_route_truck_and_steps(): void
    {
        $html = Blade::render('<x-dl::delivery-track stage="shipped" to="PT Maju" :times="[\'shipped\' => \'07-Oct-2026\']" />');
        $this->assertStringContainsString('data-dl-track', $html);
        $this->assertStringContainsString('data-moving="1"', $html);
        $this->assertStringContainsString('data-dl-track-truck', $html);
        $this->assertStringContainsString('data-dl-track-replay', $html);
        $this->assertStringContainsString('PT Maju', $html);
        $this->assertStringContainsString('is-done', $html);
        $this->assertStringContainsString('is-current', $html);
        $this->assertStringContainsString('07-Oct-2026', $html);
    }

    public function test_position_follows_stage_unless_overridden(): void
    {
        $this->assertSame('1', trim(Blade::render('{{ (new \Devlib\Components\DeliveryTrack(stage: "delivered"))->target() }}')));
        $this->assertStringContainsString('data-target="0.25"', Blade::render('<x-dl::delivery-track stage="shipped" :position="0.25" />'));
        $this->assertStringContainsString('dl-track--draft', Blade::render('<x-dl::delivery-track stage="nonsense" />'));
        $this->assertStringContainsString('dl-track__cancel', Blade::render('<x-dl::delivery-track stage="cancelled" />'));
    }

    public function test_bundle_ships_anime_js_before_the_components(): void
    {
        $scripts = DevlibServiceProvider::scripts();
        $names = array_map('basename', $scripts);
        $this->assertContains('anime.umd.min.js', $names);
        $this->assertLessThan(array_search('core.js', $names, true), array_search('anime.umd.min.js', $names, true));
        $this->assertStringContainsString('window.anime.animate', file_get_contents(dirname(__DIR__, 3).'/devlib/resources/assets/js/components/delivery-track.js'));
    }

    public function test_incident_stage_and_accident_result_are_wired(): void
    {
        $html = Blade::render('<x-dl::delivery-track stage="incident" />');
        $this->assertStringContainsString('dl-track--incident', $html);
        $this->assertStringContainsString('data-target="0.45"', $html);
        $this->assertStringContainsString('data-moving="0"', $html);
        $this->assertStringContainsString('Accident on the way', $html);
        $this->assertStringContainsString('dl-track__warn', $html);
        $this->assertSame('Delivery', DeliveryPromiseService::CAUSES['ACCIDENT'][1]);
        $this->assertArrayHasKey('ACCIDENT', DeliveryPromiseService::RESULTS);
        $this->assertSame('Retry', ProofOfDelivery::state(new DoDeliveryPod(['result' => 'ACCIDENT', 'promise_kept' => false])));
    }
}
