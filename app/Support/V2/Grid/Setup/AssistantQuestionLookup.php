<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecModule;
use App\Models\Sys\SysAssistantMetric;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Assistant Questions: katalog pertanyaan ANGKA yang dikenal asisten. Angkanya dihitung kelas program (satu Key = satu
// kelas, didaftar developer); yang dirawat di sini: frasa pemicunya, siapa yang boleh (modul + level security), dan aktif.
class AssistantQuestionLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-assistant-questions';
    }

    public function title(): string
    {
        return 'Assistant Questions';
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
        return 'Settings';
    }

    public function icon(): string
    {
        return 'fa-solid fa-wand-magic-sparkles';
    }

    public function guide(): ?string
    {
        return 'Number questions the assistant knows. The number itself is calculated by the program; here you set the trigger phrases '
            .'(any language, separated by commas), the module the asker must be allowed to open, and the minimum security level (0 = module right is enough). '
            .'A new question needs a program class first, so rows cannot be added here.';
    }

    protected function query(): Builder
    {
        return SysAssistantMetric::query()->orderBy('sort_order')->orderBy('title');
    }

    public function columns(): array
    {
        return [
            ['key' => 'metric_key', 'label' => 'Key', 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '160px'],
            ['key' => 'title', 'label' => 'Title', 'required' => true, 'width' => '200px'],
            ['key' => 'phrases', 'label' => 'Trigger phrases', 'required' => true, 'width' => '560px'],
            ['key' => 'module_code', 'label' => 'Module', 'type' => 'select', 'options' => SecModule::query()->orderBy('sort_order')->pluck('module_name', 'module_code')->all(), 'width' => '170px'],
            ['key' => 'min_level', 'label' => 'Minimum level', 'type' => 'number', 'width' => '120px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['title' => ['string', 'max:150'], 'phrases' => ['string', 'max:2000'], 'min_level' => ['integer', 'min:0', 'max:9'],
            'module_code' => [Rule::exists(SecModule::class, 'module_code')]];
    }

    protected function check(array $in, ?int $id): ?string
    {
        // Baris hanya berarti bila ada kelas hitungnya: Key harus salah satu metric terdaftar.
        $key = $id ? SysAssistantMetric::query()->whereKey($id)->value('metric_key') : ($in['metric_key'] ?? null);
        $known = array_map(fn ($class) => app($class)->key(), config('assistant.metrics', []));

        return in_array($key, $known, true) ? null : 'This Key has no program class. Ask the developer to add the question first.';
    }
}
