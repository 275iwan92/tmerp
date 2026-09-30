<?php

namespace Devlib\Components;

// Daftar notifikasi (mockup_notification): ikon berwarna, judul, titik belum dibaca, keterangan, waktu relatif.
class NotificationList extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public string $title = 'Semua Notifikasi',
        public bool $markAll = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::notification-list';
    }

    public function render()
    {
        return <<<'BLADE'
            @php $unread = count(array_filter($items, fn ($n) => ! ($n['read'] ?? false))); @endphp
            <div {{ $attributes->class('dl-notif') }} {!! $dl() !!} data-dl-notif>
                <div class="dl-notif__head"><b>{{ $title }}</b>@if ($unread)<span class="dl-notif__count" data-dl-notif-count>{{ $unread }} belum dibaca</span>@endif
                    @if ($markAll && $unread)<button type="button" class="dl-notif__all" data-dl-notif-all>Tandai semua dibaca</button>@endif</div>
                @forelse ($items as $n)
                    <div class="dl-notif__item {{ ($n['read'] ?? false) ? '' : 'is-unread' }}" data-dl-notif-item>
                        <span class="dl-notif__icon dl-notif__icon--{{ $n['tone'] ?? 'accent' }}"><i class="fa-solid {{ \Devlib\Icon::fa($n['icon'] ?? 'notification') }}"></i></span>
                        <div class="dl-notif__text">
                            <div class="dl-notif__title">{{ $n['title'] }}@if (! ($n['read'] ?? false))<span class="dl-notif__dot"></span>@endif</div>
                            @if (! empty($n['body']))<div class="dl-notif__body">{{ $n['body'] }}</div>@endif
                            <div class="dl-notif__time">{{ isset($n['at']) ? \Devlib\Locale::at($n['at'])->diffForHumans() : '' }}</div>
                        </div>
                    </div>
                @empty
                    <x-dl::empty-state icon="notification" title="No notifications" />
                @endforelse
            </div>
            BLADE;
    }
}
