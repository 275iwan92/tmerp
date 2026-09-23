<div class="dl-shell" {!! $dl() !!} data-dl-modules="{{ json_encode($modules) }}" data-dl-user="{{ json_encode($user) }}" data-dl-developer="{{ $developer ? '1' : '0' }}">
    <aside class="dl-side" data-dl="sidebar kontekstual" data-dl-class="bagian Shell" data-dl-src="{{ $menuSource }} (grup + item modul aktif)">
        <div class="dl-side__head">
            <span class="dl-logo-badge dl-logo-badge--sm"></span>
            <span class="dl-side__logo"><b>{{ $brand }}</b><br><span>{{ $tagline }}</span></span>
            <button type="button" class="dl-side__collapse" data-dl-act="mini" title="Ciutkan sidebar (mini)">⊟</button>
        </div>
        <div class="dl-side__title" data-dl-mod-title></div>
        <div class="dl-side__nav" data-dl-side-nav></div>
    </aside>

    <div class="dl-main">
        <header class="dl-topbar" data-dl="top bar + mega menu modul" data-dl-class="bagian Shell" data-dl-src="{{ $menuSource }} (menu_type = module)">
            <button type="button" class="dl-tb-btn" data-dl-act="nosidebar" title="Sembunyikan sidebar">☰</button>
            <nav class="dl-mega" data-dl-mega></nav>
            <div class="dl-tb-spacer"></div>
            <button type="button" class="dl-tb-btn" data-dl-act="palette" title="Cari / perintah (Ctrl K)"><i class="fa-solid fa-magnifying-glass"></i></button>
            <button type="button" class="dl-tb-btn" data-dl-act="notopbar" title="Sembunyikan top bar"><i class="fa-solid fa-chevron-up"></i></button>
            <div class="dl-pop">
                <button type="button" class="dl-avatar-btn" data-dl-act="usermenu" title="{{ $user['name'] }}">
                    <span class="dl-avatar"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg><span class="dl-av-dot" data-dl-avdot></span></span>
                    <i class="fa-solid fa-chevron-down dl-avatar-btn__caret"></i>
                </button>
                <div class="dl-pop-menu" data-dl-usermenu data-dl="menu avatar" data-dl-class="bagian Shell" data-dl-src="sys_user_menu (rencana)">
                    <div class="dl-um-head">
                        <span class="dl-um-av"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                        <div><div class="dl-um-name">{{ $user['name'] }}</div><div class="dl-um-mail">{{ $user['email'] }}</div><span class="dl-um-role">{{ $user['role'] }}</span></div>
                    </div>
                    <div class="dl-um-list">
                        <div class="dl-um-cap">Pesan &amp; info</div>
                        <button type="button" class="dl-um-item" data-dl-um="chat"><i class="fa-solid fa-comment-dots"></i> Chat<span class="dl-um-count" data-dl-count="chat">2</span></button>
                        <button type="button" class="dl-um-item" data-dl-um="notif"><i class="fa-solid fa-bell"></i> Notifikasi<span class="dl-um-count" data-dl-count="notif">3</span></button>
                        <button type="button" class="dl-um-item" data-dl-um="ai"><i class="fa-solid fa-wand-magic-sparkles"></i> Asisten AI</button>
                        <div class="dl-um-sep"></div>
                        <div class="dl-um-cap">Akun</div>
                        <button type="button" class="dl-um-item" data-dl-um="edit"><i class="fa-solid fa-user-pen"></i> Edit profil</button>
                        <button type="button" class="dl-um-item" data-dl-um="pass"><i class="fa-solid fa-key"></i> Ubah password</button>
                        <button type="button" class="dl-um-item" data-dl-um="help"><i class="fa-solid fa-circle-question"></i> Bantuan &amp; dukungan</button>
                        <div class="dl-um-item dl-um-item--static"><i class="fa-solid fa-globe"></i> Bahasa<span class="dl-um-langs"><button type="button" class="dl-um-flag is-active" data-dl-lang="id">🇮🇩</button><button type="button" class="dl-um-flag" data-dl-lang="en">🇬🇧</button></span></div>
                        @if ($developer)
                            <div class="dl-um-sep"></div>
                            <div class="dl-um-cap">Developer</div>
                            <button type="button" class="dl-um-item" data-dl-um="devmode"><i class="fa-solid fa-bug"></i> Mode Developer<span class="dl-um-switch" data-dl-devswitch></span></button>
                        @endif
                        <div class="dl-um-sep"></div>
                        <form method="POST" action="{{ $logoutAction }}">
                            @csrf
                            <button type="submit" class="dl-um-item dl-um-item--danger"><i class="fa-solid fa-right-from-bracket"></i> Keluar aplikasi</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="dl-topbar-restore"><button type="button" data-dl-act="showtopbar">▾ Tampilkan top bar</button></div>
        <div class="dl-tabbar" data-dl-tabbar data-dl="tab MDI" data-dl-class="bagian Shell" data-dl-src="—"></div>
        <div class="dl-content" data-dl-content>
            <div class="dl-pane is-active" data-dl-home>
                <div class="dl-home">
                    <div class="dl-home__hero"></div>
                    <h1>Selamat datang, {{ \Illuminate\Support\Str::before($user['name'], ' ') }} 👋</h1>
                    <p>Pilih modul di <b>mega menu</b> atas — submenunya muncul di sidebar. Setiap form/grid terbuka sebagai <b>tab</b> di frame ini dan tetap terbuka meski Anda berpindah modul.</p>
                    {{ $slot }}
                    <div class="dl-home__hint">Ctrl+K cari cepat · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> sembunyikan sidebar · <kbd>⤒</kbd> sembunyikan top bar.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="dl-backdrop" data-dl-backdrop></div>

    <aside class="dl-drawer" data-dl-drawer="chat" data-dl="drawer chat" data-dl-class="bagian Shell" data-dl-src="sys_chat_conversations / sys_chat_messages (rencana)">
        <div class="dl-dr-head"><i class="fa-solid fa-comment-dots dl-dr-ico"></i><span class="dl-dr-title">Chat</span><button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="dl-chat">
            <div class="dl-chat__list" data-dl-chat-list></div>
            <div class="dl-chat__main">
                <div class="dl-chat__msgs" data-dl-chat-msgs></div>
                <div class="dl-chat__in"><input data-dl-chat-input placeholder="Tulis pesan…"><button type="button" data-dl-chat-send><i class="fa-solid fa-paper-plane"></i></button></div>
            </div>
        </div>
    </aside>

    <aside class="dl-drawer" data-dl-drawer="ai" data-dl="drawer asisten AI" data-dl-class="bagian Shell" data-dl-src="registry menu (pratinjau)">
        <div class="dl-dr-head"><i class="fa-solid fa-wand-magic-sparkles dl-dr-ico dl-dr-ico--ai"></i><span class="dl-dr-title">Asisten AI</span><span class="dl-dr-note">pratinjau</span><button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="dl-ai" data-dl-ai-body>
            <div class="dl-ai__intro">Tanya dengan bahasa biasa — mis. <b>“buka login”</b> atau <b>“grid”</b>, saya bantu bukakan di tab. Versi ini masih pratinjau.
                <div class="dl-ai__chips"><button type="button" class="dl-ai__chip" data-dl-ai="login">login</button><button type="button" class="dl-ai__chip" data-dl-ai="kanban">kanban</button><button type="button" class="dl-ai__chip" data-dl-ai="grid">grid</button></div>
            </div>
        </div>
        <div class="dl-ai__in"><input data-dl-ai-input placeholder="Ketik permintaan…"><button type="button" data-dl-ai-send><i class="fa-solid fa-arrow-up"></i></button></div>
    </aside>

    <aside class="dl-drawer" data-dl-drawer="notif" data-dl="drawer notifikasi" data-dl-class="bagian Shell" data-dl-src="sys_notifications (rencana)">
        <div class="dl-dr-head"><i class="fa-solid fa-bell dl-dr-ico"></i><span class="dl-dr-title">Notifikasi</span><button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="dl-notif__bar"><a data-dl-markread>Tandai semua terbaca</a></div>
        <div class="dl-notif__list">
            <div class="dl-nz"><span class="dl-nz__dot" style="--dot:var(--dl-accent)"></span><div><div class="dl-nz__t">PO baru menunggu approval</div><div class="dl-nz__s">Procurement · 5 menit lalu</div></div></div>
            <div class="dl-nz"><span class="dl-nz__dot" style="--dot:var(--dl-amber-2)"></span><div><div class="dl-nz__t">Stok Bahan A di bawah minimum</div><div class="dl-nz__s">Inventory · 1 jam lalu</div></div></div>
            <div class="dl-nz"><span class="dl-nz__dot" style="--dot:var(--dl-green)"></span><div><div class="dl-nz__t">Invoice #2600393 lunas</div><div class="dl-nz__s">Finance · kemarin</div></div></div>
        </div>
    </aside>

    <div class="dl-mov" data-dl-modal="edit"><div class="dl-modal" data-dl="modal edit profil" data-dl-class="bagian Shell" data-dl-src="sec_user">
        <div class="dl-modal__head"><h3>Edit profil</h3><button type="button" class="dl-modal__x" data-dl-mclose>&times;</button></div>
        <div class="dl-modal__body">
            <div class="dl-photo-row"><span class="dl-photo-big"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span><button type="button" class="dl-ghost">Ubah foto</button></div>
            <div class="dl-mfield"><label>Nama lengkap</label><input value="{{ $user['name'] }}"></div>
            <div class="dl-mfield"><label>Email</label><input value="{{ $user['email'] }}"></div>
            <div class="dl-mfield"><label>No. telepon</label><input value="0812-3456-7890"></div>
            <div class="dl-mfield"><label>Jabatan</label><input value="{{ $user['role'] }}" readonly></div>
        </div>
        <div class="dl-modal__foot"><button type="button" class="dl-mbtn dl-mbtn--c" data-dl-mclose>Batal</button><button type="button" class="dl-mbtn dl-mbtn--p" data-dl-mclose>Simpan</button></div>
    </div></div>

    <div class="dl-mov" data-dl-modal="pass"><div class="dl-modal" data-dl="modal ubah password" data-dl-class="bagian Shell" data-dl-src="sec_user.pswd">
        <div class="dl-modal__head"><h3>Ubah password</h3><button type="button" class="dl-modal__x" data-dl-mclose>&times;</button></div>
        <div class="dl-modal__body">
            <div class="dl-mfield"><label>Password lama</label><input type="password" placeholder="Password saat ini"></div>
            <div class="dl-mfield"><label>Password baru</label><input type="password" placeholder="Min. 8 karakter"></div>
            <div class="dl-mfield"><label>Konfirmasi password baru</label><input type="password" placeholder="Ulangi password"></div>
        </div>
        <div class="dl-modal__foot"><button type="button" class="dl-mbtn dl-mbtn--c" data-dl-mclose>Batal</button><button type="button" class="dl-mbtn dl-mbtn--p" data-dl-mclose>Simpan</button></div>
    </div></div>

    <div class="dl-pal-ov" data-dl-palette data-dl="palet perintah Ctrl+K" data-dl-class="bagian Shell" data-dl-src="{{ $menuSource }} (semua item)">
        <div class="dl-pal">
            <div class="dl-pal__in"><span>🔎</span><input data-dl-pal-input placeholder="Ketik nama form, grid, atau laporan…" autocomplete="off"></div>
            <div class="dl-pal__list" data-dl-pal-list></div>
            <div class="dl-pal__foot"><span><kbd>↑</kbd><kbd>↓</kbd> pilih</span><span><kbd>↵</kbd> buka</span><span><kbd>esc</kbd> tutup</span></div>
        </div>
    </div>
</div>
