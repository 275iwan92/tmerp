    <div class="dl-backdrop" data-dl-backdrop></div>

    @if ($chatDrawer)
    <aside class="dl-drawer" data-dl-drawer="chat" data-dl="drawer chat" data-dl-class="bagian Shell" data-dl-src="sys_chat_conversations / sys_chat_messages + sys_user_presence">
        <div class="dl-dr-head"><i class="fa-solid fa-comment-dots dl-dr-ico"></i><span class="dl-dr-title">Chat</span><button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <x-dl::chat compact :me="$chat['me'] ?? $user" :people="$chat['people'] ?? []" :convos="$chat['convos'] ?? []" :api="$chat['api'] ?? []" />

    </aside>
    @endif

    {{-- Asisten: prop assistant terisi = chat pribadi user dengan asisten aplikasi (jawaban dari server); kosong = pratinjau. --}}
    @php $assistantLive = ! empty($assistant['api']['ask']); @endphp
    <aside class="dl-drawer" data-dl-drawer="ai" data-dl="drawer asisten AI" data-dl-class="bagian Shell" data-dl-src="{{ $assistantLive ? 'sys_assistant_message + sys_assistant_metric, sys_help_topic, sys_app_menu' : 'registry menu (pratinjau)' }}"
        @if ($assistantLive) data-dl-assistant="{{ json_encode($assistant) }}" @endif>
        <div class="dl-dr-head"><i class="fa-solid fa-wand-magic-sparkles dl-dr-ico dl-dr-ico--ai"></i><span class="dl-dr-title">Asisten AI</span>@unless ($assistantLive)<span class="dl-dr-note">pratinjau</span>@endunless<button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="dl-ai" data-dl-ai-body>
            @if ($assistantLive)
            <div class="dl-ai__intro">{{ $assistant['hello'] ?? '' }}
                @if (! empty($assistant['chips']))<div class="dl-ai__chips">@foreach ($assistant['chips'] as $chip)<button type="button" class="dl-ai__chip" data-dl-ai="{{ $chip }}">{{ $chip }}</button>@endforeach</div>@endif
            </div>
            @else
            <div class="dl-ai__intro">Tanya dengan bahasa biasa — mis. <b>“buka login”</b> atau <b>“grid”</b>, saya bantu bukakan di tab. Versi ini masih pratinjau.
                <div class="dl-ai__chips"><button type="button" class="dl-ai__chip" data-dl-ai="login">login</button><button type="button" class="dl-ai__chip" data-dl-ai="kanban">kanban</button><button type="button" class="dl-ai__chip" data-dl-ai="grid">grid</button></div>
            </div>
            @endif
        </div>
        <div class="dl-ai__in"><div class="dl-ai__box"><input data-dl-ai-input maxlength="500" placeholder="{{ $assistant['placeholder'] ?? 'Ketik permintaan…' }}" aria-label="{{ \Devlib\Lang::text('Message') }}"><button type="button" class="dl-field__trigger dl-case" data-dl-ai-case title="{{ \Devlib\Lang::text('Change case: lower / Title Case / UPPER') }}">aa</button></div><button type="button" data-dl-ai-send aria-label="{{ \Devlib\Lang::text('Send') }}"><i class="fa-solid fa-arrow-up"></i></button></div>
    </aside>

    {{-- Menu akun: prop account terisi = isi dari server dan Save benar-benar menyimpan (shell.js initAccount); kosong = pratinjau. --}}
    @php
        $notifLive = isset($account['notifications']);
        $profileLive = ! empty($account['profile']['url']);
        $passLive = ! empty($account['password']['url']);
        $dot = ['danger' => '--dl-danger', 'warn' => '--dl-amber-2', 'success' => '--dl-green'];
    @endphp
    <aside class="dl-drawer" data-dl-drawer="notif" data-dl="drawer notifikasi" data-dl-class="bagian Shell" data-dl-src="{{ $notifLive ? 'sys_notifications' : 'sys_notifications (rencana)' }}"
        @if (! empty($account)) data-dl-account="{{ json_encode(['csrf' => $account['csrf'] ?? '', 'read' => $account['notifications']['read'] ?? null, 'profile' => $account['profile']['url'] ?? null, 'password' => $account['password']['url'] ?? null]) }}" @endif>
        <div class="dl-dr-head"><i class="fa-solid fa-bell dl-dr-ico"></i><span class="dl-dr-title">Notifikasi</span><button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="dl-notif__bar"><a data-dl-markread>Tandai semua terbaca</a></div>
        <div class="dl-notif__list">
            @if ($notifLive)
                @forelse ($account['notifications']['items'] as $n)
                    <div class="dl-nz" data-dl-notif="{{ $n['id'] }}" data-title="{{ $n['title'] }}" @if (! empty($n['url'])) data-url="{{ $n['url'] }}" @endif><span class="dl-nz__dot{{ ! empty($n['read']) ? ' is-read' : '' }}" style="--dot:var({{ $dot[$n['tone'] ?? ''] ?? '--dl-accent' }})"></span><div><div class="dl-nz__t">{{ $n['title'] }}</div><div class="dl-nz__s">{{ $n['sub'] ?? '' }}</div></div></div>
                @empty
                    <div class="dl-empty"><p class="dl-empty__msg">{{ \Devlib\Lang::text('No notifications') }}</p></div>
                @endforelse
            @else
            <div class="dl-nz"><span class="dl-nz__dot" style="--dot:var(--dl-accent)"></span><div><div class="dl-nz__t">PO baru menunggu approval</div><div class="dl-nz__s">Procurement · 5 menit lalu</div></div></div>
            <div class="dl-nz"><span class="dl-nz__dot" style="--dot:var(--dl-amber-2)"></span><div><div class="dl-nz__t">Stok Bahan A di bawah minimum</div><div class="dl-nz__s">Inventory · 1 jam lalu</div></div></div>
            <div class="dl-nz"><span class="dl-nz__dot" style="--dot:var(--dl-green)"></span><div><div class="dl-nz__t">Invoice #2600393 lunas</div><div class="dl-nz__s">Finance · kemarin</div></div></div>
            @endif
        </div>
    </aside>

    <div class="dl-mov" data-dl-modal="edit"><div class="dl-modal" data-dl="modal edit profil" data-dl-class="bagian Shell" data-dl-src="sec_user">
        <div class="dl-modal__head"><h3>Edit profil</h3><button type="button" class="dl-modal__x" data-dl-mclose>&times;</button></div>
        <div class="dl-modal__body">
            @unless ($profileLive)
            <div class="dl-photo-row"><span class="dl-photo-big"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span><button type="button" class="dl-ghost">Ubah foto</button></div>
            @endunless
            <div class="dl-modal__msg" data-dl-account-msg hidden></div>
            <div class="dl-mfield"><label>Nama lengkap</label><input name="name" maxlength="255" value="{{ $user['name'] }}"></div>
            <div class="dl-mfield"><label>Email</label><input name="email" type="email" maxlength="255" value="{{ $user['email'] }}"></div>
            <div class="dl-mfield"><label>No. telepon</label><input name="phone" maxlength="64" value="{{ $profileLive ? ($account['profile']['phone'] ?? '') : '0812-3456-7890' }}"></div>
            <div class="dl-mfield"><label>Jabatan</label><input value="{{ $profileLive ? ($account['profile']['role'] ?? '') : $user['role'] }}" readonly></div>
        </div>
        <div class="dl-modal__foot"><button type="button" class="dl-mbtn dl-mbtn--c" data-dl-mclose>Cancel</button><button type="button" class="dl-mbtn dl-mbtn--p" @if ($profileLive) data-dl-account-save="profile" @else data-dl-mclose @endif>Save</button></div>
    </div></div>

    <div class="dl-mov" data-dl-modal="pass"><div class="dl-modal" data-dl="modal ubah password" data-dl-class="bagian Shell" data-dl-src="sec_user.pswd">
        <div class="dl-modal__head"><h3>Ubah password</h3><button type="button" class="dl-modal__x" data-dl-mclose>&times;</button></div>
        <div class="dl-modal__body">
            <div class="dl-modal__msg" data-dl-account-msg hidden></div>
            <div class="dl-mfield"><label>Password lama</label><input type="password" name="old_password" autocomplete="current-password" placeholder="{{ \Devlib\Lang::text('Current password') }}"></div>
            <div class="dl-mfield"><label>Password baru</label><input type="password" name="new_password" autocomplete="new-password" placeholder="Min. 8 karakter"></div>
            <div class="dl-mfield"><label>Konfirmasi password baru</label><input type="password" name="new_password_confirmation" autocomplete="new-password" placeholder="Ulangi password"></div>
        </div>
        <div class="dl-modal__foot"><button type="button" class="dl-mbtn dl-mbtn--c" data-dl-mclose>Cancel</button><button type="button" class="dl-mbtn dl-mbtn--p" @if ($passLive) data-dl-account-save="password" @else data-dl-mclose @endif>Save</button></div>
    </div></div>

    <div class="dl-pal-ov" data-dl-palette data-dl="palet perintah Ctrl+K" data-dl-class="bagian Shell" data-dl-src="{{ $menuSource }} (semua item)">
        <div class="dl-pal">
            <div class="dl-pal__in"><span>🔎</span><input data-dl-pal-input placeholder="{{ \Devlib\Lang::text('Type a form, grid or report name…') }}" autocomplete="off"><button type="button" class="dl-pal__x" data-dl-pal-close title="{{ \Devlib\Lang::text('Close') }}" aria-label="{{ \Devlib\Lang::text('Close') }}"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="dl-pal__list" data-dl-pal-list></div>
            <div class="dl-pal__foot"><span><kbd>↑</kbd><kbd>↓</kbd> pilih</span><span><kbd>↵</kbd> buka</span><span><kbd>esc</kbd> tutup</span></div>
        </div>
    </div>
