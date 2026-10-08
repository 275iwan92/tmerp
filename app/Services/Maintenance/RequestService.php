<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntRequest;
use App\Support\V2\Data\Maintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Maintenance Request: laporan masalah dari siapa saja. Kepala maintenance menilai (Accept membuat BMO Draft, Reject butuh alasan).
// Darurat (P1) tidak menunggu: request langsung diterima dan BMO langsung Confirmed supaya masuk daftar kerja teknisi saat itu juga.
final class RequestService
{
    /** Jenis yang dibuka lewat layar Request; Improvement lewat Improvement Proposal. */
    public const OPEN_KINDS = ['BREAKDOWN' => 'Breakdown', 'CORRECTIVE' => 'Corrective', 'SAFETY' => 'Safety'];

    // Jenis request -> jenis order.
    private const ORDER_TYPE = ['BREAKDOWN' => 'BREAKDOWN', 'CORRECTIVE' => 'CORRECTIVE', 'SAFETY' => 'CORRECTIVE', 'IMPROVEMENT' => 'IMPROVEMENT'];

    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    /** Usulan prioritas: keselamatan = P1; mesin berhenti pada aset kritis (A, B) = P1, aset lain berhenti = P2; sisanya P3 (P2 untuk aset kritis). */
    public static function suggestPriority(MntAsset $asset, string $kind, bool $stopped): string
    {
        $critical = in_array(Maintenance::criticalityOf($asset), ['A', 'B'], true);

        return match (true) {
            $kind === 'SAFETY' => 'P1',
            $stopped => $critical ? 'P1' : 'P2',
            default => $critical ? 'P2' : 'P3',
        };
    }

    /** @return array{0: MntRequest, 1: ?MntOrder} request dan BMO otomatis bila P1 */
    public function open(array $d): array
    {
        $asset = MntAsset::findOrFail((int) $d['asset_id']);
        $kind = (string) $d['request_kind'];
        $stopped = ! empty($d['asset_stopped']);
        $this->guard(isset(self::OPEN_KINDS[$kind]), t('Choose a request kind.'));
        $this->guard(trim((string) ($d['symptom'] ?? '')) !== '', t('Describe the problem (symptom).'));
        $priority = ($d['priority'] ?? '') ?: self::suggestPriority($asset, $kind, $stopped);

        return DB::connection('tm2mnt')->transaction(function () use ($asset, $kind, $stopped, $priority, $d) {
            $request = MntRequest::create(['doc_no' => DocNumber::next('MR', MntRequest::class), 'request_date' => now(), 'asset_id' => $asset->id_asset, 'request_kind' => $kind,
                'priority' => $priority, 'asset_stopped' => $stopped, 'stopped_at' => $stopped ? ($d['stopped_at'] ?? now()) : null, 'symptom' => trim((string) $d['symptom']),
                'source_type' => 'MANUAL', 'breakdown_event_id' => $d['breakdown_event_id'] ?? null, 'status' => 'OPEN', 'created_by' => $this->login, 'updated_by' => $this->login]);
            MntLog::add('REQUEST', $request->id_request, 'Created', $priority);
            $order = $priority === 'P1' ? $this->accept($request, true) : null;

            return [$request->refresh(), $order];
        });
    }

    /**
     * Laporan mesin rusak dari lantai produksi (WO entry) menjadi Maintenance Request. Mesin tanpa aset Maintenance dilewati; satu laporan satu request.
     * Gagal di sini tidak boleh menghentikan laporan produksi, jadi pemanggil menelan galatnya dan mencatatnya.
     *
     * @return array{0: MntRequest, 1: ?MntOrder}|null
     */
    public function fromBreakdown(int $eventId, int $machineId, ?string $note = null): ?array
    {
        $asset = MntAsset::where('machine_id', $machineId)->where('activate', true)->first();
        if (! $asset || MntRequest::where('breakdown_event_id', $eventId)->exists()) {
            return null;
        }

        return $this->open(['asset_id' => $asset->id_asset, 'request_kind' => 'BREAKDOWN', 'asset_stopped' => true, 'breakdown_event_id' => $eventId,
            'symptom' => trim('Machine reported broken on the shop floor. '.($note ?? ''))]);
    }

    /** Accept: BMO Draft dari request (atau Confirmed bila $auto, untuk P1). */
    public function accept(MntRequest $request, bool $auto = false): MntOrder
    {
        $this->guard($request->status === 'OPEN', t('Only an Open request can be accepted.'));
        $orders = new OrderService($this->login);
        $order = $orders->create(['order_type' => self::ORDER_TYPE[$request->request_kind] ?? 'CORRECTIVE', 'asset_id' => $request->asset_id, 'priority' => $request->priority,
            'description' => mb_substr($request->symptom, 0, 255), 'needs_stop' => $request->asset_stopped, 'planned_start' => now()]);
        $order->update(['source' => 'REQUEST', 'request_id' => $request->id_request]);
        $request->update(['status' => 'ACCEPTED', 'order_id' => $order->id_order, 'updated_by' => $this->login]);
        MntLog::add('REQUEST', $request->id_request, $auto ? 'Auto accepted (P1)' : 'Accepted', 'Order #'.$order->id_order);
        if ($auto) {
            $orders->confirm($order);
        }

        return $order->refresh();
    }

    public function reject(MntRequest $request, string $reason): void
    {
        $this->guard($request->status === 'OPEN', t('Only an Open request can be rejected.'));
        $this->guard(trim($reason) !== '', t('Enter a reason to Reject.'));
        $request->update(['status' => 'REJECTED', 'reject_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $this->login]);
        MntLog::add('REQUEST', $request->id_request, 'Rejected', $reason);
    }

    private function guard(bool $ok, string $msg): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['maintenance' => $msg]);
        }
    }
}
