<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SecModule;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysHelpTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Help Topics: satu baris = satu layar, laporan, atau panduan umum -- untuk apa layar itu (Purpose) dan kata carinya.
// Langkah cara pakai dan penjelasan tiap isian ada di Help Lines. Dibaca Help Center dan asisten AI.
class HelpTopicLookup extends HelpLookup
{
    public function slug(): string
    {
        return 'setup-help-topics';
    }

    public function title(): string
    {
        return 'Help Topics';
    }

    public function icon(): string
    {
        return 'fa-solid fa-book-open';
    }

    public function search(): string
    {
        return 'Search screen, purpose or keyword...';
    }

    public function guide(): ?string
    {
        return 'One row = one screen, report or general guide. Key = the screen address in Menu Setup (guide-... for a general guide); a saved Key cannot be changed. '
            .'Purpose says what the screen is for. Keywords are the words people type when they look for it. Steps and field explanations are in Help Lines.';
    }

    protected function textColumn(): string
    {
        return 'purpose';
    }

    protected function query(): Builder
    {
        return SysHelpTopic::query()->orderBy('module_code')->orderBy('sort_order')->orderBy('title');
    }

    public function columns(): array
    {
        $modules = SecModule::query()->orderBy('sort_order')->pluck('module_name', 'module_code')->all() + [SecUser::PUBLIC_MODULE => 'General (everyone)'];

        return [
            ['key' => 'topic_key', 'label' => 'Key', 'required' => true, 'unique' => true, 'lockSaved' => true, 'width' => '210px'],
            ['key' => 'topic_type', 'label' => 'Type', 'type' => 'select', 'options' => SysHelpTopic::TYPES, 'required' => true, 'width' => '130px'],
            ['key' => 'module_code', 'label' => 'Module', 'type' => 'select', 'options' => $modules, 'width' => '170px'],
            ['key' => 'title', 'label' => 'Title', 'required' => true, 'width' => '250px'],
            ['key' => 'purpose', 'label' => 'Purpose (English)', 'required' => true, 'width' => '420px'],
            ...$this->langColumns(),
            ['key' => 'keywords', 'label' => 'Keywords', 'width' => '320px'],
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function prepare(array $in): array
    {
        if (isset($in['topic_key']) && is_string($in['topic_key'])) {
            $in['topic_key'] = strtolower(trim($in['topic_key']));
        }

        return parent::prepare($in);
    }

    protected function rules(): array
    {
        return ['topic_key' => ['regex:/^[a-z0-9-]{2,80}$/'], 'topic_type' => [Rule::in(array_keys(SysHelpTopic::TYPES))], 'title' => ['string', 'max:150'],
            'purpose' => ['string', 'max:255'], 'keywords' => ['string', 'max:255']];
    }
}
