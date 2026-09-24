    <div class="dl-backdrop" data-dl-backdrop></div>

    @if ($chatDrawer)
    <aside class="dl-drawer" data-dl-drawer="chat" data-dl="drawer chat" data-dl-class="bagian Shell" data-dl-src="sys_chat_conversations / sys_chat_messages (rencana)">
        <div class="dl-dr-head"><i class="fa-solid fa-comment-dots dl-dr-ico"></i><span class="dl-dr-title">Chat</span><button type="button" class="dl-dr-x" data-dl-close><i class="fa-solid fa-xmark"></i></button></div>
        <x-dl::chat compact :me="$chat['me'] ?? $user" :people="$chat['people'] ?? []" :convos="$chat['convos'] ?? []" />

    </aside>
    @endif

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
