<?php

namespace App\Support\V2\Data;

use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

// Data layar ProDev v2 = komponen tmerp v1 `pages::production.prodev` yang SAMA (zona beku: flow & rumus tidak disalin,
// dipanggil langsung). Daftar part = query prodev-list v1. Panggilan server workbench (Save/Confirm, BOM/Ink/BOP,
// PR, subkon, warna artwork, upload artwork) lewat route v2.prodev.* ke method komponen yang sama.
final class Prodev
{
    // Method komponen v1 yang boleh dipanggil dari workbench.js (server(m, ...a)).
    public const CALLS = ['saveLayout', 'generateSpec', 'saveArtworkColors', 'searchMaterials', 'createPurchase', 'subcontract'];

    public static function component(int $partId): Component
    {
        $c = app('livewire')->new('pages::production.prodev');
        $c->partId = $partId;
        $c->activeJobPartId = $partId;

        return $c;
    }

    public static function parts(): array
    {
        $fromInquiry = IqpPart::whereNotNull('prod_part_id')->distinct()->pluck('prod_part_id')->flip();

        return ProdPart::with(['product', 'layoutGroups'])->orderByDesc('id_part')->limit(200)->get()
            ->map(function (ProdPart $p) use ($fromInquiry) {
                $latest = $p->layoutGroups->sortByDesc('id_layout_group')->first();

                return [
                    'id' => $p->id_part,
                    'part_name' => $p->part_name,
                    'code' => $p->mastercard_code ?: $p->part_code,
                    'product' => $p->product?->product_name,
                    'origin' => $fromInquiry->has($p->id_part) ? 'INQUIRY' : 'MANUAL',
                    'status' => ! $latest ? 'BELUM MULAI' : ($latest->status === 'CONFIRMED' ? 'CONFIRMED' : 'DRAFT'),
                ];
            })->all();
    }

    public static function resolve(string $part): ?int
    {
        return ctype_digit($part) && ProdPart::whereKey((int) $part)->exists() ? (int) $part : null;
    }

    public static function workbench(int $id, string $backUrl, ?string $costingUrl): array
    {
        $c = self::component($id);

        return [
            'jobs' => $c->jobs(),
            'machines' => $c->machines(),
            'dieCutters' => $c->dieCutters(),
            'planos' => $c->planos(),
            'initialJob' => $c->initialJobIndex(),
            'jobIssue' => $c->jobIssue(),
            'lookups' => $c->lookups(),
            'gripperMm' => SysConstant::number('PRODEV_GRIPPER_MM', 12.0),
            'marginMm' => SysConstant::number('PRODEV_MARGIN_MM', 8.0),
            'backUrl' => $backUrl,
            'costingUrl' => $costingUrl,
            'serverUrl' => route('v2.prodev.call', $id),
            'uploadUrl' => route('v2.prodev.artwork', $id),
            'uploadMaxMb' => $c->artworkMaxMb(),
            'csrf' => csrf_token(),
        ];
    }

    public static function call(int $partId, string $method, array $args): mixed
    {
        abort_unless(in_array($method, self::CALLS, true), 404);

        return self::component($partId)->{$method}(...array_values($args));
    }

    // Upload artwork = updatedArtworkUpload() v1 (disk public, iqp_part.artwork_path + analisa warna).
    public static function uploadArtwork(int $partId, UploadedFile $file): array
    {
        $c = self::component($partId);
        $c->artworkUpload = $file;

        try {
            $c->updatedArtworkUpload();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ['ok' => false, 'message' => collect($e->errors())->flatten()->first()];
        }

        if ($c->artworkError !== '') {
            return ['ok' => false, 'message' => $c->artworkError];
        }

        $iqp = IqpPart::where('prod_part_id', $partId)->first();
        $path = $iqp->artwork_path;
        $preview = Storage::disk('public')->exists($path.'.preview.png') ? $path.'.preview.png' : $path;

        return ['ok' => true, 'partId' => $partId, 'url' => asset('storage/'.$preview),
            'name' => $iqp->artwork_original_filename, 'colors' => $iqp->artwork_colors];
    }
}
