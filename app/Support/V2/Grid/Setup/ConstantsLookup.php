<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysConstant;
use App\Models\Sys\SysConstantCategory;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

// Setup > Constants (v1: pages::setup.constants): SEMUA baris sys_constant diisi langsung di sel -- angka dan teks yang dibaca
// engine, cetakan, dan aturan (aturan "semua data wajib punya inputan"). Key yang sudah tersimpan terkunci karena dibaca kode.
class ConstantsLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-constants';
    }

    public function title(): string
    {
        return 'Constants';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Constants';
    }

    public function icon(): string
    {
        return 'fa-solid fa-sliders';
    }

    public function guide(): ?string
    {
        return 'Values read by the engines, prints and rules. A constant holds a number, a text, or both. The Key is read by the program, '
            .'so a saved Key cannot be changed; switch the row off instead. Search by key or description.';
    }

    protected function models(): array
    {
        return [SysConstant::class, SysConstantCategory::class];
    }

    protected function query(): Builder
    {
        return SysConstant::query()->orderByRaw('category_code IS NULL')->orderBy('category_code')->orderBy('constant_group')->orderBy('constant_key');
    }

    public function columns(): array
    {
        $categories = SysConstantCategory::query()->orderBy('sort_order')->pluck('label', 'code')->all();

        return [
            ['key' => 'category_code', 'label' => 'Category', 'type' => 'select', 'options' => $categories, 'width' => '170px'],
            ['key' => 'constant_group', 'label' => 'Group', 'case' => 'uppercase', 'width' => '150px'],
            ['key' => 'constant_key', 'label' => 'Key', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '270px'],
            ['key' => 'constant_number', 'label' => 'Number Value', 'type' => 'number', 'width' => '140px'],
            ['key' => 'constant_text', 'label' => 'Text Value', 'width' => '260px'],
            ['key' => 'description', 'label' => 'Description', 'width' => '420px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        foreach (['constant_key', 'constant_group'] as $k) {
            if (isset($in[$k]) && is_string($in[$k])) {
                $in[$k] = strtoupper(trim($in[$k]));
            }
        }

        return $in;
    }

    protected function rules(): array
    {
        return ['category_code' => [Rule::exists(SysConstantCategory::class, 'code')], 'constant_group' => ['string', 'max:50'],
            'constant_key' => ['string', 'max:50'], 'constant_text' => ['string', 'max:255'], 'description' => ['string', 'max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        // Sama dengan v1: zona waktu aplikasi harus nama zona yang sah, kalau tidak semua jam di layar ikut salah.
        $key = $id ? SysConstant::query()->whereKey($id)->value('constant_key') : ($in['constant_key'] ?? null);
        if ($key === 'APP_TIMEZONE' && ! in_array((string) ($in['constant_text'] ?? ''), timezone_identifiers_list(), true)) {
            return t('APP_TIMEZONE needs a valid time zone name in Text Value, for example Asia/Jakarta.');
        }

        return null;
    }

    protected function saved(Model $row): void
    {
        // AppServiceProvider meng-cache zona waktu; dibuang supaya perubahan langsung berlaku.
        Cache::forget('app.timezone');
    }
}
