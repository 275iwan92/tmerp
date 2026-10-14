<?php

/*
 * REMARK
 * Tujuan       : Pengaman jadwal (Jo 2026-10-14): tidak boleh ada proses BOP yang tidak terjadwal; daftar proses tak terjadwal beserta alasannya.
 * Sumber hulu  : Ppic::operations (Draft atau jadwal berjalan), gerbang bahan / tooling / artwork dari GateService, kandidat mesin dari WoPlanningEnricher.
 * Input        : id WO opsional (kosong = semua WO aktif).
 * Output       : list {wo_id, wo_no, process, reason}; kosong = semua proses terjadwal. line() = teks satu baris untuk pesan.
 * Dipakai oleh : PpicController::processWo (BLOCK), applyOne / applyAll (tolak di server), partial v2.partials.schedule-guard (popup).
 * Rumus        : tak terjadwal = operasi aktif (belum selesai / jalan) tanpa waktu mulai. SUBCON dan MANUAL (crew) selalu punya waktu
 *                (mesin 0, lead days / 30 menit dari ScheduleSolver) sehingga tidak pernah masuk daftar.
 * Status       : Aktif di mockup (ddt*).
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Support\V2\Data\Ppic;

final class ScheduleGuard
{
    /** @return list<array{wo_id: int, wo_no: string, process: string, reason: string}> */
    public static function issues(?int $woId = null): array
    {
        $ops = array_filter(Ppic::operations(), fn ($o) => ! $o['start'] && ! in_array($o['bucket'], ['done', 'running'], true)
            && ($woId === null || $o['wo_id'] === $woId));

        return array_values(array_map(fn ($o) => ['wo_id' => $o['wo_id'], 'wo_no' => (string) $o['wo_no'], 'process' => (string) $o['process'], 'reason' => self::reason($o)], $ops));
    }

    /** One line for a flash / JSON message: "WO2600001 Cutting: reason · ...". @param list<array{wo_no: string, process: string, reason: string}> $issues */
    public static function line(array $issues): string
    {
        return implode(' · ', array_map(fn ($i) => $i['wo_no'].' '.$i['process'].': '.$i['reason'], $issues));
    }

    /** @param array<string, mixed> $o operation row of Ppic::operations */
    private static function reason(array $o): string
    {
        return match (true) {
            in_array('BLOCK', $o['gates'], true) => (string) ($o['gate_note'] ?: t('A gate is blocked.')),
            ! $o['candidates'] => t('No machine can run this process.'),
            in_array('WAIT', $o['gates'], true) => t('Waiting for material or tooling').($o['gate_note'] ? ': '.$o['gate_note'] : ''),
            default => t('No machine slot found in the horizon.'),
        };
    }
}
