<?php

namespace App\Services\Production;

use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProcessPrecedence;
use App\Models\Master\ProdRoutingStep;
use App\Models\Master\ProdRoutingTemplate;
use Illuminate\Support\Collection;

// Validator & penerap Routing Template. Dua kegunaan:
//   1. VALIDASI urutan langkah terhadap prod_process_precedence sebelum
//      template dipakai. BLOCK = urutan mustahil secara teknis (kupas sebelum
//      plong); WARN = tidak lazim tapi ada kasusnya.
//   2. TERAPKAN template jadi daftar langkah siap-pakai untuk BOP ProDev,
//      lengkap dengan usulan mesin dari matriks kapabilitas.
//
// Pola tetap "engine suggests, officer confirms" (CLAUDE.md §4): WARN tidak
// pernah menghentikan officer, dan usulan mesin bisa diganti.
class RoutingTemplateService
{
    public function __construct(private MachineCapabilityService $capabilities) {}

    // Periksa satu rangkaian langkah. $steps boleh koleksi ProdRoutingStep
    // ATAU array [['process_id'=>, 'step_seq'=>, 'branch_code'=>], ...] supaya
    // layar bisa memvalidasi draf yang belum disimpan.
    public function validate(Collection|array $steps): array
    {
        $rows = collect($steps)->map(fn ($s) => [
            'process_id' => (int) (is_array($s) ? $s['process_id'] : $s->process_id),
            'step_seq' => (int) (is_array($s) ? ($s['step_seq'] ?? 0) : $s->step_seq),
            'branch_code' => (is_array($s) ? ($s['branch_code'] ?? 'MAIN') : $s->branch_code) ?: 'MAIN',
        ])->values();

        if ($rows->isEmpty()) {
            return ['ok' => false, 'blocks' => [], 'warnings' => [],
                'notes' => [t('The routing is still empty — there is no step to check yet.')]];
        }

        $names = ProdProcess::whereIn('id_process', $rows->pluck('process_id')->unique())
            ->pluck('process_name', 'id_process');

        $blocks = [];
        $warnings = [];
        $notes = [];

        // Aturan diperiksa PER CABANG: langkah di cabang BODY tidak menjadi
        // pendahulu langkah di cabang LID -- keduanya jalan sendiri-sendiri.
        foreach ($rows->groupBy('branch_code') as $branch => $branchRows) {
            $ordered = $branchRows->sortBy('step_seq')->values();
            $positions = [];
            foreach ($ordered as $i => $row) {
                // Proses yang muncul 2x (mis. cetak 2 lintasan): pakai
                // kemunculan PERTAMA sebagai acuan urutan.
                $positions[$row['process_id']] ??= $i;
            }

            $rules = ProdProcessPrecedence::query()
                ->where('activate', true)
                ->whereIn('process_id', array_keys($positions))
                ->with(['process', 'relatedProcess'])
                ->get();

            foreach ($rules as $rule) {
                $selfPos = $positions[$rule->process_id] ?? null;
                $relPos = $positions[$rule->related_process_id] ?? null;
                $selfName = $names[$rule->process_id] ?? '?';
                $relName = $names[$rule->related_process_id] ?? ($rule->relatedProcess->process_name ?? '?');

                if ($selfPos === null) {
                    continue;
                }

                $violation = match ($rule->rule_type) {
                    // Pembanding tidak ada sama sekali -> hanya REQUIRES yang
                    // peduli. MUST_FOLLOW tidak dilanggar kalau prosesnya
                    // memang tidak dipakai di produk ini.
                    'MUST_FOLLOW' => $relPos !== null && $relPos > $selfPos,
                    'MUST_PRECEDE' => $relPos !== null && $relPos < $selfPos,
                    'FORBIDDEN_AFTER' => $relPos !== null && $relPos < $selfPos,
                    'REQUIRES' => $relPos === null,
                    default => false,
                };

                if (! $violation) {
                    continue;
                }

                $message = match ($rule->rule_type) {
                    'MUST_FOLLOW' => tf(':process is placed BEFORE :related, but it must come after it.', ['process' => $selfName, 'related' => $relName]),
                    'MUST_PRECEDE' => tf(':process is placed AFTER :related, but it must come before it.', ['process' => $selfName, 'related' => $relName]),
                    'FORBIDDEN_AFTER' => tf(':process must not run after :related.', ['process' => $selfName, 'related' => $relName]),
                    'REQUIRES' => tf(':process requires :related in the routing, but it is not there yet.', ['process' => $selfName, 'related' => $relName]),
                    default => tf(':process: the sequence rule is not met.', ['process' => $selfName]),
                };

                $entry = [
                    'branch' => $branch,
                    'process' => $selfName,
                    'related' => $relName,
                    'rule' => $rule->rule_type,
                    'message' => $message,
                    'reason' => $rule->reason,
                ];

                $rule->severity === 'BLOCK' ? $blocks[] = $entry : $warnings[] = $entry;
            }

            // Proses yang butuh mesin tapi tidak punya satu pun kapabilitas =
            // routing tidak akan bisa dijadwalkan, walau urutannya benar.
            foreach ($ordered as $row) {
                $process = ProdProcess::withCount(['capabilities' => fn ($q) => $q->where('activate', true)])
                    ->find($row['process_id']);

                if ($process && ! $process->is_manual && $process->capabilities_count === 0) {
                    $blocks[] = [
                        'branch' => $branch,
                        'process' => $process->process_name,
                        'related' => null,
                        'rule' => 'NO_MACHINE',
                        'message' => tf(':process has no machine to run it yet — this step cannot be scheduled.', ['process' => $process->process_name]),
                        'reason' => t('Pair a machine on the machines tab of Master Process.'),
                    ];
                }
            }

            // Tahap yang melompat mundur (FINISHING lalu PRESS lagi) sah untuk
            // sebagian produk, jadi cuma dicatat -- bukan warning.
            $stages = $ordered->map(fn ($r) => ProdProcess::find($r['process_id'])?->process_stage)->filter()->values();
            $order = array_keys(ProdProcess::STAGES);
            for ($i = 1; $i < $stages->count(); $i++) {
                $prev = array_search($stages[$i - 1], $order, true);
                $curr = array_search($stages[$i], $order, true);
                if ($prev !== false && $curr !== false && $curr < $prev) {
                    $notes[] = tf('Branch :branch: the stage goes back from :from to :to — valid for some products, make sure it is intended.',
                        ['branch' => $branch, 'from' => ProdProcess::STAGES[$stages[$i - 1]], 'to' => ProdProcess::STAGES[$stages[$i]]]);
                    break;
                }
            }
        }

        return [
            'ok' => $blocks === [],
            'blocks' => $blocks,
            'warnings' => $warnings,
            'notes' => $notes,
        ];
    }

