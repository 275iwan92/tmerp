        <div class="dl-tabbar" data-dl-tabbar data-dl="tab MDI" data-dl-class="bagian Shell" data-dl-src="—"></div>
        <div class="dl-content" data-dl-content>
            <div class="dl-pane is-active" data-dl-home>
                {{-- Slot "dashboard" (x-dl::dashboard) = opening screen per jabatan; tanpa slot = sambutan bawaan. --}}
                @isset($dashboard)
                <div class="dl-home dl-home--dash">{{ $dashboard }}</div>
                @else
                <div class="dl-home">
                    <div class="dl-home__hero"></div>
                    <h1>Selamat datang, {{ \Illuminate\Support\Str::before($user['name'], ' ') }} 👋</h1>
                    <p>{!! $intro !!} Setiap form/grid terbuka sebagai <b>tab</b> di frame ini dan tetap terbuka meski Anda berpindah modul.</p>
                    {{ $slot }}
                    <div class="dl-home__hint">{!! $hint !!}</div>
                </div>
                @endisset
            </div>
        </div>
