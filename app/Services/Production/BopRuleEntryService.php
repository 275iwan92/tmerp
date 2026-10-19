<?php

/*
 * REMARK
 * Tujuan       : Aturan Bill of Process (prod_bop_rule: langkah standar, sisipan finishing, bahan BOM per langkah) dari layar v2 prod-bop-form: simpan, aktif / nonaktif, usulan takaran dari data nyata (terapkan, Auto apply, hitung ulang). Hanya admin yang boleh mengubah. Tidak menyentuh engine ProDev (SpecEngine hanya membaca tabel ini).
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.bop-rule (diarsipkan di tmerp_unused/production_bop_rule_v1_2026-10-19.blade.php): jenis STANDARD / FINISHING, finishing wajib untuk FINISHING, proses kosong hanya untuk aturan bahan, label langkah wajib bila ada proses, peran bahan wajib bila ada pola bahan, waste dan toleransi 0 sampai 100, kondisi hanya untuk STANDARD. Usulan: App\Services\ProDev\UsageRateEngine::refresh (3 bulan terakhir). Baru: Seq diisi langsung (menggantikan panah naik / turun v1).
 * Input        : Isian form (rule_scope, finishing_type_id, part_type, step_seq, process_id, step_label, merge_group, merge_label, machine_role, condition_code, material_pattern, material_rule, material_role, usage_basis, usage_rate, waste_pct, tolerance_pct, note, auto_apply), user.
 * Output       : tm2mst.prod_bop_rule berubah (kolom usulan hanya oleh UsageRateEngine; Apply suggestion menyalin usulan ke usage_rate / waste_pct dan menandai rate_source AUTO); ValidationException bila aturan dilanggar atau user bukan admin.
 * Dipakai oleh : routes/v2_production.php (v2.prod.bop.*), layar prod-bop-form, grid BopRuleGrid, tes BopRuleEntryServiceTest.
 * Status       : Aktif (Master T6b, 2026-10-19). Data 26 aturan tidak diubah oleh pemindahan layar.
 */

namespace App\Services\Production;

