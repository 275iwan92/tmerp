<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use App\Models\Hrr\CndCandidate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Offering (v1: recruitment.offer.list -> offer.entry per LAMARAN). Baris = lamaran tahap OFFERING atau yang sudah punya
// penawaran, supaya {id} = id lamaran seperti route v1. Nilai gaji sengaja tidak ditampilkan di grid.
class OfferGrid extends RcrGrid
{
    private const OFFER_TONES = ['DRAFT' => 'neutral', 'PENDING_APPROVAL' => 'warn', 'APPROVED' => 'info', 'SENT' => 'info',
        'ACCEPTED' => 'success', 'REJECTED' => 'danger', 'NEGOTIATION' => 'accent'];

    public function slug(): string
    {
        return 'hrm-rcr-offer';
    }

    public function title(): string
    {
        return 'Offering';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('hrm.recruitment.offer.entry', 'application');
    }

    public function newUrl(): ?string
    {
        return null;
    }

    protected function models(): array
    {
        return [AppApplication::class, AppOffer::class, CndCandidate::class];
    }

    public function search(): string
    {
        return 'Search candidate, vacancy...';
    }

    protected function query(): Builder
    {
        return AppApplication::query()->with(['candidate', 'vacancy', 'offers'])
            ->where(fn ($q) => $q->where('status', 'OFFERING')->orWhereHas('offers'))->orderByDesc('id_application');
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Candidate', 'type' => 'identity', 'code' => 'phone'],
            ['key' => 'vacancy', 'label' => 'Vacancy'],
            ['key' => 'rounds', 'label' => 'Offers', 'type' => 'number'],
            ['key' => 'type', 'label' => 'Employment Type'],
            ['key' => 'start', 'label' => 'Start Date', 'type' => 'date'],
            ['key' => 'offer', 'label' => 'Offer Status', 'type' => 'badge', 'tones' => self::OFFER_TONES],
            ['key' => 'status', 'label' => 'Application', 'type' => 'badge', 'tones' => self::APP_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $last = $m->offers->sortByDesc('id_offer')->first();

        return [
            'name' => $m->candidate?->name, 'phone' => $m->candidate?->phone, 'vacancy' => $m->vacancy?->title, 'rounds' => $m->offers->count(),
            'type' => $last?->employment_type, 'start' => $last?->start_date?->toDateString(), 'offer' => $last?->status ?? 'DRAFT', 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Waiting approval', 'where' => ['offer' => 'PENDING_APPROVAL'], 'tone' => 'warn'],
            ['label' => 'Accepted', 'where' => ['offer' => 'ACCEPTED'], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['offer' => 'REJECTED'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['vacancy' => 'Vacancy', 'offer' => 'Offer Status'];
    }
}
