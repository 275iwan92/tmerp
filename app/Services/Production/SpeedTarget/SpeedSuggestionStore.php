<?php

namespace App\Services\Production\SpeedTarget;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdProcessMachine;
use App\Models\Master\ProdSpeedSuggestion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Menyimpan hasil engine sebagai usulan PENDING dan menjalankan keputusan PPIC. Hanya accept() yang menulis ke master
// (prod_machine.speed_target_per_hour / prod_process_machine.speed_per_hour) — engine tidak pernah menimpa diam-diam.
final class SpeedSuggestionStore
{
    public function __construct(private readonly SpeedTargetEngine $engine) {}

    // Jalankan engine untuk semua mesin (atau sebagian) lalu sinkronkan usulan. Dipanggil jadwal harian & tombol layar.
    public function refresh(?array $machineIds = null, string $user = 'system'): array
    {
        $contexts = (new SpeedDataRepository($this->engine->settings()))->contexts();
        $results = $this->engine->run($contexts);
        $stats = ['created' => 0, 'kept' => 0, 'superseded' => 0, 'machines' => 0];

        foreach ($contexts as $c) {
            if ($machineIds !== null && ! in_array($c->machineId, $machineIds, true)) {
                continue;
            }
            $stats['machines']++;
            $r = $results[$c->machineId];
            $this->sync($c->machineId, null, null, $c->currentTarget, $r['machine'], $user, $stats);
            foreach ($c->capabilities as $cap) {
                $this->sync($c->machineId, $cap['capability_id'], $cap['process_name'], $cap['current'], $r['capabilities'][$cap['capability_id']] ?? null, $user, $stats);
            }
        }

        return $stats;
    }

    private function sync(int $machineId, ?int $capId, ?string $process, ?float $current, ?SpeedSuggestion $s, string $user, array &$stats): void
    {
        $open = ProdSpeedSuggestion::query()->where('machine_id', $machineId)->where('capability_id', $capId)->where('status', 'PENDING')->get();
        $wanted = $s && $this->engine->shouldPropose($current, $s);

        $same = $wanted ? $open->first(fn ($o) => $o->source_code === $s->source && abs($o->suggested_speed - $s->speed) < 0.5) : null;
        if ($same) {
            $same->update(['computed_at' => now(), 'current_value' => $current, 'confidence' => $s->confidence, 'sample_size' => $s->sampleSize, 'basis' => mb_substr($s->basis, 0, 500)]);
            $stats['kept']++;

            return;
        }
        foreach ($open as $o) {
            $o->update(['status' => 'SUPERSEDED', 'updated_by' => $user]);
            $stats['superseded']++;
        }
        if (! $wanted) {
            return;
        }
        // Angka yang sudah pernah ditolak PPIC dari sumber yang sama tidak disodorkan lagi.
        $rejected = ProdSpeedSuggestion::query()->where('machine_id', $machineId)->where('capability_id', $capId)
            ->where('status', 'REJECTED')->where('source_code', $s->source)->whereBetween('suggested_speed', [$s->speed - 0.5, $s->speed + 0.5])->exists();
        if ($rejected) {
            return;
        }
        ProdSpeedSuggestion::create([
            'machine_id' => $machineId, 'capability_id' => $capId, 'process_name' => $process,
            'suggested_speed' => $s->speed, 'uom' => $s->uom, 'source_code' => $s->source, 'confidence' => $s->confidence,
            'sample_size' => $s->sampleSize, 'basis' => mb_substr($s->basis, 0, 500), 'current_value' => $current,
            'status' => 'PENDING', 'computed_at' => now(), 'created_by' => $user,
        ]);
        $stats['created']++;
    }

    // Terima (value null) atau Ubah (value = angka PPIC). Nilai ditulis ke master + jejak di notes mesin (English, aturan DB).
    public function accept(ProdSpeedSuggestion $s, ?float $value, string $user, ?string $note = null): void
    {
        if ($s->status !== 'PENDING') {
            throw new InvalidArgumentException('Suggestion is no longer pending.');
        }
        $value ??= $s->suggested_speed;
        if ($value <= 0) {
            throw new InvalidArgumentException('Speed must be greater than zero.');
        }

        DB::connection('tm2mst')->transaction(function () use ($s, $value, $user, $note) {
            $line = sprintf('[SPEED %s] %s %s/h set by %s (%s; engine suggested %s, %s confidence)',
                now()->format('Y-m-d'), $s->capability_id ? "pair {$s->process_name}" : 'target', rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'),
                $user, $value == $s->suggested_speed ? 'accepted' : 'changed', rtrim(rtrim(number_format($s->suggested_speed, 2, '.', ''), '0'), '.'), $s->confidence);

            if ($s->capability_id) {
                $cap = ProdProcessMachine::findOrFail($s->capability_id);
                $cap->update(['speed_per_hour' => $value, 'note' => mb_substr(trim($cap->note.' '.$line), 0, 255), 'updated_by' => $user]);
            } else {
                $m = ProdMachine::findOrFail($s->machine_id);
                $m->update(['speed_target_per_hour' => (int) round($value), 'notes' => trim($m->notes."\n".$line), 'updated_by' => $user]);
            }
            $s->update(['status' => 'ACCEPTED', 'decided_value' => $value, 'decided_by' => $user, 'decided_at' => now(), 'decision_note' => $note, 'updated_by' => $user]);
        });
    }

    public function reject(ProdSpeedSuggestion $s, string $user, ?string $note = null): void
    {
        if ($s->status !== 'PENDING') {
            throw new InvalidArgumentException('Suggestion is no longer pending.');
        }
        $s->update(['status' => 'REJECTED', 'decided_by' => $user, 'decided_at' => now(), 'decision_note' => $note, 'updated_by' => $user]);
    }
}
