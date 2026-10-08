<?php

declare(strict_types=1);

namespace App\Support\V2\Grid\Planning;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Data\PreBookings;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Pre-Booking (B-01..B-04): kebutuhan kertas sejak ProCost terbit, sebelum ada Customer Order. Satu baris per inquiry;
// Open = rinci dengan Pick Item / Hold. Isi: App\Support\V2\Data\PreBookings; aturan: MaterialPlanningEngine.
class PreBookingGrid extends DbGrid
{
    public function slug(): string
    {
        return 'material-prebooking';
    }

    public function title(): string
    {
        return 'Pre-Booking';
    }

    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Planning';
    }

    public function icon(): string
    {
        return 'fa-solid fa-hourglass-half';
    }

    public function search(): string
    {
        return 'Search inquiry, customer, product...';
    }

    protected function query(): Builder
    {
        return RmBooking::query();
    }

    protected function models(): array
    {
        return [RmBooking::class, RmBookingD::class];
    }

    protected function row(Model $m): array
    {
        return [];
    }

    public function rows(): array
    {
        return PreBookings::rows();
    }

    public function columns(): array
    {
        return [
            ['key' => 'inquiry', 'label' => 'Inquiry / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'product', 'label' => 'Product'],
            ['key' => 'stage', 'label' => 'Stage', 'type' => 'badge', 'tones' => PreBookings::STAGE_TONES],
            ['key' => 'need', 'label' => 'Material Needed By', 'type' => 'date'],
            ['key' => 'short', 'label' => 'Lines Not Covered', 'type' => 'number'],
            ['key' => 'cover', 'label' => 'Coverage', 'type' => 'badge', 'tones' => MaterialPlanning::COVER_TONES],
            ['key' => 'booking_no', 'label' => 'Booking No.'],
            ['key' => 'status', 'label' => 'Booking', 'type' => 'badge', 'tones' => PreBookings::STATUS_TONES],
            ['key' => 'hold_until', 'label' => 'Hold Until', 'type' => 'date'],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Material', 'columns' => [
            ['key' => 'material', 'label' => 'Material', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Coverage'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Not CO yet', 'where' => ['stage' => 'Not CO yet'], 'tone' => 'info'], ['label' => 'Quotation', 'where' => ['stage' => 'Quotation'], 'tone' => 'info'],
            ['label' => 'Short', 'where' => ['cover' => 'Short'], 'tone' => 'danger'], ['label' => 'Held', 'where' => ['status' => 'Held'], 'tone' => 'warn']];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'stage' => 'Stage', 'status' => 'Booking'];
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.material-prebooking'] + parent::screen();
    }
}
