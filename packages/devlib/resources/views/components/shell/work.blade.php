        <div class="dl-tabbar" data-dl-tabbar data-dl="tab MDI" data-dl-class="bagian Shell" data-dl-src="—"></div>
        <div class="dl-content" data-dl-content>
            <div class="dl-pane is-active" data-dl-home>
                <div class="dl-home">
                    <div class="dl-home__hero"></div>
                    <h1>{{ \Devlib\Lang::t('welcome') }}, {{ \Illuminate\Support\Str::before($user['name'], ' ') }} 👋</h1>
                    <p>{!! \Devlib\Lang::text($intro) !!} {!! \Devlib\Lang::text('Every form/grid opens as a <b>tab</b> in this frame and stays open when you switch modules.') !!}</p>
                    {{ $slot }}
                    <div class="dl-home__hint">{!! \Devlib\Lang::text($hint) !!}</div>
                </div>
            </div>
        </div>
