<?php

namespace App\Services\Production;

use App\Models\Prd\PrdWo;
use App\Models\Prd\PrdWoMaterial;
use App\Models\Prd\PrdWoOperation;
use App\Services\Scheduling\MpsEngine;
use Illuminate\Support\Facades\DB;

/**
 * Titik masuk TUNGGAL untuk membuat WO baru -- layar/flow apa pun ke depan
 * (dari CO, dari Costing, manual PPIC) WAJIB lewat sini, supaya Engine MRP
 * dan Engine MPS otomatis jalan "di balik layar saat pembuatan WO" (sesuai
 * permintaan), bukan dipanggil manual per-layar.
 *
 * Belum ada layar pemanggil sungguhan sesi ini (CO->WO conversion di luar
 * scope) -- service ini yang jadi bukti & titik uji Engine MRP/MPS bekerja.
 */
class WoCreationService
{
    public function __construct(
        private readonly MrpEngine $mrpEngine,
        private readonly MpsEngine $mpsEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $header  field PrdWo (wo_type, source_type, product_id, qty, due_date, dst)
     * @param  array<int, array<string, mixed>>  $materials  field PrdWoMaterial (tanpa wo_id)
     * @param  array<int, array<string, mixed>>  $operations  field PrdWoOperation (tanpa wo_id)
     */
    public function create(array $header, array $materials, array $operations, string $actorLogin): PrdWo
    {
        $wo = DB::connection('tm2prd')->transaction(function () use ($header, $materials, $operations, $actorLogin) {
            $wo = PrdWo::create(array_merge($header, [
                'wo_no' => PrdWo::nextWoNo(),
                'wo_status' => 'DRAFT',
                'schedule_layer' => 'SIMULATED',
                'created_by' => $actorLogin,
            ]));

            foreach ($materials as $seq => $material) {
                PrdWoMaterial::create(array_merge($material, [
                    'wo_id' => $wo->wo_id,
                    'seq_no' => $material['seq_no'] ?? ($seq + 1),
                    'created_by' => $actorLogin,
                ]));
            }

            foreach ($operations as $seq => $operation) {
                PrdWoOperation::create(array_merge($operation, [
                    'wo_id' => $wo->wo_id,
                    'seq_no' => $operation['seq_no'] ?? ($seq + 1),
                    'created_by' => $actorLogin,
                ]));
            }

            return $wo;
        });

        // Di luar transaction WO -- kegagalan Engine (mis. Rate Band belum ada,
        // atau PR draft gagal dibuat) tak boleh membatalkan WO yang sudah valid.
        $wo->load(['materials', 'operations']);

        $this->mrpEngine->runForWo($wo, $actorLogin);
        $this->mpsEngine->runForWo($wo, $actorLogin);

        return $wo->fresh(['materials', 'operations']);
    }
}
