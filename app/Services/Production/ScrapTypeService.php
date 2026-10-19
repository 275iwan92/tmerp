<?php

/*
 * REMARK
 * Tujuan       : Master Tipe Scrap (prd_scrap_type: Reject = barang jadi cacat, Waste = bahan / proses terbuang) dari layar v2 prod-scrap-form: simpan, aktif / nonaktif, jumlah pemakaian di catatan output produksi, riwayat.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.scrap-type (diarsipkan di tmerp_unused/production_scrap_type_v1_2026-10-19.blade.php): kode wajib maks 30 (huruf besar, spasi jadi garis bawah), nama wajib maks 100. Baru: kode unik per kategori (v1 tidak memeriksa, bentrok = error database), Sort order bisa diisi (v1 selalu 500 untuk tipe baru), kategori dan kode terkunci bila tipe sudah dipakai di prd_wo_operation_output_defect.
 * Input        : Isian form (category, type_code, type_name, sort_order), user.
 * Output       : tm2prd.prd_scrap_type berubah; ValidationException bila aturan dilanggar.
 * Dipakai oleh : routes/v2_production.php (v2.prod.scrap.save), layar prod-scrap-form, grid ScrapTypeGrid, tes ScrapTypeServiceTest.
 * Status       : Aktif (Master T6b, 2026-10-19). Data awal tipe scrap berasal dari migration 2026_08_28_000013; daftar riil pabrik menyusul dari Jo.
 */

namespace App\Services\Production;

use App\Models\Prd\PrdScrapType;
use App\Models\Prd\PrdWoOperationOutputDefect;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ScrapTypeService
{
    public const CATEGORIES = ['REJECT' => 'Reject (defective finished goods)', 'WASTE' => 'Waste (material/process lost)'];

    /** Jumlah catatan output produksi yang memakai tipe ini. */
    public function usage(PrdScrapType $t): int
    {
        return PrdWoOperationOutputDefect::where('scrap_type_id', $t->type_id)->count();
    }

    public function save(?PrdScrapType $t, array $in, SecUser $user): PrdScrapType
    {
        $used = $t && $this->usage($t) > 0;
        if ($used) {
            // Kategori dan kode ditampilkan saja pada tipe yang sudah dipakai, jadi tidak ikut terkirim.
            $in['category'] ??= $t->category;
            $in['type_code'] ??= $t->type_code;
        }
        $in['type_code'] = strtoupper(str_replace(' ', '_', trim((string) ($in['type_code'] ?? ''))));
        $in['sort_order'] = ($in['sort_order'] ?? '') === '' ? null : $in['sort_order'];
        if ($used && ((string) ($in['category'] ?? '') !== $t->category || $in['type_code'] !== $t->type_code)) {
            throw ValidationException::withMessages(['type_code' => 'This type is already used in production output records, so its category and code cannot change. Deactivate it and add a new type instead.']);
        }
        $category = (string) ($in['category'] ?? '');
        $d = Validator::make($in, [
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'type_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('tm2prd.prd_scrap_type', 'type_code')->where('category', $category)->ignore($t?->type_id, 'type_id')],
            'type_name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['type_code.unique' => 'This code already exists in the same category.', 'type_code.regex' => 'Use letters, digits, underscore or hyphen only.'],
            ['category' => 'Category', 'type_code' => 'Code', 'type_name' => 'Type name', 'sort_order' => 'Sort order'])->validate();

        $payload = ['category' => $d['category'], 'type_code' => $d['type_code'], 'type_name' => trim($d['type_name']), 'updated_by' => $user->login];
        if ($t) {
            $t->update($payload + ($d['sort_order'] !== null ? ['sort_order' => (int) $d['sort_order']] : []));

            return $t;
        }

        return PrdScrapType::create($payload + ['activate' => true, 'sort_order' => $d['sort_order'] !== null ? (int) $d['sort_order'] : 500, 'created_by' => $user->login]);
    }

    public function setActive(PrdScrapType $t, bool $active, SecUser $user): void
    {
        $t->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(PrdScrapType $t): array
    {
        $rows = [['at' => $t->created_at?->toDateTimeString(), 'by' => $t->created_by, 'action' => 'Created', 'note' => '']];
        if ($t->updated_at && $t->updated_at != $t->created_at) {
            $rows[] = ['at' => $t->updated_at->toDateTimeString(), 'by' => $t->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
