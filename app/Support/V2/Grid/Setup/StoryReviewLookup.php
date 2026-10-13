<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Others > Old Stories Review: Jo membaca tiap pesan lamanya tentang ERP utuh (kartu blog) lalu memutuskan Adopt / Later / Ignore.
 * Sumber hulu  : tm2sys.sys_story_review (diisi php artisan review:import dari docs/ringkas/cerita_lama_penuh.md).
 * Input        : Pilihan Decision per pesan (tombol besar di kartu); tombol Process with Claude di header layar.
 * Output       : decision, decided_by, decided_at; antrean sys_review_request (scope STORY); pesan yang Adopt ditulis Claude ke dokumen yang benar setelah Jo menyetujui.
 * Dipakai oleh : Menu SET_STORY_REVIEW (v2:setup-story-review).
 * Status       : Aktif (ddt*).
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysStoryReview;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StoryReviewLookup extends ReviewLookup
{
    private const TONES = ['ADOPT' => 'success', 'LATER' => 'warn', 'IGNORE' => 'neutral'];

    public function slug(): string
    {
        return 'setup-story-review';
    }

    public function title(): string
    {
        return 'Old Stories Review';
    }

    public function scope(): string
    {
        return 'STORY';
    }

    public function icon(): string
    {
        return 'fa-solid fa-book-open';
    }

    public function guide(): ?string
    {
        return 'Pick Adopt (this is my story), Later or Ignore for each message. Process with Claude queues your decisions; Claude reads the queue at the start of a session and writes the adopted stories into the right document, and you approve the result.';
    }

    protected function decisionLabels(): array
    {
        return ['ADOPT' => 'Adopt', 'LATER' => 'Later', 'IGNORE' => 'Ignore'];
    }

    protected function query(): Builder
    {
        return SysStoryReview::query()->orderBy('msg_date')->orderBy('id_story_review');
    }

    // Isi pesan ada di kartu (posts); baris ini hanya yang dibutuhkan balasan Save.
    protected function row(Model $m): array
    {
        return ['msg_date' => $m->msg_date, 'decision' => (string) $m->decision];
    }

    public function choiceTones(): array
    {
        return self::TONES;
    }

    // Kartu blog: tanggal pesan sebagai judul, pesan utuh apa adanya, lalu tombol keputusan.
    public function posts(): array
    {
        return $this->query()->limit($this->limit)->get()->map(fn (SysStoryReview $m) => [
            'id' => $m->getKey(), 'title' => Locale::date($m->msg_date), 'meta' => tf('Session :ref · :n characters', ['ref' => $m->session_ref, 'n' => $m->char_count]),
            'text' => $m->full_text, 'decision' => (string) $m->decision, 'state' => $this->decidedState($m), 'data' => ['decision' => (string) $m->decision],
        ])->all();
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Not decided', 'where' => ['decision' => ['']], 'tone' => 'warn'],
            ['label' => 'Adopt', 'where' => ['decision' => 'ADOPT'], 'tone' => 'success'],
            ['label' => 'Later', 'where' => ['decision' => 'LATER'], 'tone' => 'accent'],
            ['label' => 'Ignore', 'where' => ['decision' => 'IGNORE'], 'tone' => 'neutral'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'msg_date', 'label' => 'Message date', 'type' => 'date', 'readonly' => true],
            $this->decisionColumn(),
        ];
    }
}
