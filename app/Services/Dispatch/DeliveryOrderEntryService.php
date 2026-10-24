<?php

/*
 * REMARK
 * Tujuan       : Aturan entri Delivery Order (dipindah apa adanya dari komponen v1 pages::dispatch.do.entry): ubah header DRAFT, alamat kirim, tarik baris dari jadwal kirim CO, ubah qty / harga, hapus baris, Confirm, Ship, Cancel. Semua tulis lewat DispatchService (satu-satunya jalur sah); service ini hanya memuat ulang DO dari DB dan menerjemahkan hasilnya jadi pesan.
 * Sumber hulu  : DoDelivery, DoLine, DispatchService, v_co_delivery_outstanding (lewat DispatchService).
 * Input        : id DO (+ id baris / nilai field / id jadwal kirim / alasan batal).
 * Output       : do_delivery, do_line, co_order_delivery (lewat DispatchService); nilai kembali = pesan error ('' = OK).
 * Dipakai oleh : App\Support\V2\Data\DeliveryOrder, tests/Feature/DeliveryOrderEntryTest.
 * Status       : Aktif (T10 tahap 7c, 2026-10-22).
 */

namespace App\Services\Dispatch;

use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoLine;
use Illuminate\Support\Facades\Auth;

final class DeliveryOrderEntryService
{
    public const HEADER_FIELDS = ['do_date', 'scheduled_ship_date', 'ship_address_detail', 'pickup_name', 'pickup_vehicle', 'pickup_plate'];

    public static function onAddressPicked(int $doId, string $fieldName, ?int $areaId, ?string $areaLevel, ?string $label, ?string $postalCode): void
    {
        $do = self::load($doId);

        if ($fieldName !== 'do-ship-address' || $do->status !== 'DRAFT') {
            return;
        }

        $do->update([
            'ship_area_id' => $areaId,
            'ship_area_level' => $areaLevel,
            'ship_address_label' => $label,
            'ship_postal_code' => $postalCode,
            'updated_by' => self::login(),
        ]);
    }

    public static function updateHeader(int $doId, string $field, mixed $value): void
    {
        $do = self::load($doId);

        if ($do->status !== 'DRAFT' || ! in_array($field, self::HEADER_FIELDS, true)) {
            return;
        }

        $value = is_string($value) ? trim($value) : $value;
        // Nomor polisi selalu huruf besar dengan satu spasi antar bagian (N 12345 SAMPLE).
        if ($field === 'pickup_plate' && is_string($value)) {
            $value = mb_strtoupper((string) preg_replace('/\s+/', ' ', $value));
        }

        $do->update([$field => $value === '' ? null : $value, 'updated_by' => self::login()]);
    }

    // Kembalian = pesan error DispatchService ('' = OK atau tidak ada yang dipilih).
    public static function pullSelected(int $doId, array $checkedDeliveryIds): string
    {
        if (empty($checkedDeliveryIds)) {
            return '';
        }

        $result = app(DispatchService::class)->pullLines(self::load($doId), $checkedDeliveryIds, self::login());

        return $result['message'] ?? '';
    }

    public static function updateLineQty(int $doId, int $lineId, mixed $value): string
    {
        $result = app(DispatchService::class)->updateLineQty(self::line($doId, $lineId), (float) $value, self::login());

        return $result['ok'] ? '' : $result['message'];
    }

    public static function updateLinePrice(int $doId, int $lineId, mixed $value): string
    {
        // Harga jual hanya boleh diubah yang berhak (rulebook A.9); yang tidak berhak diabaikan tanpa pesan (kolom harga tidak tampil di layar).
        if (! Auth::user()->canViewSalesPrice()) {
            return '';
        }

        $result = app(DispatchService::class)->updateLinePrice(self::line($doId, $lineId), (float) $value, self::login());

        return $result['ok'] ? '' : $result['message'];
    }

    public static function removeLine(int $doId, int $lineId): void
    {
        app(DispatchService::class)->removeLine(self::line($doId, $lineId));
    }

    public static function confirmDo(int $doId): string
    {
        $result = app(DispatchService::class)->confirm(self::load($doId), self::login());

        return $result['ok'] ? '' : $result['message'];
    }

    // Hasil DispatchService sengaja tidak dibaca (sama dengan v1): penolakan terlihat dari status DO yang tidak berubah.
    public static function markShipped(int $doId): void
    {
        app(DispatchService::class)->markShipped(self::load($doId), self::login());
    }

    public static function cancelDo(int $doId, string $reason): string
    {
        $result = app(DispatchService::class)->cancel(self::load($doId), $reason, self::login());

        return $result['ok'] ? '' : $result['message'];
    }

    private static function load(int $doId): DoDelivery
    {
        return DoDelivery::findOrFail($doId);
    }

    private static function line(int $doId, int $lineId): DoLine
    {
        return DoLine::where('delivery_id', $doId)->findOrFail($lineId);
    }

    private static function login(): string
    {
        return Auth::user()->login;
    }
}
