<?php

/*
 * REMARK
 * Tujuan       : Master Routing Template (urutan operasi standar per tipe produk) dari layar v2 prod-routing-form: simpan template beserta semua langkahnya dalam satu Save, aktif / nonaktif, hasil pemeriksaan urutan dan usulan mesin (hanya-baca), riwayat. Pemeriksa dan pemeta tetap RoutingTemplateService.
 * Sumber hulu  : Aturan dipindah dari halaman v1 pages::production.routing-template (diarsipkan di tmerp_unused/production_routing_template_v1_2026-10-19.blade.php): kode wajib unik maks 20, nama wajib maks 150, cabang huruf besar / angka / garis bawah, nomor urut kelipatan 10 supaya mudah disisipi, qty factor 0.001 sampai 99, catatan maks 255. Baru: nomor urut unik per cabang diperiksa sebelum simpan (v1 menukar lewat nomor sementara), mesin pilihan harus mampu mengerjakan proses (v1 hanya membatasi isi pilihan), langkah disimpan bersama Save (v1 menulis tiap klik).
 * Input        : Isian header (template_code, template_name, prod_type_code, substrate_hint, description, is_default), baris langkah (branch_code, step_seq, process_id, preferred_machine_id, is_optional, qty_factor, note), user.
 * Output       : tm2mst.prod_routing_template dan prod_routing_step berubah (langkah diganti seluruhnya dalam transaksi); ValidationException bila aturan dilanggar. Tidak menulis master mesin atau proses.
 * Dipakai oleh : routes/v2_production.php (v2.prod.routing.save), layar prod-routing-form, grid RoutingTemplateGrid, tes RoutingTemplateEntryServiceTest.
 * Status       : Aktif (Master T6b, 2026-10-19). Blok pemeriksaan urutan tidak menghentikan Save (sama seperti v1): hanya tampil sebagai Sequence invalid. Belum ada template di data mana pun dan ProDev belum membaca template.
 */

namespace App\Services\Production;

