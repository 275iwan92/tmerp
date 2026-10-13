<?php

/*
 * REMARK
 * Tujuan       : Rute tombol "Process with Claude" di layar Setup > Others > MD Review dan Old Stories Review.
 * Sumber hulu  : Keputusan di sys_md_review / sys_story_review.
 * Input        : POST /v2/review/{md|story}/request dari header layar (user dengan akses modul SETUP).
 * Output       : Baris sys_review_request (REQUESTED) lewat ReviewRequestService, lalu kembali ke layar asal dengan pesan.
 * Dipakai oleh : routes/web.php (grup v2); layar resources/views/v2/screens/setup-review.blade.php.
 * Status       : Aktif (ddt*).
 */

use App\Services\Sys\ReviewRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/review')->name('v2.review.')->middleware('module:SETUP')->group(function () {
    Route::post('/{scope}/request', function (string $scope) {
        $key = strtoupper($scope);
        $home = route('v2.screen', ReviewRequestService::SCOPES[$key]['slug']);
        try {
            [, $updated] = app(ReviewRequestService::class)->request($key, Auth::user()->login);
        } catch (DomainException $e) {
            return redirect($home)->withErrors(['review' => $e->getMessage()]);
        }

        return redirect($home)->with('v2_flash', $updated ? t('The waiting request was updated with your latest decisions.')
            : t('Sent to Claude. Claude reads the queue at the start of a session.'));
    })->where('scope', 'md|story')->name('request');
});
