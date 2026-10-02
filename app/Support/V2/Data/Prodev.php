<?php

namespace App\Support\V2\Data;

use App\Models\Master\ProdPart;
use App\Models\Sdi\IqpPart;
use App\Models\Sys\SysConstant;
use App\Services\ProDev\PartDieline;
use Devlib\Locale;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use MockupParts\Components\Sales\InquiryPart;

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

    // Pro-Dev > Manual (Mode #2): part master yang TIDAK lahir dari Inquiry. Part dari Inquiry dikerjakan lewat grid
    // Pro-Dev > New / Revise (satu baris = satu inquiry, rulebook A.20).
    public static function parts(): array
    {
        $fromInquiry = IqpPart::whereNotNull('prod_part_id')->distinct()->pluck('prod_part_id');

        return ProdPart::with(['product.customer:id_customer,customer_name', 'layoutGroups'])->whereNotIn('id_part', $fromInquiry)
            ->orderByDesc('id_part')->limit(200)->get()
            ->map(function (ProdPart $p) {
                $latest = $p->layoutGroups->sortByDesc('id_layout_group')->first();
                $n = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
                $status = ! $latest ? t('NOT STARTED') : ($latest->status === 'CONFIRMED' ? 'CONFIRMED' : 'DRAFT');

                return [
                    'id' => $p->id_part,
                    'part_name' => $p->part_name,
                    'code' => $p->mastercard_code ?: $p->part_code,
                    'product' => $p->product?->product_name,
                    'customer' => $p->product?->customer?->customer_name ?? '—',
                    'updated' => $p->updated_at?->toDateString(),
                    'status' => $status,
                    'items' => [['product' => $p->product?->product_name, 'part' => $p->part_name, 'code' => $p->part_code,
                        'size' => $n($p->cm_length).' × '.$n($p->cm_width).' × '.$n($p->cm_height), 'status' => $status]],
                ];
            })->all();
    }

    // Tombol Back workbench: kembali ke grid asal part (Pro-Dev > New / Revise bila part dari Inquiry, selain itu Manual).
    public static function listUrl(int $partId, ?int $inquiryId = null): string
    {
        $type = $inquiryId
            ? \App\Models\Sdi\IqpInquiry::whereKey($inquiryId)->value('inquiry_type')
            : \App\Models\Sdi\IqpInquiry::whereIn('id_inquiry', IqpPart::where('prod_part_id', $partId)->pluck('inquiry_id'))
                ->whereIn('inquiry_type', \App\Services\Sales\InquiryStage::PRODEV_TYPES)->orderByDesc('id_inquiry')->value('inquiry_type');

        return route('v2.screen', $type === 'REVISE' ? 'prodev-revise' : ($type === 'NEW' ? 'prodev-new' : 'prodev'));
    }

    public static function resolve(string $part): ?int
    {
        return ctype_digit($part) && ProdPart::whereKey((int) $part)->exists() ? (int) $part : null;
    }

    // $accUrl = layar cetak ACC Digital product part ini (null = belum ada product) -> pilihan kedua Print / Email di panel atas.
    public static function workbench(int $id, string $backUrl, ?string $costingUrl, ?string $accUrl = null): array
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
            'accUrl' => $accUrl,
            'partId' => $id,
            'docNo' => ProdPart::find($id)?->displayMastercardCode(),
            'serverUrl' => route('v2.prodev.call', $id),
            'uploadUrl' => route('v2.prodev.artwork', $id),
            'uploadMaxMb' => $c->artworkMaxMb(),
            'csrf' => csrf_token(),
        ];
    }

    // Panel Part & Dieline di form ProDev (rulebook A.17): dieline + artwork part ini, dari Inquiry Part asalnya
    // (Mode #1) atau dari master part (Mode #2). null = part belum punya tipe dieline (mis. rakitan / part manual lama).
    public static function dieline(int $partId): ?array
    {
        $part = ProdPart::with('product')->findOrFail($partId);
        $src = PartDieline::origin($partId);
        $spec = ($src?->dieline_spec ?: $part->dieline_spec) ?? [];
        $code = $spec['type_code'] ?? '';
        $types = array_intersect_key(Sales::dielineTypes(), [$code => true]);
        // Rakitan tidak punya dieline sendiri (dieline milik part komponennya), sama seperti di form Inquiry.
        if (! $types || ($src?->part_kind ?? $part->part_kind) === 'ASSEMBLY') {
            return null;
        }

        $url = fn (?string $path) => $path ? asset('storage/'.(Storage::disk('public')->exists($path.'.preview.png') ? $path.'.preview.png' : $path)) : null;
        $master = $src ? collect() : DB::connection('tm2mst')->table('prod_part_artwork')->where('part_id', $partId)->where('activate', true)->get()->keyBy('side');
        $inquiry = $src?->inquiry;
        $num = fn ($v) => $v === null ? '—' : rtrim(rtrim((string) $v, '0'), '.');

        return [
            'id' => $partId, 'part_name' => $part->part_name, 'part_type' => InquiryPart::PART_TYPES[$src?->part_type ?? $part->part_type] ?? ($part->part_type ?: '—'),
            'qty_per_sku' => $num($src?->qty_per_sku ?? $part->qty_per_sku), 'pack_qty' => $num($src?->pack_qty ?? $part->pack_qty),
            'type_code' => $code, 'types' => $types, 'dims' => $spec['dims'] ?? [],
            'artwork_url' => $url($src?->artwork_path ?? $master->get('OUTER')?->artwork_path), 'artwork_name' => $src?->artwork_original_filename ?? $master->get('OUTER')?->original_filename,
            'artwork_inner_url' => $url($src?->artwork_inner_path ?? $master->get('INNER')?->artwork_path), 'artwork_inner_name' => $src?->artwork_inner_original_filename ?? $master->get('INNER')?->original_filename,
            'inquiry_no' => $inquiry?->inquiry_no, 'can_upload' => (bool) $src,
            'updated_by' => $src?->updated_by ?? $part->updated_by, 'updated_at' => Locale::dt($src?->updated_at ?? $part->updated_at),
            // Dampak ke tahap sesudahnya: tidak memblokir (ProDev berhak mengubah), tapi wajib terlihat sebelum Save.
            'layout_confirmed' => $part->layoutGroups()->where('status', 'CONFIRMED')->exists(),
            'costing_published' => (bool) $inquiry?->costing?->published_flag,
            'product_active' => $part->product?->card_status === 'ACTIVE',
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
        } catch (ValidationException $e) {
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
