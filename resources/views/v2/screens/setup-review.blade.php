{{-- REMARK
     Tujuan       : Layar review Setup > Others (MD Review dan Old Stories Review) bergaya blog: baca kartu, putuskan dengan tombol besar, lalu Process with Claude.
     Sumber hulu  : tm2sys.sys_md_review / sys_story_review lewat MdReviewLookup / StoryReviewLookup (feed()); antrean sys_review_request lewat ReviewRequestService.
     Input        : Tombol Keep / Condense / Discard (atau Adopt / Later / Ignore) per kartu, tersimpan seketika; tombol Process with Claude (POST v2.review.request).
     Output       : Keputusan tersimpan + permintaan REQUESTED yang dibaca Claude lewat php artisan review:requests.
     Dipakai oleh : Menu SET_MD_REVIEW dan SET_STORY_REVIEW (screen() milik ReviewLookup).
     Status       : Aktif (ddt*). --}}
@php
    use App\Services\Sys\ReviewRequestService;
    use App\Support\V2\Crumb;

    $grid = $screen['grid'];
    $scope = $grid->scope();
    $latest = ReviewRequestService::latest($scope);
    $feed = $grid->feed();
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif
@if ($guide = $grid->guide())<x-dl::alert tone="info" :message="$guide" />@endif
@if ($latest)
    <x-dl::alert :tone="$latest->status === 'DONE' ? 'success' : 'warn'"
        :message="raw(tf('Last request: :status, :at by :by. :decided of :total decided (:summary).', ['status' => $latest->status, 'at' => \Devlib\Locale::date($latest->requested_at),
            'by' => $latest->requested_by, 'decided' => $latest->n_decided, 'total' => $latest->n_total, 'summary' => $latest->summary]))" />
@endif

@php
    ob_start();
@endphp
<x-dl::action-form action="{{ route('v2.review.request', strtolower($scope)) }}">
    <x-dl::button size="sm" icon="fa-paper-plane" label="Process with Claude" type="submit" />
</x-dl::action-form>
@php
    Crumb::actions(ob_get_clean());
@endphp

<x-dl::review-feed :posts="$feed['posts']" :choices="$feed['choices']" :choice-tones="$feed['choiceTones']" :filters="$feed['filters']" :save-url="$feed['saveUrl']" :source="$feed['source']" />