use App\Models\Master\ProdProcess;
use App\Models\Master\ProdRoutingStep;
use App\Models\Master\ProdRoutingTemplate;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\ProductionRef;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RoutingTemplateEntryService
{
    public function __construct(private RoutingTemplateService $checker, private MachineCapabilityService $capabilities) {}

    /**
     * @param  array<int, array<string, mixed>>  $steps  baris form langkah
     */
    public function save(?ProdRoutingTemplate $t, array $in, array $steps, SecUser $user): ProdRoutingTemplate
    {
        $in['template_code'] = strtoupper(trim((string) ($in['template_code'] ?? '')));
        $d = Validator::make($in, [
            'template_code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_\-]+$/', 'unique:tm2mst.prod_routing_template,template_code'.($t ? ','.$t->template_id.',template_id' : '')],
            'template_name' => ['required', 'string', 'max:150'],
            'prod_type_code' => ['nullable', 'string', 'max:20'],
            'substrate_hint' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ], ['template_code.regex' => 'Use letters, digits, underscore or hyphen only.'],
            ['template_code' => 'Template code', 'template_name' => 'Template name', 'prod_type_code' => 'Product type', 'substrate_hint' => 'Material reference'])->validate();
        $rows = $this->cleanSteps($t, $steps);
        $none = fn ($v) => ($v ?? '') === '' ? null : $v;

        return DB::connection('tm2mst')->transaction(function () use ($t, $d, $in, $rows, $none, $user) {
            $payload = ['template_code' => $d['template_code'], 'template_name' => trim($d['template_name']), 'prod_type_code' => $none($d['prod_type_code'] ?? null), 'substrate_hint' => $none($d['substrate_hint'] ?? null),
                'description' => $none($d['description'] ?? null), 'is_default' => ! empty($in['is_default']), 'updated_by' => $user->login];
            if ($t) {
                $t->update($payload);
                $t->steps()->delete();
            } else {
                $t = ProdRoutingTemplate::create($payload + ['activate' => true, 'created_by' => $user->login]);
            }
            foreach ($rows as $r) {
                ProdRoutingStep::create($r + ['template_id' => $t->template_id, 'created_by' => $user->login, 'updated_by' => $user->login]);
            }

            return $t;
        });
    }

    /**
     * Baris langkah bersih. Baris kosong dibuang; wajib proses; cabang default MAIN; nomor urut kosong = kelipatan 10 berikutnya di cabang; unik per cabang; mesin pilihan harus mampu (hanya dicek bila pasangan proses + mesin baru).
     *
     * @return list<array<string, mixed>>
     */
    private function cleanSteps(?ProdRoutingTemplate $t, array $steps): array
    {
        $known = $t ? $t->steps()->get()->mapWithKeys(fn ($s) => [$s->process_id.':'.$s->preferred_machine_id => true])->all() : [];
        $out = [];
        $errors = [];
        $next = [];
        $seen = [];
        $rows = array_values($steps);
        foreach ($rows as $i => $r) {
            $n = $i + 1;
            $processId = (int) ($r['process_id'] ?? 0);
            $seq = trim((string) ($r['step_seq'] ?? ''));
            if (! $processId && $seq === '' && trim((string) ($r['preferred_machine_id'] ?? '')) === '') {
                continue;
            }
            $process = $processId ? ProdProcess::find($processId) : null;
            if (! $process) {
                $errors['steps'] = "Step {$n}: choose a process.";

                continue;
            }
            $branch = strtoupper(trim((string) ($r['branch_code'] ?? ''))) ?: 'MAIN';
            if (! preg_match('/^[A-Z0-9_]{1,20}$/', $branch)) {
                $errors['steps'] = "Step {$n}: the branch code uses letters, digits and underscore only (up to 20).";

                continue;
            }
            $maxInBranch = collect($rows)->filter(fn ($x) => (strtoupper(trim((string) ($x['branch_code'] ?? ''))) ?: 'MAIN') === $branch)->map(fn ($x) => (int) ($x['step_seq'] ?? 0))->max() ?: 0;
            $next[$branch] ??= $maxInBranch;
            if ($seq === '') {
                $next[$branch] += 10;
                $seqNo = $next[$branch];
            } elseif (! ctype_digit($seq) || (int) $seq < 1 || (int) $seq > 65535) {
                $errors['steps'] = "Step {$n}: the sequence number must be a whole number from 1 to 65535.";

                continue;
            } else {
                $seqNo = (int) $seq;
            }
            if (isset($seen[$branch.':'.$seqNo])) {
                $errors['steps'] = "Step {$n}: the sequence number {$seqNo} is already used by another step in branch {$branch}.";

                continue;
            }
            $seen[$branch.':'.$seqNo] = true;
            $machineId = (int) ($r['preferred_machine_id'] ?? 0) ?: null;
            if ($machineId && ! isset($known[$processId.':'.$machineId])) {
                $eligible = $this->capabilities->machinesForProcess($processId)->first(fn ($o) => (int) $o['machine']->machine_id === $machineId && $o['eligible']);
                if (! $eligible) {
                    $errors['steps'] = "Step {$n}: the chosen machine is not registered as able to do {$process->process_name}.";

                    continue;
                }
            }
            $qty = Locale::input($r['qty_factor'] ?? '');
            if ($qty !== null && ((float) $qty < 0.001 || (float) $qty > 99)) {
                $errors['steps'] = "Step {$n}: the quantity factor is between 0.001 and 99.";

                continue;
            }
            $note = trim((string) ($r['note'] ?? ''));
            if (mb_strlen($note) > 255) {
                $errors['steps'] = "Step {$n}: the note is at most 255 characters.";

                continue;
            }
            $out[] = ['step_seq' => $seqNo, 'process_id' => $processId, 'preferred_machine_id' => $machineId, 'is_optional' => filter_var($r['is_optional'] ?? false, FILTER_VALIDATE_BOOLEAN), 'branch_code' => $branch,
                'qty_factor' => $qty === null ? null : (float) $qty, 'note' => $note !== '' ? $note : null];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    public function setActive(ProdRoutingTemplate $t, bool $active, SecUser $user): void
    {
        $t->update(['activate' => $active, 'updated_by' => $user->login]);
    }

    /** Baris x-dl::lines dari template tersimpan, atau dari isian form (old input). */
    public function rows(?ProdRoutingTemplate $t, ?array $input = null): array
    {
        $raw = $input ?? ($t ? $t->steps()->get()->map(fn ($s) => ['branch_code' => $s->branch_code, 'step_seq' => $s->step_seq, 'process_id' => $s->process_id, 'preferred_machine_id' => $s->preferred_machine_id,
            'is_optional' => $s->is_optional, 'qty_factor' => $s->qty_factor === null ? '' : (float) $s->qty_factor, 'note' => $s->note])->all() : []);

        return array_map(function ($r) {
            $p = ! empty($r['process_id']) ? ProductionRef::processFind($r['process_id']) : null;
            $m = ! empty($r['preferred_machine_id']) ? ProductionRef::machineFind($r['preferred_machine_id']) : null;

            return ['branch_code' => $r['branch_code'] ?? 'MAIN', 'step_seq' => $r['step_seq'] ?? '', 'process_id' => $r['process_id'] ?? '', 'process_id_label' => $p['label'] ?? '',
                'preferred_machine_id' => $r['preferred_machine_id'] ?? '', 'preferred_machine_id_label' => $m['label'] ?? '', 'is_optional' => filter_var($r['is_optional'] ?? false, FILTER_VALIDATE_BOOLEAN), 'qty_factor' => $r['qty_factor'] ?? '', 'note' => (string) ($r['note'] ?? '')];
        }, $raw);
    }

    /** Hasil pemeriksaan urutan dan usulan mesin untuk template tersimpan. @return array{check: array, expansion: array} */
    public function result(ProdRoutingTemplate $t): array
    {
        return ['check' => $this->checker->validateTemplate($t->template_id), 'expansion' => $this->checker->expand($t->template_id)];
    }

    /** Riwayat tab History: terbaru di atas. */
    public function history(ProdRoutingTemplate $t): array
    {
        $rows = [['at' => $t->created_at?->toDateTimeString(), 'by' => $t->created_by, 'action' => 'Created', 'note' => '']];
        if ($t->updated_at && $t->updated_at != $t->created_at) {
            $rows[] = ['at' => $t->updated_at->toDateTimeString(), 'by' => $t->updated_by, 'action' => 'Last modified', 'note' => ''];
        }

        return array_reverse($rows);
    }
}
