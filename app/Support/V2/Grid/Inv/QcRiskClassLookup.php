<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvQcRiskClass;
use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Services\Inventory\QcSamplingService;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

// Risk Class (Q-01, D21): kelas risiko A / B / C per kategori material atau per item; kepala QC mengisinya. Item (kode bahan) lebih khusus daripada
// kategori, kategori terdalam menang atas induknya, tanpa baris = kelas B. Satu baris memilih kategori ATAU mengetik kode bahan.
class QcRiskClassLookup extends DbLookup
{
    public function slug(): string
    {
        return 'qc-risk-class';
    }

    public function title(): string
    {
        return 'Risk Class';
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Batch & Quality › Setup';
    }

    public function icon(): string
    {
        return 'fa-solid fa-triangle-exclamation';
    }

    public function guide(): ?string
    {
        return 'The risk class decides the sampling intensity. A: food grade, FSC / PEFC claim, hazardous material. B: paper, board, film, corrugated, glue from a known supplier (also the default). C: small auxiliary materials. Pick a material category, or type one material code to set a single item.';
    }

    protected function models(): array
    {
        return [InvQcRiskClass::class, MatCategory::class];
    }

    protected function query(): Builder
    {
        return InvQcRiskClass::query()->orderBy('scope_type')->orderBy('category_id')->orderBy('material_code');
    }

    // Kategori sebagai "Induk › Anak" supaya nama yang sama di cabang berbeda tidak tertukar.
    private function categoryOptions(): array
    {
        $all = MatCategory::query()->where('activate', true)->orderBy('level')->orderBy('category_name')->get(['id_category', 'parent_id', 'category_name'])->keyBy('id_category');
        $path = function (int $id) use ($all, &$path): string {
            $c = $all[$id] ?? null;

            return $c ? ($c->parent_id && isset($all[$c->parent_id]) ? $path((int) $c->parent_id).' › ' : '').$c->category_name : '';
        };

        return $all->mapWithKeys(fn ($c) => [$c->id_category => $path((int) $c->id_category)])->sort()->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'category_id', 'label' => 'Material Category', 'type' => 'select', 'options' => $this->categoryOptions(), 'width' => '320px'],
            ['key' => 'material_code', 'label' => 'or Item Code', 'case' => 'uppercase', 'width' => '150px'],
            ['key' => 'risk_class', 'label' => 'Risk Class', 'type' => 'select', 'options' => array_map('t', QcSamplingService::CLASSES), 'required' => true, 'width' => '170px'],
            ['key' => 'note', 'label' => 'Note', 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['material_code'])) {
            $in['material_code'] = strtoupper(trim((string) $in['material_code']));
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['risk_class' => [Rule::in(array_keys(QcSamplingService::CLASSES))], 'note' => ['max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $category = (int) ($in['category_id'] ?? 0);
        $code = (string) ($in['material_code'] ?? '');
        if (($category > 0) === ($code !== '')) {
            return t('Choose a material category or type an item code, not both and not neither.');
        }
        if ($code !== '' && ! MatRaw::query()->where('material_code', $code)->exists()) {
            return tf('Item code :code is not in the raw material master.', ['code' => raw($code)]);
        }
        $dup = InvQcRiskClass::query()->where('scope_key', $this->scopeKey($category, $code))->when($id, fn ($q) => $q->whereKeyNot($id))->exists();

        return $dup ? t('This category or item already has a risk class.') : null;
    }

    // Kunci unik baris: kategori C:<id>, item I:<id bahan>.
    private function scopeKey(int $category, string $code): string
    {
        return $category > 0 ? 'C:'.$category : 'I:'.(int) MatRaw::query()->where('material_code', $code)->value('material_id');
    }

    // Baris baru perlu scope_key sementara (kolom wajib); saved() menggantinya dengan kunci sebenarnya.
    protected function defaults(): array
    {
        return ['scope_key' => 'NEW-'.bin2hex(random_bytes(8)), 'scope_type' => 'CATEGORY'];
    }

    protected function saved(Model $row): void
    {
        $category = (int) $row->category_id;
        $code = (string) $row->material_code;
        $row->forceFill(['scope_key' => $this->scopeKey($category, $code), 'scope_type' => $category > 0 ? 'CATEGORY' : 'ITEM',
            'material_id' => $category > 0 ? null : (int) MatRaw::query()->where('material_code', $code)->value('material_id'), 'material_code' => $category > 0 ? null : $code])->save();
    }
}
