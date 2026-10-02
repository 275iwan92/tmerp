<div class="tm-act" x-init="$watch('activityOpen', v => v && $wire.load())">
    @if (! $loaded)
        <div class="tm-cfg-hint">{{ t('Loading…') }}</div>
    @else
        <div>
            <h4>{{ t('Recent documents') }}</h4>
            @forelse ($this->documents() as $d)
                <div class="tm-act-row" wire:key="act-doc-{{ $loop->index }}">
                    <span class="ic"><i class="fa-solid {{ $d['action'] === 'Diubah' ? 'fa-pen' : 'fa-file-circle-plus' }}"></i></span>
                    <div class="main"><b>{{ t($d['type']) }} · {{ $d['no'] }}</b><span>{{ $d['action'] === 'Diubah' ? t('Changed') : t('Created') }}</span></div>
                    <time>{{ \Illuminate\Support\Carbon::parse($d['at'])->format('d/m/Y H:i') }}</time>
                </div>
            @empty
                <div class="tm-cfg-hint">{{ t('You have not created or changed any document yet.') }}</div>
            @endforelse
        </div>
        <div>
            <h4>{{ t('Login history') }}</h4>
            @forelse ($this->logins() as $l)
                <div class="tm-act-row" wire:key="act-login-{{ $l->id_attempt }}">
                    <span class="ic {{ $l->success ? '' : 'bad' }}"><i class="fa-solid {{ $l->success ? 'fa-right-to-bracket' : 'fa-triangle-exclamation' }}"></i></span>
                    <div class="main"><b>{{ $l->success ? t('Login succeeded') : t('Login failed') }}</b><span>{{ $l->ip_address }} · {{ \Illuminate\Support\Str::limit($l->user_agent, 60) }}</span></div>
                    <time>{{ $l->attempted_at?->format('d/m/Y H:i') ?? $l->attempted_at }}</time>
                </div>
            @empty
                <div class="tm-cfg-hint">{{ t('No login history yet.') }}</div>
            @endforelse
        </div>
    @endif
</div>
