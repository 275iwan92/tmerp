<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\DielineSvgGenerator;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Livewire v4 Component: InquiryForm
 * Supports Full Multi-Part Packaging Inquiries including 2-Piece Shoe Boxes,
 * Real-time dynamic Dieline calculations, 3D exploded box preview, and commercial costing inputs.
 */
class InquiryForm extends Component
{
    use WithFileUploads;

    // Header State
    public string $inquiryNo = 'INQ-2026-09-0842';

    public string $date;

    public string $customerNeedDate;

    public string $salesPic = 'Budi Santoso — Senior Packaging Account Exec';

    public string $source = 'WHATSAPP';

    public string $customer = 'PT MAP Aktif Adiperkasa Tbk (Footwear Division)';

    public string $productNameDraft = 'Dus Sepatu Sneaker Original 2-Pc (Badan & Tutup Terpisah)';

    public string $productCategory = 'Rigid Gift Box';

    public string $productType = 'RIGID_BOX';

    public string $marketVariantId = 'Dus Sepatu / Courier E-Flute Mailer';

    public string $salesNotes = 'Dus sepatu sneakers premium model 2-piece (Lid & Base). Pisau pon dan dieline dibuat 2 set terpisah: 1 untuk badan sepatu dan 1 untuk tutup atas. Tutup harus pas presisi dengan toleransi +5mm agar nyaman dibuka.';

    public bool $customerProvidesRawMaterial = false;

    public string $formStatus = 'DRAFT'; // DRAFT, SAVED, CONFIRMED

    // Active Part & Multi-Part State
    public string $activePartId = 'part-1';

    public string $dielineViewMode = 'SPLIT'; // SPLIT or SINGLE

    public string $unit = 'mm';

    public float $lidElevation = 35.0; // 3D Exploded view height (px)

    public bool $autoSpin = false;

    // Multi-Part Array (Default: 2-Piece Shoe Box)
    public array $parts = [];

    // Tiered Quantities
    public array $tiers = [];

    // Materials
    public array $materials = [];

    // Print & Color
    public string $colorNotation = '4/0';

    public string $colorNotesPantone = 'Pantone Cool Gray 8 C + Matte Black Solid';

    // Finishing
    public array $finishings = [];

    // Tooling
    public array $toolings = [];

    // External Costs
    public array $externalCosts = [];

    // Flash notification message
    public ?string $toastMessage = null;

    public function mount(): void
    {
        $this->date = date('Y-m-d');
        $this->customerNeedDate = date('Y-m-d', strtotime('+21 days'));

        // Initialize Parts: 2-Piece Shoe Box (Body & Separate Lid)
        $this->parts = [
            [
                'id' => 'part-1',
                'partName' => 'Badan Sepatu (Base Tray / Bottom)',
                'partType' => 'Base / Tray',
                'componentType' => 'BODY_TRAY',
                'qtyPerSet' => 1,
                'contentPerPack' => 1,
                'dimensions' => [
                    'length' => 320.0,
                    'width' => 200.0,
                    'height' => 120.0,
                    'glueFlap' => 15.0,
                    'bleed' => 3.0,
                    'tuckFlap' => 15.0,
                    'hasThumbHole' => false,
                    'thumbHoleRadius' => 8.0,
                    'isManualFlatSize' => false,
                    'manualFlatWidth' => 0.0,
                    'manualFlatHeight' => 0.0,
                ],
                'materialSummary' => 'Corrugated E-Flute + Duplex 310gsm',
                'colorNotation' => '4/0',
                'finishingSummary' => 'Waterbased Varnish + Diecutting',
                'notes' => 'Konstruksi baki lipat roll-end tray kokoh menahan beban sepasang sepatu.',
            ],
            [
                'id' => 'part-2',
                'partName' => 'Tutup Sepatu (Top Lid / Cover)',
                'partType' => 'Lid / Cover',
                'componentType' => 'LID_COVER',
                'qtyPerSet' => 1,
                'contentPerPack' => 1,
                'dimensions' => [
                    'length' => 325.0,
                    'width' => 205.0,
                    'height' => 35.0,
                    'glueFlap' => 15.0,
                    'bleed' => 3.0,
                    'tuckFlap' => 15.0,
                    'hasThumbHole' => true,
                    'thumbHoleRadius' => 10.0,
                    'isManualFlatSize' => false,
                    'manualFlatWidth' => 0.0,
                    'manualFlatHeight' => 0.0,
                ],
                'materialSummary' => 'Ivory Board 350gsm Foodgrade / Premium',
                'colorNotation' => '4/0 + Pantone',
                'finishingSummary' => 'Thermal Matte Lamination + Spot UV 3D Logo',
                'notes' => 'Tutup dibuat lebih longgar +5mm dari badan agar dapat menutup mulus.',
            ],
        ];

        // Default Tier Quantities
        $this->tiers = [
            ['id' => 't-1', 'tierNumber' => 1, 'qty' => 1000, 'isPrimary' => true],
            ['id' => 't-2', 'tierNumber' => 2, 'qty' => 3000, 'isPrimary' => false],
            ['id' => 't-3', 'tierNumber' => 3, 'qty' => 5000, 'isPrimary' => false],
            ['id' => 't-4', 'tierNumber' => 4, 'qty' => 10000, 'isPrimary' => false],
        ];

        // Materials
        $this->materials = [
            [
                'id' => 'm-1',
                'materialName' => 'Corrugated Sheet E-Flute (Cikarang Box)',
                'brand' => 'Cikarang Box Board',
                'materialType' => 'E-Flute Single Wall Corrugated',
                'gsm' => 350,
                'isPrimary' => true,
            ],
            [
                'id' => 'm-2',
                'materialName' => 'Ivory Board Food Grade (Ningbo Star APP)',
                'brand' => 'Ningbo Star APP',
                'materialType' => 'FBB / GC1 Virgin Pulp',
                'gsm' => 350,
                'isPrimary' => false,
            ],
        ];

        // Finishing
        $this->finishings = [
            ['id' => 'f-1', 'process' => 'Thermal Matte Lamination', 'side' => 'OUTER_1_SIDE', 'coverage' => 100, 'notes' => 'Lapisan doff elegan pada tutup'],
            ['id' => 'f-2', 'process' => 'UV Spot Screen 3D', 'side' => 'OUTER_1_SIDE', 'coverage' => 20, 'notes' => 'Highlight logo sneaker'],
            ['id' => 'f-3', 'process' => 'Auto Folding-Gluing', 'side' => 'BOTH_SIDES', 'coverage' => 100, 'notes' => 'Lem sudut presisi mesin Bobst'],
        ];

        // Tooling
        $this->toolings = [
            ['id' => 'tl-1', 'toolingType' => 'Laser Diecut Knife Block (Pisau Pon Badan)', 'status' => 'NEW', 'isNewCost' => true, 'cost' => 1850000, 'notes' => 'Pisau pon flatbed laser plywood 18mm untuk badan sepatu'],
            ['id' => 'tl-2', 'toolingType' => 'Laser Diecut Knife Block (Pisau Pon Tutup)', 'status' => 'NEW', 'isNewCost' => true, 'cost' => 1450000, 'notes' => 'Pisau pon terpisah untuk tutup sepatu'],
            ['id' => 'tl-3', 'toolingType' => 'CTP Offset Printing Plates (4 Plat Badan + 4 Plat Tutup)', 'status' => 'NEW', 'isNewCost' => true, 'cost' => 1200000, 'notes' => 'Thermal CTP 8 plat (2 plano)'],
        ];

        // External Costs
        $this->externalCosts = [
            ['id' => 'ec-1', 'description' => 'Trucking Delivery Colt Diesel Double (CDD) Jabodetabek', 'costType' => 'FREIGHT_DELIVERY', 'amount' => 1250000, 'taxTreatment' => 'NON_PPN'],
            ['id' => 'ec-2', 'description' => 'Packaging Drop Test & Compression Strength Lab COA', 'costType' => 'LAB_TESTING', 'amount' => 600000, 'taxTreatment' => 'PPN_11'],
        ];
    }

