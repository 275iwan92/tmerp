<?php

/*
 * REMARK
 * Tujuan       : Dasar dua layar review Setup > Others (MD Review, Old Stories Review): daftar butir bergaya blog (x-dl::review-feed), hanya kolom Decision yang bisa diubah.
 * Sumber hulu  : sys_md_review / sys_story_review (diisi php artisan review:import); keputusan Jo.
 * Input        : Pilihan Decision per butir (tombol besar di kartu; tersimpan lewat rute v2.lookup.save).
 * Output       : decision + decided_by + decided_at per baris; kartu lewat posts(); jumlahnya dibaca ReviewRequestService untuk tombol Process with Claude.
 * Dipakai oleh : MdReviewLookup, StoryReviewLookup, resources/views/v2/screens/setup-review.blade.php.
 * Status       : Aktif (ddt*).
 */

namespace App\Support\V2\Grid\Setup;

use App\Support\V2\Grid\DbLookup;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

abstract class ReviewLookup extends DbLookup
{
    // MD | STORY (kunci ReviewRequestService::SCOPES).
    abstract public function scope(): string;

    // Kode keputusan yang sah => label English (diterjemahkan lewat kamus).
    abstract protected function decisionLabels(): array;

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
        return 'Others';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.setup-review'] + parent::screen();
    }

    // Satu kartu blog per baris: [id, title, meta, badge, tone, summary, text, body, note, decision, state, data{proposal, decision}].
    abstract public function posts(): array;

    // Warna tombol keputusan saat terpilih (kode => success | warn | danger | accent | neutral).
    abstract public function choiceTones(): array;

    // Semua yang dibutuhkan x-dl::review-feed: kartu, tombol keputusan + warnanya, saringan (= stats), alamat simpan.
    public function feed(): array
    {
        return ['posts' => $this->posts(), 'choices' => $this->decisionLabels(), 'choiceTones' => $this->choiceTones(), 'filters' => $this->stats(),
            'saveUrl' => route('v2.lookup.save', $this->slug()), 'source' => $this->sourceText()];
    }

    // Teks "siapa dan kapan" di bawah kartu yang sudah diputuskan.
    protected function decidedState(Model $m): string
    {
        return $m->decision ? trim($m->decided_by.' · '.($m->decided_at ? Locale::date($m->decided_at) : ''), ' ·') : '';
    }

    // Kolom Decision: satu-satunya kolom yang bisa diubah di layar.
    protected function decisionColumn(): array
    {
        return ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => array_map('t', $this->decisionLabels()), 'width' => '150px'];
    }

    protected function rules(): array
    {
        return ['decision' => ['nullable', Rule::in(array_keys($this->decisionLabels()))]];
    }

    // Baris hanya berasal dari php artisan review:import; layar ini tidak menambah baris (berlaku juga bila grid menampilkan tombol baris).
    protected function create(Model $model, array $data, ?string $login): Model
    {
        throw ValidationException::withMessages(['row' => t('Rows cannot be added on this screen.')]);
    }

    // Siapa dan kapan memutuskan dicatat di server, bukan dikirim layar.
    protected function saved(Model $row): void
    {
        if ($row->wasChanged('decision')) {
            $row->forceFill(['decided_by' => $row->decision ? auth()->user()?->login : null, 'decided_at' => $row->decision ? now() : null])->save();
        }
    }
}
