<?php

namespace App\Support\V2\Data\Concerns;

use App\Models\Master\FlowLog;
use App\Services\Flow\FlowEngineService;
use Illuminate\Support\Facades\Auth;

// Bersama untuk dokumen ber-approval flow engine (PR, PO, ...): antrian approver, log History, konversi nilai form.
trait FlowDocument
{
    public static function pendingFor(string $module, int $docId): ?object
    {
        return app(FlowEngineService::class)->pendingForUser($module, Auth::id())->firstWhere('doc_id', $docId);
    }

    // flow_log -> tab History (terbaru di atas).
    public static function flowLog(string $module, int $docId): array
    {
        return FlowLog::where('module_code', $module)->where('doc_id', $docId)->orderByDesc('acted_at')->get()
            ->map(fn ($l) => ['at' => $l->acted_at?->toDateTimeString(), 'by' => $l->created_by, 'action' => ucfirst(strtolower((string) $l->action)), 'note' => $l->notes])->all();
    }

    private static function str(mixed $v): string
    {
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v);
    }

    private static function num(mixed $v): string
    {
        $v = trim((string) $v);
        $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : (substr_count($v, '.') > 1 ? str_replace('.', '', $v) : $v);

        return $v === '' ? '0' : $v;
    }

    private static function changed(string $v, mixed $cur): bool
    {
        $cur = self::str($cur);

        return $v !== $cur && ! (is_numeric($v) && is_numeric($cur) && (float) $v === (float) $cur);
    }
}
