<?php

namespace App\Services\Production\SpeedTarget;

// Hasil riwayat mesin-mesin lain di pabrik (lintasan pertama engine) — bahan sumber SAME_MODEL & PEER_RATIO.
final class SpeedPeers
{
    /**
     * @param  array<int, MachineSpeedContext>  $contexts
     * @param  array<int, SpeedSuggestion>  $history  hanya usulan isMachineHistory()
     */
    public function __construct(public readonly array $contexts = [], public readonly array $history = []) {}

    /** @return list<array{context: MachineSpeedContext, suggestion: SpeedSuggestion}> */
    public function withHistory(MachineSpeedContext $self, callable $filter): array
    {
        $out = [];
        foreach ($this->history as $id => $s) {
            $ctx = $this->contexts[$id] ?? null;
            if ($ctx && $id !== $self->machineId && $filter($ctx, $s)) {
                $out[] = ['context' => $ctx, 'suggestion' => $s];
            }
        }

        return $out;
    }
}
