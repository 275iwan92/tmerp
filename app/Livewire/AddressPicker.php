<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

// Komponen reusable — cari wilayah lewat v_geo_full_address (5-level: kelurahan
// > kecamatan > kota > provinsi > negara), dipakai Master Customer & module lain
// yang butuh pilih wilayah (Supplier, Shipping Address, dst). Spec:
// docs/customer_fix_nav_address.md §B.3.
//
// Komunikasi ke parent lewat event `address-picked` (bukan wire:model ganda —
// Livewire wire:model cuma bind 1 properti, sedangkan hasil pilih wilayah butuh
// 3 nilai sekaligus: areaId + areaLevel + postalCode), parent dengarkan lewat
// #[On('address-picked')].
class AddressPicker extends Component
{
    public string $fieldName = 'default';

    public string $search = '';

    public bool $open = false;

    public ?string $selectedLabel = null;

    public ?int $selectedAreaId = null;

    public ?string $selectedAreaLevel = null;

    public function mount(?int $initialAreaId = null, ?string $initialAreaLevel = null, ?string $initialLabel = null, string $fieldName = 'default'): void
    {
        $this->fieldName = $fieldName;
        $this->selectedAreaId = $initialAreaId;
        $this->selectedAreaLevel = $initialAreaLevel;
        $this->selectedLabel = $initialLabel;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->search = '';
        }
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function getResultsProperty()
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return DB::connection('tm2mst')->table('v_geo_full_address')
            ->select('id_village', 'id_district', 'id_city', 'full_label', 'postal_code')
            ->where(function ($q) use ($term) {
                $q->where('full_label', 'like', "%{$term}%")
                    ->orWhere('postal_code', 'like', "{$term}%");
            })
            ->orderBy('full_label')
            ->limit(15)
            ->get();
    }

    public function select(?int $idVillage, ?int $idDistrict, ?int $idCity, string $label, ?string $postalCode): void
    {
        [$areaId, $areaLevel] = match (true) {
            $idVillage !== null => [$idVillage, 'VILLAGE'],
            $idDistrict !== null => [$idDistrict, 'DISTRICT'],
            default => [$idCity, 'CITY'],
        };

        $this->selectedAreaId = $areaId;
        $this->selectedAreaLevel = $areaLevel;
        $this->selectedLabel = $label;
        $this->open = false;
        $this->search = '';

        $this->dispatch('address-picked', fieldName: $this->fieldName, areaId: $areaId, areaLevel: $areaLevel, label: $label, postalCode: $postalCode);
    }

    public function clear(): void
    {
        $this->selectedAreaId = null;
        $this->selectedAreaLevel = null;
        $this->selectedLabel = null;

        $this->dispatch('address-picked', fieldName: $this->fieldName, areaId: null, areaLevel: null, label: null, postalCode: null);
    }

    public function render()
    {
        return view('livewire.address-picker');
    }
}
