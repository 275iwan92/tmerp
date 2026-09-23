@php use App\Support\Localization\Translator; @endphp
<div class="tm-act" x-init="$watch('activityWinOpen', v => v && $wire.load())">
    @if (! $loaded)
        <div class="tm-cfg-hint">{{ Translator::t('shared_loading', 'Memuat…') }}</div>
    @else
        <div>
            <h4>{{ Translator::t('shell_act_documents', 'Dokumen terakhir') }}</h4>
            @forelse ($this->documents() as $d)
                <div class="tm-act-row" wire:key="act-doc-{{ $loop->index }}">
                    <span class="ic"><i class="fa-solid {{ $d['action'] === 'Diubah' ? 'fa-pen' : 'fa-file-circle-plus' }}"></i></span>
                    <div class="main"><b>{{ $d['type'] }} · {{ $d['no'] }}</b><span>{{ $d['action'] }}</span></div>
                    <time>{{ \Illuminate\Support\Carbon::parse($d['at'])->format('d/m/Y H:i') }}</time>
                </div>
            @empty
                <div class="tm-cfg-hint">{{ Translator::t('shell_act_documents_empty', 'Belum ada dokumen yang Anda buat atau ubah.') }}</div>
            @endforelse
        </div>
        <div>
            <h4>{{ Translator::t('shell_act_logins', 'Riwayat login') }}</h4>
            @forelse ($this->logins() as $l)
                <div class="tm-act-row" wire:key="act-login-{{ $l->id_attempt }}">
                    <span class="ic {{ $l->success ? '' : 'bad' }}"><i class="fa-solid {{ $l->success ? 'fa-right-to-bracket' : 'fa-triangle-exclamation' }}"></i></span>
                    <div class="main"><b>{{ $l->success ? Translator::t('shell_act_login_ok', 'Login berhasil') : Translator::t('shell_act_login_fail', 'Login gagal') }}</b><span>{{ $l->ip_address }} · {{ \Illuminate\Support\Str::limit($l->user_agent, 60) }}</span></div>
                    <time>{{ $l->attempted_at?->format('d/m/Y H:i') ?? $l->attempted_at }}</time>
                </div>
            @empty
                <div class="tm-cfg-hint">{{ Translator::t('shell_act_logins_empty', 'Belum ada riwayat login.') }}</div>
            @endforelse
        </div>
    @endif
</div>