use App\Models\Master\ProdBopRule;
use App\Models\Master\ProdProcess;
use App\Models\Master\RefFinishingType;
use App\Models\Sys\SecUser;
use App\Services\ProDev\UsageRateEngine;
use Devlib\Locale;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BopRuleEntryService
{
    public const SCOPES = ['STANDARD' => 'Standard step', 'FINISHING' => 'Finishing insert'];

    public const PART_TYPES = ['DC' => 'DC', 'DC+SHEET' => 'DC+SHEET', 'DC+SF' => 'DC+SF', 'SHEET' => 'SHEET', 'KRAFT' => 'KRAFT'];

    public const CONDITIONS = [
        'PLANO_NEEDS_CUT' => 'Plano size differs from the print sheet',
        'MULTI_LAYER' => 'Layered material (more than 1 material / DC+SF / DC+SHEET)',
        'NEEDS_GLUE' => 'Structure needs glue (glue / lock bottom / crash)',
    ];

    public const ROLES = ['PRINT' => 'Print machine chosen by ProDev', 'DIECUT' => 'Die-cut machine chosen by ProDev'];

    public const MATERIAL_RULES = [
        'ROLL_WIDTH_GE_SHEET' => 'Smallest roll whose width is >= the long side of the sheet',
        'MATCH_ATTRIBUTE' => 'Material name that best matches the finishing attribute',
        'BEST_STOCK' => 'Matching material with the most warehouse stock',
        'INK_BY_NAME' => 'Ink matching the name of each unit ({ink} in the pattern)',
    ];

    public const USAGE = [
        'SHEET_LENGTH' => 'Meters per sheet (short side) x usage rate',
        'SHEET_AREA' => 'Grams per m² of print sheet',
        'INK_COVERAGE' => 'Grams per m² of ink-covered area',
        'PER_PCS' => 'Grams per product pcs',
        'PER_PACK' => 'Meters per pack',
        'AREA_UNKNOWN' => 'Usage area cannot be calculated yet (enter manually)',
    ];

    public function __construct(private UsageRateEngine $usage) {}

    public function canEdit(?SecUser $user): bool
    {
        return (bool) $user?->is_admin;
    }

    private function guard(SecUser $user): void
    {
        if (! $this->canEdit($user)) {
            throw ValidationException::withMessages(['document' => 'Only an admin can change the BOP rules.']);
        }
    }

    public function save(?ProdBopRule $r, array $in, SecUser $user): ProdBopRule
    {
        $this->guard($user);
        foreach (['usage_rate', 'waste_pct', 'tolerance_pct'] as $k) {
            $in[$k] = ($in[$k] ?? '') === '' ? null : Locale::input($in[$k]);
        }
        $d = Validator::make($in, [
            'rule_scope' => ['required', 'in:STANDARD,FINISHING'],
            'finishing_type_id' => ['required_if:rule_scope,FINISHING', 'nullable', 'exists:tm2mst.ref_finishing_type,id_finishing_type'],
            'part_type' => ['nullable', 'in:'.implode(',', array_keys(self::PART_TYPES))],
            'step_seq' => ['required', 'integer', 'min:0', 'max:9999'],
            // Baris tanpa proses = aturan BAHAN saja (mis. lem laminasi).
            'process_id' => ['nullable', 'required_without:material_pattern', 'exists:tm2mst.prod_process,id_process'],
            'step_label' => ['nullable', 'required_with:process_id', 'string', 'max:120'],
            'merge_group' => ['nullable', 'string', 'max:20'],
            'merge_label' => ['nullable', 'string', 'max:60'],
            'machine_role' => ['nullable', 'in:'.implode(',', array_keys(self::ROLES))],
            'condition_code' => ['nullable', 'in:'.implode(',', array_keys(self::CONDITIONS))],
            'material_pattern' => ['nullable', 'string', 'max:150'],
            'material_rule' => ['nullable', 'in:'.implode(',', array_keys(self::MATERIAL_RULES))],
            'material_role' => ['nullable', 'required_with:material_pattern', 'string', 'max:30'],
            'usage_basis' => ['nullable', 'in:'.implode(',', array_keys(self::USAGE))],
            'usage_rate' => ['nullable', 'numeric', 'min:0'],
            'waste_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['process_id.required_without' => 'Choose a process, or fill in a material pattern for a material-only rule.', 'step_label.required_with' => 'The routing label is required when a process is chosen.',
            'material_role.required_with' => 'The BOM line name is required when a material pattern is filled in.', 'finishing_type_id.required_if' => 'Choose the finishing for a finishing insert.'],
            ['rule_scope' => 'Rule type', 'finishing_type_id' => 'Finishing', 'step_seq' => 'Seq', 'process_id' => 'Process', 'step_label' => 'Routing label', 'material_role' => 'BOM line name',
                'waste_pct' => 'Default waste', 'tolerance_pct' => 'Default tolerance', 'usage_rate' => 'Usage rate'])->validate();

        $blank = fn ($v) => ($v === null || $v === '') ? null : $v;
        $text = fn ($v) => $blank(trim((string) $v));
        $payload = [
            'rule_scope' => $d['rule_scope'], 'finishing_type_id' => $d['rule_scope'] === 'FINISHING' ? (int) $d['finishing_type_id'] : null, 'part_type' => $blank($d['part_type'] ?? null),
            'step_seq' => (int) $d['step_seq'], 'process_id' => ! empty($d['process_id']) ? (int) $d['process_id'] : null, 'step_label' => $text($d['step_label'] ?? ''),
            'merge_group' => ($g = $text($d['merge_group'] ?? '')) === null ? null : strtoupper($g), 'merge_label' => $text($d['merge_label'] ?? ''), 'machine_role' => $blank($d['machine_role'] ?? null),
            'condition_code' => $d['rule_scope'] === 'STANDARD' ? $blank($d['condition_code'] ?? null) : null, 'material_pattern' => $text($d['material_pattern'] ?? ''), 'material_rule' => $blank($d['material_rule'] ?? null),
            'material_role' => ($m = $text($d['material_role'] ?? '')) === null ? null : strtoupper($m), 'usage_basis' => $blank($d['usage_basis'] ?? null), 'usage_rate' => $blank($d['usage_rate'] ?? null),
            'waste_pct' => $blank($d['waste_pct'] ?? null), 'tolerance_pct' => $blank($d['tolerance_pct'] ?? null), 'note' => $text($d['note'] ?? ''),
            'auto_apply' => ! empty($in['auto_apply']) && $in['auto_apply'] !== 'false' && $in['auto_apply'] !== '0', 'updated_by' => $user->login,
        ];
        if ($r) {
            $r->update($payload);

            return $r;
        }

        return ProdBopRule::create($payload + ['activate' => true, 'created_by' => $user->login]);
    }

    public function setActive(ProdBopRule $r, bool $active, SecUser $user): void
    {
        $this->guard($user);
        $r->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /** Salin usulan takaran dan waste ke aturan (rate_source AUTO). Pesan hasil. */
    public function applySuggestion(ProdBopRule $r, SecUser $user): string
    {
        $this->guard($user);
        if ($r->rate_suggested === null && $r->waste_suggested === null) {
            throw ValidationException::withMessages(['document' => 'There is no suggestion for this rule yet.']);
        }
        $r->update(array_filter(['usage_rate' => $r->rate_suggested, 'waste_pct' => $r->waste_suggested], fn ($v) => $v !== null) + ['rate_source' => 'AUTO', 'updated_by' => $user->login]);

        return 'Suggestion applied to this rule.';
    }

    /** Hitung ulang usulan dari WO selesai 3 bulan terakhir (UsageRateEngine). Pesan hasil. */
    public function refreshSuggestions(SecUser $user): string
    {
        $this->guard($user);
        $r = $this->usage->refresh(now()->subMonths(3), true);

        return $r['note'] ?: "Suggestions refreshed from {$r['sample_total']} completed WOs; {$r['applied']} auto rules applied.";
    }

    /** @return array<int, string> proses aktif */
    public function processOptions(): array
    {
        return ProdProcess::where('activate', true)->orderBy('process_name')->pluck('process_name', 'id_process')->all();
    }

    /** @return array<int, string> jenis finishing aktif */
    public function finishingOptions(): array
    {
        return RefFinishingType::where('activate', true)->orderBy('finishing_name')->pluck('finishing_name', 'id_finishing_type')->all();
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(ProdBopRule $r): array
    {
        $rows = [['at' => $r->created_at?->toDateTimeString(), 'by' => $r->created_by, 'action' => 'Created', 'note' => '']];
        if ($r->updated_at && $r->updated_at != $r->created_at) {
            $rows[] = ['at' => $r->updated_at->toDateTimeString(), 'by' => $r->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