    public function selectPart(string $partId): void
    {
        $this->activePartId = $partId;
    }

    public function setDielineViewMode(string $mode): void
    {
        $this->dielineViewMode = $mode;
    }

    public function toggleAutoSpin(): void
    {
        $this->autoSpin = ! $this->autoSpin;
    }

    public function addTier(): void
    {
        $lastQty = end($this->tiers)['qty'] ?? 1000;
        $nextTierNum = count($this->tiers) + 1;
        $this->tiers[] = [
            'id' => 't-'.uniqid(),
            'tierNumber' => $nextTierNum,
            'qty' => (int) ($lastQty * 1.5),
            'isPrimary' => false,
        ];
    }

    public function deleteTier(string $tierId): void
    {
        if (count($this->tiers) <= 1) {
            return;
        }
        $this->tiers = array_values(array_filter($this->tiers, fn ($t) => $t['id'] !== $tierId));
        if (! in_array(true, array_column($this->tiers, 'isPrimary'))) {
            $this->tiers[0]['isPrimary'] = true;
        }
    }

    public function setPrimaryTier(string $tierId): void
    {
        foreach ($this->tiers as &$t) {
            $t['isPrimary'] = ($t['id'] === $tierId);
        }
    }

    public function saveDraft(): void
    {
        $this->formStatus = 'SAVED';
        $this->toastMessage = "Draft tersimpan dengan sukses (INQ: {$this->inquiryNo})";
    }

    public function confirmInquiry(): void
    {
        $this->validate([
            'customer' => 'required|string|min:3',
            'productNameDraft' => 'required|string|min:3',
        ]);
        $this->formStatus = 'CONFIRMED';
        $this->toastMessage = 'Inquiry telah DIKONFIRMASI! Data siap dialihkan ke tim Prepress CAD & Kalkulasi HPP.';
    }

    public function render(DielineSvgGenerator $dielineGenerator)
    {
        $activePart = collect($this->parts)->firstWhere('id', $this->activePartId) ?? $this->parts[0];

        // Generate SVG string for active part
        $activeSvg = $dielineGenerator->generate(
            partName: $activePart['partName'],
            partType: $activePart['partType'],
            length: (float) $activePart['dimensions']['length'],
            width: (float) $activePart['dimensions']['width'],
            height: (float) $activePart['dimensions']['height'],
            glueFlap: (float) $activePart['dimensions']['glueFlap'],
            tuckFlap: (float) $activePart['dimensions']['tuckFlap'],
            unit: $this->unit
        );

        // Pre-generate SVGs for all parts if in split mode
        $splitSvgs = [];
        foreach ($this->parts as $p) {
            $splitSvgs[$p['id']] = $dielineGenerator->generate(
                partName: $p['partName'],
                partType: $p['partType'],
                length: (float) $p['dimensions']['length'],
                width: (float) $p['dimensions']['width'],
                height: (float) $p['dimensions']['height'],
                glueFlap: (float) $p['dimensions']['glueFlap'],
                tuckFlap: (float) $p['dimensions']['tuckFlap'],
                unit: $this->unit
            );
        }

        return view('livewire.inquiry-form', [
            'activePart' => $activePart,
            'activeSvg' => $activeSvg,
            'splitSvgs' => $splitSvgs,
        ]);
    }
}
