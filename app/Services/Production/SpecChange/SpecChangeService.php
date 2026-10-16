<?php

/*
 * REMARK
 * Tujuan       : Substitusi bahan pada baris BOM Work Order (kelas 2): diminta PPIC, dampak biayanya dihitung dan diberitahukan ke GM dan Direksi, disetujui lewat OTP OPM / GM / Director, lalu diterapkan ke baris WO; bahan pengganti boleh disimpan sebagai cadangan (Jo 2026-10-16).
 * Sumber hulu  : tm2prd.prd_wo_material (is_substituted, substitute_material_id dibaca GateService), mat_raw (harga rata-rata / terakhir), CO delivery + item (nilai order), SpecLock, SpecOtpService.
 * Input        : id baris BOM, bahan pengganti, alasan, pilihan simpan cadangan, login pemohon; kode OTP.
 * Output       : spc_change_request (PENDING lalu APPLIED / REJECTED); baris WO bertanda pengganti; prod_material_alt bila cadangan; notifikasi dampak biaya ke grup GM dan DIREKSI.
 * Dipakai oleh : Form WO (kartu substitusi), antrean persetujuan.
 * Status       : Aktif. Dampak biaya = selisih harga bahan x qty yang sama (ukuran lembar berbeda belum dimodelkan; ditandai di basis hitung).
 */

