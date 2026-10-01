<?php

namespace App\Services\ProDev;

use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\ProdType;
use App\Models\Sdi\IqpPart;
use App\Services\Sales\InquiryWriter;
use App\Services\Sales\ProductMasterSync;
use App\Support\V2\Data\Prodev;
use Illuminate\Http\UploadedFile;

// ProDev berhak mengubah dieline & mockup (artwork) part (docs/globaluserrules.md A.17, user 2026-10-01).
// Simpan DUA ARAH seperti Save ProDev lainnya: snapshot inquiry (iqp_part) + master (prod_part). Part Mode #2
// (tanpa Inquiry) hanya punya arah master. Bentuk dieline_spec = sama dengan InquiryWriter::persistParts
// (DSL prod_type + dims + type_code), jadi workbench imposition membaca hasilnya tanpa jalur baru.
final class PartDieline
{
    public function __construct(private string $login) {}

    public static function origin(int $partId): ?IqpPart
    {
        return IqpPart::where('prod_part_id', $partId)->first();
    }

    // $in = App\Support\V2\InquiryPayload::dieline(). Mengembalikan pesan gagal upload artwork (dieline tetap tersimpan), null = beres.
    public function save(ProdPart $part, array $in, ?UploadedFile $artwork = null, ?UploadedFile $inner = null): ?string
    {
        $src = self::origin($part->id_part);
        $old = ($src?->dieline_spec ?: $part->dieline_spec) ?? [];
        $type = ProdType::find($old['type_code'] ?? '');

        $spec = ($type?->dieline_spec ?: $old);
        $spec['type_code'] = $old['type_code'] ?? null;
        // Diganti utuh (bukan digabung) seperti Inquiry: kunci yang tidak dikirim form, mis. hole_* saat Hole = None, harus hilang.
        $spec['dims'] = $in['dims'];

        $flat = array_map(fn ($v) => $v !== '' ? $v : null, ['flat_w' => $in['flat_w'], 'flat_h' => $in['flat_h'], 'flat_area' => $in['flat_area']]);
        $cm = fn (string $k) => is_numeric($spec['dims'][$k] ?? null) ? round(((float) $spec['dims'][$k]) / 10, 2) : null;
        $size = array_filter(['cm_length' => $cm('product_length'), 'cm_width' => $cm('product_width'), 'cm_height' => $cm('product_height')], fn ($v) => $v !== null);

        $src?->update(['dieline_spec' => $spec, 'updated_by' => $this->login] + $flat);
        $part->update(['dieline_spec' => $spec, 'updated_by' => $this->login] + $flat + $size);

        // Ukuran di kepala Master Product = ukuran komponen pertamanya (aturan ProductMasterSync::fromInquiry).
        $product = ProdProduct::find($part->product_id);
        $active = $product?->card_status === 'ACTIVE';
        if ($active && $size && (int) $part->id_part === (int) ProdPart::where('product_id', $product->id_product)->where('part_kind', 'COMPONENT')->min('id_part')) {
            $product->update($size + ['updated_by' => $this->login]);
        }

        if (! $src) {
            return null;
        }

        $error = null;
        if ($artwork) {
            // Jalur upload ProDev yang sudah ada: file + analisa warna tinta (dipakai Ink Configuration).
            $res = Prodev::uploadArtwork($part->id_part, $artwork);
            $error = $res['ok'] ? null : $res['message'];
        }
        if ($inner) {
            (new InquiryWriter($this->login))->storeArtwork($src, $inner, true);
        }
        // Product sudah resmi: artwork baru = versi baru di master (belum di-ACC; ACC Digital wajib diulang).
        if ($active && ($artwork || $inner)) {
            (new ProductMasterSync($this->login))->syncArtwork($part, $src->refresh());
        }

        return $error;
    }
}
