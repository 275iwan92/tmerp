{{-- REMARK
     Tujuan       : Layar review Setup > Others (MD Review dan Old Stories Review): daftar keputusan Jo + tombol header Process with Claude + status permintaan terakhir.
     Sumber hulu  : tm2sys.sys_md_review / sys_story_review lewat MdReviewLookup / StoryReviewLookup; antrean sys_review_request lewat ReviewRequestService.
     Input        : Pilihan Decision per baris (tersimpan otomatis); tombol Process with Claude (POST v2.review.request).
     Output       : Keputusan tersimpan + permintaan REQUESTED yang dibaca Claude lewat php artisan review:requests.
     Dipakai oleh : Menu SET_MD_REVIEW dan SET_STORY_REVIEW (screen() milik ReviewLookup).
     Status       : Aktif (ddt*). --}}
@php
    use App\Services\Sys\ReviewRequestService;
    use App\Support\V2\Crumb;
    use Illuminate\Support\Facades\Blade;

    $grid = $screen['grid'];
    $p = $grid->props();
    $scope = $grid->scope();
    $latest = ReviewRequestService::latest($scope);
    $counts = ReviewRequestService::counts($scope);
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
    Crumb::status(Blade::render('<x-dl::badge :label="$l" tone="info" />', ['l' => raw(t('Decided').': '.$counts['decided'].' / '.$counts['total'])]));
@endphp

@if ($scope === 'STORY')
    <x-dl::tabs class="dl-tabs--caps" :tabs="['decide' => ['Decide', 'fa-check-double'], 'full' => ['Full text', 'fa-book-open']]">
        <x-slot:decide>
            <x-dl::lookup-grid class="dl-sheet--roomy" :rows="$p['rows']" :columns="$p['columns']" :save-url="$p['saveUrl']" :stats="$p['stats']" :source="$p['source']" :add-rows="false" :auto-save="true" :legible="true" />
        </x-slot:decide>
        <x-slot:full>
            <x-dl::lookup-grid class="dl-sheet--roomy" :rows="$p['rows']" :columns="$grid->fullColumns()" :save-url="$p['saveUrl']" :source="$p['source']" :add-rows="false" :auto-save="true" :legible="true" />
        </x-slot:full>
    </x-dl::tabs>
@else
    <x-dl::lookup-grid :rows="$p['rows']" :columns="$p['columns']" :save-url="$p['saveUrl']" :stats="$p['stats']" :source="$p['source']" :add-rows="false" :auto-save="true" :legible="true" />
@endif