namespace App\Services\Production\SpecChange;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdMaterialAlt;
use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\SpcChangeRequest;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sys\SecUser;
use App\Services\Inventory\QcNotifier;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SpecChangeService
{
    public static function requestSubstitution(int $woMaterialId, int $substituteId, string $reason, bool $saveBackup, string $login): SpcChangeRequest
    {
        $line = PrdWoMaterial::query()->findOrFail($woMaterialId);
        $substitute = MatRaw::query()->findOrFail($substituteId);
        if (SpecLock::locked((int) $line->wo_id)) {
            throw new DomainException(t('Production has started on this Work Order: material can no longer be changed.'));
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new DomainException(t('Write the reason (at least 5 characters).'));
        }
        if ((int) $line->material_id === $substituteId) {
            throw new DomainException(t('The substitute is the same material.'));
        }
        if (SpcChangeRequest::query()->where('wo_material_id', $woMaterialId)->where('status', 'PENDING')->exists()) {
            throw new DomainException(t('A substitution for this line is already waiting for approval.'));
        }

        $original = MatRaw::query()->find($line->material_id);
        $request = SpcChangeRequest::query()->create([
            'change_class' => SpcChangeRequest::CLASS_MATERIAL, 'wo_id' => $line->wo_id, 'wo_material_id' => $woMaterialId,
            'product_id' => PrdWo::query()->whereKey($line->wo_id)->value('product_id'), 'reason' => trim($reason), 'save_backup' => $saveBackup,
            'payload' => ['original' => ['material_id' => $line->material_id, 'name' => $line->material_name], 'substitute' => ['material_id' => $substituteId, 'name' => $substitute->material_name]],
            'cost_impact' => self::costImpact($line, $original, $substitute), 'status' => 'PENDING', 'requested_by' => $login, 'requested_at' => now(),
        ]);
        // Pemberitahuan ke GM dan Direksi berjalan sesudah respons dikirim (latar belakang), tidak menahan layar PPIC.
        app()->terminating(fn () => self::notifyImpact($request));

        return $request;
    }

    // Selisih biaya bahan bila dipakai pengganti: (harga pengganti - harga asli) x qty Required baris itu; nilai order dari baris CO terkait.
    public static function costImpact(PrdWoMaterial $line, ?MatRaw $original, MatRaw $substitute): array
    {
        $unit = fn (?MatRaw $m) => $m ? (float) ($m->avg_unitprice ?: $m->last_unitprice ?: 0) : 0.0;
        $qty = (float) $line->qty_required;
        $delta = round($qty * ($unit($substitute) - $unit($original)), 2);

        $wo = PrdWo::query()->find($line->wo_id);
        $delivery = $wo?->source_delivery_id ? CoOrderDelivery::query()->find($wo->source_delivery_id) : null;
        $item = $delivery ? CoOrderItem::query()->find($delivery->item_id) : null;
        $orderValue = $item ? round((float) $delivery->ship_qty * (float) $item->unit_price * (1 - (float) $item->discount_pct / 100), 2) : null;

        return ['qty' => $qty, 'original_unit_cost' => $unit($original), 'substitute_unit_cost' => $unit($substitute), 'delta' => $delta, 'order_value' => $orderValue,
            'delta_pct_of_order' => $orderValue ? round($delta / $orderValue * 100, 2) : null, 'basis' => 'same quantity; a different sheet size is not modelled'];
    }

    // GM dan Direksi diberi tahu bila bahan pengganti mengubah biaya (laba kotor dan laba bersih turun / naik sebesar selisih sebelum pajak).
    public static function notifyImpact(SpcChangeRequest $request): void
    {
        $i = (array) $request->cost_impact;
        if (! ($i['delta'] ?? 0)) {
            return;
        }
        $wo = PrdWo::query()->whereKey($request->wo_id)->value('wo_no');
        $sign = $i['delta'] > 0 ? '+' : '';
        QcNotifier::toGroups(['GM', 'DIREKSI'], tf('Material substitution on WO :wo', ['wo' => $wo]),
            tf(':orig to :sub: cost :sign:delta (:pct of the order value). It lowers (or raises) gross and net profit by the same amount before tax.', [
                'orig' => $request->payload['original']['name'], 'sub' => $request->payload['substitute']['name'], 'sign' => $sign, 'delta' => number_format((float) $i['delta'], 0, '.', ','),
                'pct' => ($i['delta_pct_of_order'] ?? null) !== null ? $i['delta_pct_of_order'].'%' : t('order value unknown'),
            ]), 'warning');
    }

    // Kode OTP dari penyetuju menyetujui sekaligus menerapkan: kode ditukar dulu (percobaan salah tetap tercatat), lalu penerapan satu transaksi.
    public static function approveWithOtp(SpcChangeRequest $request, string $code, SecUser $by): SpcChangeRequest
    {
        $otp = SpecOtpService::redeem($request, $code, $by);
        if (SpecLock::locked((int) $request->wo_id)) {
            $request->update(['status' => 'REJECTED', 'decided_by' => $otp->issued_by, 'decided_at' => now(), 'decision_note' => 'Production started before approval']);
            throw new DomainException(t('Production started before this was approved: the request was closed.'));
        }

        DB::connection('tm2prd')->transaction(function () use ($request, $otp) {
            // substitute_reason = kolom enum lama (DEAD_STOCK / SLOW_MOVING / OTHER); alasan lengkap ada di spc_change_request.reason.
            $sub = (int) $request->payload['substitute']['material_id'];
            PrdWoMaterial::query()->whereKey($request->wo_material_id)->update(['is_substituted' => true, 'substitute_material_id' => $sub,
                'substitute_reason' => 'OTHER', 'substitute_approved_by' => $otp->issued_by, 'substitute_approved_at' => now()]);
            $request->update(['status' => 'APPLIED', 'decided_by' => $otp->issued_by, 'decided_at' => now()]);
        });
        if ($request->save_backup) {
            ProdMaterialAlt::query()->updateOrCreate(['material_id' => $request->payload['original']['material_id'], 'alt_material_id' => $request->payload['substitute']['material_id']],
                ['note' => $request->reason, 'approved_by' => $otp->issued_by, 'approved_at' => now(), 'source_wo_id' => $request->wo_id, 'activate' => true, 'created_by' => $request->requested_by]);
        }

        return $request->refresh();
    }

    public static function reject(SpcChangeRequest $request, SecUser $approver, string $note): void
    {
        if (! SpecOtpService::canApprove($approver)) {
            throw new DomainException(t('Only an Operational Manager, General Manager or Director can reject.'));
        }
        if (mb_strlen(trim($note)) < 5) {
            throw new DomainException(t('Write the reason (at least 5 characters).'));
        }
        $request->update(['status' => 'REJECTED', 'decided_by' => $approver->login, 'decided_at' => now(), 'decision_note' => trim($note)]);
    }

    public static function cancel(SpcChangeRequest $request, string $login): void
    {
        if ($request->status === 'PENDING' && $request->requested_by === $login) {
            $request->update(['status' => 'CANCELLED', 'decided_by' => $login, 'decided_at' => now()]);
        }
    }
}
