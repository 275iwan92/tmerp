<?php

namespace App\Support\V2\Grid\Planning;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Models\Sdi\CoOrder;
use App\Support\V2\Data\MaterialPlanning;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Material Booking: satu baris per Customer Order aktif (+ forecast MPS), isi dari MaterialPlanningEngine (bukan query satu tabel).
// Klik 2x = booking CO itu: Recalculate / Pick Item / Confirm (kunci) / Back to Draft.
class MaterialBookingGrid extends DbGrid
{
    public function slug(): string
    {
        return 'material-booking';
    }

    public function title(): string
    {
        return 'Material Booking';
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
        return 'fa-solid fa-boxes-packing';
    }

    public function search(): string
    {
        return 'Search CO no., customer...';
    }

    protected function query(): Builder
    {
        return RmBooking::query();
    }

    protected function models(): array
    {
        return [RmBooking::class, CoOrder::class, RmBookingD::class];
    }

    protected function row(Model $m): array
    {
        return [];
    }

    public function rows(): array
    {
        return MaterialPlanning::coRows();
    }

    public function columns(): array
    {
        return [
            ['key' => 'co', 'label' => 'CO No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'co_status', 'label' => 'CO Status', 'type' => 'badge', 'tones' => MaterialPlanning::CO_TONES],
            ['key' => 'need', 'label' => 'Material Needed By', 'type' => 'date'],
            ['key' => 'short', 'label' => 'Lines Not Covered', 'type' => 'number'],
            ['key' => 'cover', 'label' => 'Coverage', 'type' => 'badge', 'tones' => MaterialPlanning::COVER_TONES],
            ['key' => 'booking_no', 'label' => 'Booking No.'],
            ['key' => 'booking', 'label' => 'Booking', 'type' => 'badge', 'tones' => MaterialPlanning::BOOKING_TONES],
            ['key' => 'changed', 'label' => 'Note', 'type' => 'badge', 'tones' => ['CO changed' => 'danger']],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product & Material', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'part', 'label' => 'Part'],
            ['key' => 'material', 'label' => 'Material'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'uom', 'label' => 'UoM'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Coverage'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Short', 'where' => ['cover' => 'Short'], 'tone' => 'danger'],
            ['label' => 'Need item', 'where' => ['cover' => ['Need item', 'Need layout']], 'tone' => 'danger'],
            ['label' => 'Late', 'where' => ['cover' => 'Late'], 'tone' => 'warn'],
            ['label' => 'To confirm', 'where' => ['booking' => 'DRAFT'], 'tone' => 'warn'],
            ['label' => 'Confirmed', 'where' => ['booking' => 'CONFIRMED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'co_status' => 'CO Status', 'booking' => 'Booking'];
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
        return ['view' => 'v2.screens.material-booking'] + parent::screen();
    }
}
