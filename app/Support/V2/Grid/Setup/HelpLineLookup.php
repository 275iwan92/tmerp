<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysHelpLine;
use App\Models\Sys\SysHelpTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Setup > Help Lines: isi tiap topik help dalam potongan pendek -- langkah cara pakai (berurutan) dan penjelasan tiap isian /
// kolom laporan. Satu potongan = satu kalimat paling panjang 255 karakter (satu teks kamus, mudah dicari asisten AI).
class HelpLineLookup extends HelpLookup
{
    public function slug(): string
    {
        return 'setup-help-lines';
    }

    public function title(): string
    {
        return 'Help Lines';
    }

    public function icon(): string
    {
        return 'fa-solid fa-list-check';
    }

    public function search(): string
    {
        return 'Search topic, field or text...';
    }

    public function guide(): ?string
    {
        return 'One row = one short text of a help topic: a step of how to use the screen, or the explanation of one field or report column. '
            .'Keep each text to one action or one fact, at most 255 characters. Field = the label exactly as written on the screen. Order sets the sequence inside the topic.';
    }

    protected function textColumn(): string
    {
        return 'body';
    }

    protected function models(): array
    {
        return [SysHelpLine::class, SysHelpTopic::class];
    }

    protected function query(): Builder
    {
        return SysHelpLine::query()->orderBy('help_topic_id')->orderByDesc('line_type')->orderBy('sort_order')->orderBy('id_help_line');
    }

    public function columns(): array
    {
        return [
            ['key' => 'help_topic_id', 'label' => 'Topic', 'type' => 'select', 'options' => SysHelpTopic::query()->orderBy('title')->pluck('title', 'id_help_topic')->all(),
                'required' => true, 'width' => '240px'],
            ['key' => 'line_type', 'label' => 'Kind', 'type' => 'select', 'options' => array_map('t', SysHelpLine::TYPES), 'required' => true, 'width' => '160px'],
            ['key' => 'field_label', 'label' => 'Field', 'width' => '190px'],
            ['key' => 'body', 'label' => 'Text (English)', 'required' => true, 'width' => '460px'],
            ...$this->langColumns('460px'),
            ['key' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => '80px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['help_topic_id' => [Rule::exists(SysHelpTopic::class, 'id_help_topic')], 'line_type' => [Rule::in(array_keys(SysHelpLine::TYPES))],
            'field_label' => ['string', 'max:100'], 'body' => ['string', 'max:255']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        return ($in['line_type'] ?? null) === SysHelpLine::FIELD && trim((string) ($in['field_label'] ?? '')) === ''
            ? t('A field explanation needs the Field label.') : null;
    }
}