    public function validateTemplate(int $templateId): array
    {
        $template = ProdRoutingTemplate::with('steps')->find($templateId);

        return $template
            ? $this->validate($template->steps)
            : ['ok' => false, 'blocks' => [], 'warnings' => [], 'notes' => [t('Template not found.')]];
    }

    // Ubah template jadi langkah siap-pakai untuk BOP: tiap langkah dilengkapi
    // usulan mesin dari matriks kapabilitas + parameter hasil resolusi 3 lapis.
    //
    // $constraints (sheet_length_mm, sheet_width_mm, gsm) diteruskan ke
    // pemilihan mesin supaya mesin yang tidak muat ukuran tidak diusulkan.
    public function expand(int $templateId, array $constraints = []): array
    {
        $template = ProdRoutingTemplate::with(['steps.process', 'steps.preferredMachine'])->find($templateId);

        if (! $template) {
            return [];
        }

        return $template->steps->map(function (ProdRoutingStep $step) use ($constraints) {
            $options = $this->capabilities->machinesForProcess($step->process_id, $constraints);
            $eligible = $options->where('eligible', true);

            // Mesin pilihan template dipakai HANYA kalau masih memenuhi syarat
            // untuk pekerjaan ini; kalau tidak, jatuh ke usulan teratas dan
            // alasannya dibawa supaya officer tahu kenapa berubah.
            $preferred = $step->preferred_machine_id
                ? $eligible->firstWhere(fn ($o) => $o['machine']->machine_id === $step->preferred_machine_id)
                : null;

            $fallbackReason = null;
            if ($step->preferred_machine_id && ! $preferred) {
                $blocked = $options->firstWhere(fn ($o) => $o['machine']->machine_id === $step->preferred_machine_id);
                $fallbackReason = $blocked
                    ? tf('The machine chosen by the template (:code) does not qualify: :reasons',
                        ['code' => $blocked['machine']->machine_code, 'reasons' => implode('; ', $blocked['reasons'])])
                    : t('The machine chosen by the template is no longer registered as able to do this process.');
            }

            $chosen = $preferred ?? $eligible->first();

            return [
                'step_seq' => $step->step_seq,
                'branch_code' => $step->branch_code,
                'process' => $step->process,
                'is_optional' => $step->is_optional,
                'qty_factor' => $step->qty_factor,
                'note' => $step->note,
                'suggested_machine' => $chosen['machine'] ?? null,
                'suggested_params' => $chosen['params'] ?? null,
                'alternatives' => $eligible->count(),
                'blocked_machines' => $options->where('eligible', false)->count(),
                'fallback_reason' => $fallbackReason,
                // Tidak ada satu pun mesin yang memenuhi syarat -> langkah ini
                // harus diselesaikan officer sebelum WO bisa dibuat.
                'unresolved' => $chosen === null && ! $step->process?->is_manual,
            ];
        })->all();
    }

    // Usulan template untuk sebuah produk. Kalau ada beberapa yang cocok,
    // yang is_default didahulukan -- tidak pernah memilih diam-diam kalau
    // tidak ada default (kembalikan semua, biar officer yang pilih).
    public function suggestFor(?string $prodTypeCode, ?int $categoryId): Collection
    {
        return ProdRoutingTemplate::query()
            ->where('activate', true)
            ->where(function ($q) use ($prodTypeCode, $categoryId) {
                $q->whereNull('prod_type_code')->whereNull('category_id');
                if ($prodTypeCode) {
                    $q->orWhere('prod_type_code', $prodTypeCode);
                }
                if ($categoryId) {
                    $q->orWhere('category_id', $categoryId);
                }
            })
            ->withCount('steps')
            ->orderByDesc('is_default')
            ->orderBy('template_name')
            ->get();
    }
}
