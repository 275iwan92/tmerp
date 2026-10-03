<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysAssistantMessage;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Setup > Assistant Log: semua pertanyaan ke asisten dan jenis jawabannya. Gunanya: melihat pertanyaan yang TIDAK terjawab
// atau dijempol turun, lalu menambah Help / frasa pemicu / angka baru. Baris hilang sendiri sesuai masa simpan per level.
class AssistantLogReport extends DbGrid
{
    private const KINDS = ['SCREEN' => 'Screen', 'HELP' => 'Help', 'NUMBER' => 'Number', 'DENIED' => 'Denied', 'UNKNOWN' => 'Not answered'];

    public function slug(): string
    {
        return 'setup-assistant-log';
    }

    public function title(): string
    {
        return 'Assistant Log';
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
        return 'Help and Support';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-question';
    }

    public function search(): string
    {
        return 'Search question or asker...';
    }

    public function guide(): ?string
    {
        return 'Every question asked to the assistant. Filter on Not answered or Thumbs down to see what to add to the Help, to the trigger phrases, or as a new number. '
            .'Rows disappear by themselves after the retention of the asker level.';
    }

    protected function query(): Builder
    {
        return SysAssistantMessage::query()->orderByDesc('id_assistant_message');
    }

    protected function row(Model $m): array
    {
        return [
            'at' => $m->created_at?->toDateTimeString(), 'asker' => $m->user_name, 'level' => (int) $m->user_level, 'question' => $m->question,
            'kind' => self::KINDS[$m->reply_kind] ?? $m->reply_kind, 'source' => $m->reply_key, 'answer' => $m->reply['big'] ?? $m->reply['text'] ?? $m->reply['title'] ?? null,
            'feedback' => match ($m->feedback) {
                1 => 'Thumbs up',
                -1 => 'Thumbs down',
                default => null,
            },
            'model' => $m->model_used ? t('Yes') : t('No'), 'ms' => (int) $m->duration_ms,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'at', 'label' => 'Asked at', 'type' => 'datetime'],
            ['key' => 'asker', 'label' => 'Asked by'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'number'],
            ['key' => 'question', 'label' => 'Question'],
            ['key' => 'kind', 'label' => 'Answer kind', 'type' => 'badge', 'tones' => ['Screen' => 'info', 'Help' => 'accent', 'Number' => 'success', 'Denied' => 'warn', 'Not answered' => 'danger']],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'answer', 'label' => 'Answer', 'visible' => false],
            ['key' => 'feedback', 'label' => 'Feedback', 'type' => 'badge', 'tones' => ['Thumbs up' => 'success', 'Thumbs down' => 'danger']],
            ['key' => 'model', 'label' => 'Local model', 'visible' => false],
            ['key' => 'ms', 'label' => 'Duration (ms)', 'type' => 'number', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Not answered', 'where' => ['kind' => 'Not answered'], 'tone' => 'danger'],
            ['label' => 'Thumbs down', 'where' => ['feedback' => 'Thumbs down'], 'tone' => 'danger'],
            ['label' => 'Denied', 'where' => ['kind' => 'Denied'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['kind' => 'Answer kind', 'asker' => 'Asked by'];
    }

    public function groups(): array
    {
        return ['kind' => 'Answer kind', 'asker' => 'Asked by'];
    }
}
