<?php

namespace App\Assistant;

use App\Models\Sys\SysHelpLine;
use App\Models\Sys\SysHelpTopic;
use App\Services\Help\HelpCenter;
use App\Support\Localization\Dictionary;
use Assistant\Contracts\Asker;
use Assistant\Contracts\Knowledge;

// Bantuan asisten = Help ERP (sys_help_topic + sys_help_line) dalam bahasa si penanya. Kata cari memuat teks English dan
// terjemahannya supaya pertanyaan Indonesia maupun English sama-sama ketemu.
final class ErpKnowledge implements Knowledge
{
    private array $topics = [];

    public function topics(Asker $asker): array
    {
        $lang = $asker->lang();

        return $this->topics[$lang] ??= SysHelpTopic::query()->where('activate', true)->with(['lines' => fn ($q) => $q->where('activate', true)])
            ->orderBy('sort_order')->get()->map(function (SysHelpTopic $t) use ($lang) {
                $say = fn (?string $en) => Dictionary::t($en, HelpCenter::CONTEXT, $lang);

                return [
                    'key' => $t->topic_key, 'title' => $t->title, 'purpose' => $say($t->purpose), 'words' => $t->keywords.' '.$t->title,
                    'steps' => $t->lines->where('line_type', SysHelpLine::STEP)->map(fn ($l) => $say($l->body))->values()->all(),
                    'fields' => $t->lines->where('line_type', SysHelpLine::FIELD)
                        ->map(fn ($l) => ['label' => (string) $l->field_label, 'text' => $say($l->body)])->values()->all(),
                ];
            })->all();
    }

    public function url(): ?string
    {
        return route('v2.screen', 'help-center');
    }
}
