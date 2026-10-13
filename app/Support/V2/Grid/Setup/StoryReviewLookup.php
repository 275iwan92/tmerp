<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Others > Old Stories Review: Jo memutuskan tiap pesan lamanya tentang ERP (Adopt / Later / Ignore); tab kedua membaca pesan utuh.
 * Sumber hulu  : tm2sys.sys_story_review (diisi php artisan review:import dari docs/ringkas/cerita_lama_penuh.md).
 * Input        : Pilihan Decision per pesan; tombol Process with Claude di header layar.
 * Output       : decision, decided_by, decided_at; antrean sys_review_request (scope STORY); pesan yang Adopt ditulis Claude ke dokumen yang benar setelah Jo menyetujui.
 * Dipakai oleh : Menu SET_STORY_REVIEW (v2:setup-story-review).
 * Status       : Aktif (ddt*).
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysStoryReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StoryReviewLookup extends ReviewLookup
{
    private const PREVIEW = 450;

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

    protected function row(Model $m): array
    {
        return ['msg_date' => $m->msg_date, 'session_ref' => $m->session_ref, 'char_count' => (int) $m->char_count,
            'preview' => Str::limit(Str::squish($m->full_text), self::PREVIEW, '...'), 'full_text' => $m->full_text, 'decision' => (string) $m->decision];
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
            ['key' => 'msg_date', 'label' => 'Message date', 'type' => 'date', 'readonly' => true, 'width' => '115px'],
            $this->decisionColumn(),
            ['key' => 'preview', 'label' => 'Message', 'readonly' => true, 'width' => '760px'],
            ['key' => 'session_ref', 'label' => 'Session', 'readonly' => true, 'width' => '90px'],
            ['key' => 'char_count', 'label' => 'Characters', 'type' => 'number', 'readonly' => true, 'width' => '90px'],
        ];
    }

    // Tab "Full text": tanggal + isi pesan utuh, hanya baca.
    public function fullColumns(): array
    {
        return [
            ['key' => 'msg_date', 'label' => 'Message date', 'type' => 'date', 'readonly' => true, 'width' => '130px'],
            ['key' => 'full_text', 'label' => 'Full message', 'readonly' => true, 'width' => '1100px'],
        ];
    }
}
