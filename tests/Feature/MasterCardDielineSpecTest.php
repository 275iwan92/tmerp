<?php

/* REMARK
   Tujuan      : menjaga part komponen Master Card dummy membawa elements dieline dari tipe (bukan hanya type_code + dims).
   Sumber hulu : MasterCardService::dielineSpec, master prod_type.dieline_spec (hanya dibaca).
   Input       : type_code RTE_BOX dan dims contoh.
   Output      : lulus/gagal; tidak menulis database.
   Dipakai oleh: php artisan test --filter=MasterCardDielineSpecTest.
   Status      : aktif. */

namespace Tests\Feature;

use App\Models\Master\ProdType;
use App\Services\MasterCard\MasterCardService;
use ReflectionMethod;
use Tests\TestCase;

class MasterCardDielineSpecTest extends TestCase
{
    private function spec(?string $code, array $dims): array
    {
        $m = new ReflectionMethod(MasterCardService::class, 'dielineSpec');

        return json_decode($m->invoke(new MasterCardService('dummy'), $code, $dims), true);
    }

    public function test_component_spec_carries_type_elements_with_dims(): void
    {
        if (empty(ProdType::find('RTE_BOX')?->dieline_spec['elements'] ?? null)) {
            $this->markTestSkipped('prod_type RTE_BOX tanpa elements di database ini.');
        }

        $s = $this->spec('RTE_BOX', ['product_length' => 230]);

        $this->assertNotEmpty($s['elements']);
        $this->assertSame('RTE_BOX', $s['type_code']);
        $this->assertSame(['product_length' => 230], $s['dims']);
    }

    public function test_unknown_type_keeps_type_code_and_dims_only(): void
    {
        $this->assertSame(['type_code' => 'NOPE_X', 'dims' => []], $this->spec('NOPE_X', []));
    }
}
