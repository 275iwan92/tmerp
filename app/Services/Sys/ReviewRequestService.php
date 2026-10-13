<?php

/*
 * REMARK
 * Tujuan       : Mengantre permintaan "Process with Claude" dari layar MD Review dan Old Stories Review (satu permintaan terbuka per cakupan).
 * Sumber hulu  : sys_md_review dan sys_story_review (keputusan Jo di layar).
 * Input        : cakupan (MD / STORY) dan login pemohon; keputusan dibaca dari tabel review.
 * Output       : Baris sys_review_request (REQUESTED) dengan jumlah keputusan per jenis; status DONE saat Claude selesai memproses.
 * Dipakai oleh : routes/v2_review.php, layar v2.screens.setup-review, php artisan review:requests.
 * Status       : Aktif (ddt*).
 */

namespace App\Services\Sys;

use App\Models\Sys\SysMdReview;
use App\Models\Sys\SysReviewRequest;
use App\Models\Sys\SysStoryReview;
use DomainException;
use InvalidArgumentException;

final class ReviewRequestService
{
    /** Cakupan review: model keputusan, slug layar, judul. */
    public const SCOPES = [
        'MD' => ['model' => SysMdReview::class, 'slug' => 'setup-md-review', 'title' => 'MD Review'],
        'STORY' => ['model' => SysStoryReview::class, 'slug' => 'setup-story-review', 'title' => 'Old Stories Review'],
    ];

    /** @return array{total: int, decided: int, by: array<string, int>} jumlah baris, yang sudah diputuskan, dan per keputusan */
    public static function counts(string $scope): array
    {
        $model = (self::SCOPES[$scope] ?? throw new InvalidArgumentException("Unknown review scope {$scope}"))['model'];
        $by = $model::query()->whereNotNull('decision')->selectRaw('decision, count(*) as n')->groupBy('decision')->pluck('n', 'decision')
            ->map(fn ($n) => (int) $n)->all();

        return ['total' => $model::query()->count(), 'decided' => array_sum($by), 'by' => $by];
    }

    // Permintaan terbaru cakupan itu (terbuka atau selesai), untuk status di layar.
    public static function latest(string $scope): ?SysReviewRequest
    {
        return SysReviewRequest::query()->where('scope', $scope)->orderByDesc('id_review_request')->first();
    }

    /** @return array{0: SysReviewRequest, 1: bool} permintaan dan apakah itu pembaruan permintaan terbuka (bukan baris baru) */
    public function request(string $scope, string $login): array
    {
        $c = self::counts($scope);
        if ($c['decided'] === 0) {
            throw new DomainException(t('Make at least one decision before sending to Claude.'));
        }
        $data = ['n_total' => $c['total'], 'n_decided' => $c['decided'], 'requested_by' => $login, 'requested_at' => now(),
            'summary' => collect($c['by'])->map(fn ($n, $k) => "{$k}={$n}")->implode('; ')];

        $open = SysReviewRequest::query()->where('scope', $scope)->where('status', SysReviewRequest::REQUESTED)->first();
        if ($open) {
            $open->update($data);

            return [$open, true];
        }

        return [SysReviewRequest::query()->create($data + ['scope' => $scope, 'status' => SysReviewRequest::REQUESTED]), false];
    }

    // Claude selesai memproses: permintaan ditutup dengan catatan hasilnya.
    public function done(int $id, string $by, ?string $note = null): SysReviewRequest
    {
        $req = SysReviewRequest::query()->findOrFail($id);
        $req->update(['status' => SysReviewRequest::DONE, 'handled_by' => $by, 'handled_at' => now(), 'note' => $note]);

        return $req;
    }
}
