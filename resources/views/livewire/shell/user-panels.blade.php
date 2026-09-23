@php use App\Support\Localization\Translator; @endphp
{{--
    Panel dropdown user -- SATU implementasi utk Menu Operasional & Laporan
    Manajemen (lihat App\Livewire\Shell\UserPanels). SEMUA panel = drawer
    kanan x-ui.drawer (keputusan user 2026-09-23); flag & openDrawer() dari
    profileDrawers() di resources/js/app-shell.js. Flag buka/tutup +
    aiMessages/aiInput/askAi() datang dari x-data shell yg membungkus.
    $standalone (Laporan Manajemen): tambah modal halaman (pageUrl) utk item
    yg di shell Operasional membuka tab/halaman penuh, supaya user tetap di
    mode Laporan.
--}}
<div class="upanels">
    <div class="backdrop" :class="{ on: anyDrawer() }" @click="closeDrawers()" @keydown.escape.window="closeDrawers()"></div>

    {{-- Drawer Notifikasi (sys_notifications — real) --}}
    <x-ui.drawer open="notifOpen" icon="fa-solid fa-bell" :title="Translator::t('shell_notif_title', 'Notifikasi')">
        <div style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--sh-border)">
            <a wire:click="markAllNotifRead" style="font-size:12px;color:var(--sh-accent);cursor:pointer;font-weight:600">{{ Translator::t('shell_notif_mark_all_read', 'Tandai semua terbaca') }}</a>
        </div>
        <div style="flex:1;overflow-y:auto;padding:6px">
            @forelse ($this->notifications as $n)
                <div class="nz {{ ! $n->is_read ? 'unread' : '' }}" wire:click="markNotifRead({{ $n->id }})" style="cursor:pointer">
                    <span class="dotc" style="background: {{ match($n->type) { 'success' => '#22b07d', 'warning' => '#e5a324', 'error' => '#e5484d', default => '#2f6fed' } }}"></span>
                    <div>
                        <div class="nt">{{ $n->title }}</div>
                        <div class="ns">{{ $n->message }} · {{ $n->created_at?->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <div class="side-hint" style="color:var(--sh-muted)">{{ Translator::t('shell_notif_empty', 'Belum ada notifikasi.') }}</div>
            @endforelse
        </div>
        <div style="padding:8px 12px;text-align:center;border-top:1px solid var(--sh-border)">
            @if ($standalone)
                <a href="#" @click.prevent="notifOpen = false; pageTitle = @js(Translator::t('shell_notif_title', 'Notifikasi')); pageUrl = @js(route('notifications.index'))" style="font-size:12px;color:var(--sh-accent);font-weight:600;text-decoration:none">{{ Translator::t('shell_notif_view_all', 'Lihat semua notifikasi →') }}</a>
            @else
                <a href="{{ route('notifications.index') }}" style="font-size:12px;color:var(--sh-accent);font-weight:600;text-decoration:none">{{ Translator::t('shell_notif_view_all', 'Lihat semua notifikasi →') }}</a>
            @endif
        </div>
    </x-ui.drawer>

    {{-- Drawer Chat (sys_chat_* — real) --}}
    <x-ui.drawer open="chatOpen" icon="fa-solid fa-comment-dots" :title="Translator::t('shell_chat_title', 'Chat')">
        <div class="chat-body">
            <div class="chat-list">
                @forelse ($this->conversations as $c)
                    <div class="cl-item {{ $activeConversationId === $c->id ? 'active' : '' }}" wire:click="selectConversation({{ $c->id }})">
                        <b>{{ $c->title }}</b>
                        <span>{{ \Illuminate\Support\Str::limit($c->last ?? '—', 24) }}</span>
                    </div>
                @empty
                    <div class="side-hint" style="color:var(--sh-muted);padding:12px">{{ Translator::t('shell_chat_empty', 'Belum ada percakapan.') }}</div>
                @endforelse
            </div>
            <div class="chat-main">
                <div class="msgs">
                    @forelse ($this->activeMessages as $m)
                        <div class="msg {{ $m->sender === auth()->user()->login ? 'me' : 'them' }}">{{ $m->body }}</div>
                    @empty
                        <div class="side-hint" style="color:var(--sh-muted)">{{ $activeConversationId ? Translator::t('shell_chat_no_messages', 'Belum ada pesan.') : Translator::t('shell_chat_select_conversation', 'Pilih percakapan di sebelah kiri.') }}</div>
                    @endforelse
                </div>
                <form wire:submit="sendMessage" class="chat-in">
                    <input type="text" wire:model="chatInput" data-dirty-ignore placeholder="{{ Translator::t('shell_chat_input_placeholder', 'Tulis pesan…') }}" @if(! $activeConversationId) disabled @endif>
                    <button type="submit" @if(! $activeConversationId) disabled @endif><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </x-ui.drawer>

    {{-- Drawer Asisten AI (pratinjau, client-side -- askAi() milik shell pembungkus) --}}
    <x-ui.drawer open="aiOpen" icon="fa-solid fa-wand-magic-sparkles" :title="Translator::t('shell_ai_title', 'Asisten AI')" :note="Translator::t('shell_ai_preview_badge', 'pratinjau')">
        <div class="ai-body" x-ref="aiBody">
            @if ($standalone)
                <div class="ai-intro">{{ Translator::t('shell_ai_intro_report', 'Tanya dengan bahasa biasa — mis. “sales” atau “gudang”, saya bukakan dashboard laporannya. Versi ini masih pratinjau (pencocokan kata kunci, bukan LLM sungguhan).') }}
                    <div style="margin-top:10px">
                        <button type="button" class="ai-chip" @click="askAi('sales')">sales</button>
                        <button type="button" class="ai-chip" @click="askAi('purchasing')">purchasing</button>
                    </div>
                </div>
            @else
                <div class="ai-intro">{!! Translator::t('shell_ai_intro', 'Tanya dengan bahasa biasa — mis. <b>“buka inquiry”</b> atau <b>“pengguna”</b>, saya bantu bukakan form/grid-nya di tab. Versi ini masih pratinjau (pencocokan kata kunci, bukan LLM sungguhan).') !!}
                    <div style="margin-top:10px">
                        <button type="button" class="ai-chip" @click="askAi('buka inquiry')">{{ Translator::t('shell_ai_chip_inquiry', 'buka inquiry') }}</button>
                        <button type="button" class="ai-chip" @click="askAi('pengguna')">{{ Translator::t('shell_ai_chip_user', 'pengguna') }}</button>
                    </div>
                </div>
            @endif
            <template x-for="(m, i) in aiMessages" :key="i">
                <div class="ai-msg" :class="m.role" x-text="m.text"></div>
            </template>
        </div>
        <div class="ai-in">
            <input type="text" x-model="aiInput" data-dirty-ignore placeholder="{{ Translator::t('shell_ai_input_placeholder', 'Ketik permintaan…') }}" @keydown.enter="askAi(aiInput); aiInput = ''">
            <button type="button" @click="askAi(aiInput); aiInput = ''"><i class="fa-solid fa-arrow-up"></i></button>
        </div>
    </x-ui.drawer>

    {{-- Drawer Edit profil --}}
    <x-ui.drawer open="profileOpen" icon="fa-solid fa-user-pen" :title="Translator::t('shell_profile_modal_title', 'Edit profil')">
        <div class="mb">
            <div class="photo-row">
                <span class="photo-big"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                <button type="button" class="ghost" disabled title="{{ Translator::t('shell_not_available_yet', 'Belum tersedia') }}">{{ Translator::t('shell_profile_change_photo', 'Ubah foto') }}</button>
            </div>
            @if ($profileSaved)
                <div class="mfield"><div class="hint ok">✅ {{ Translator::t('shell_profile_saved', 'Profil tersimpan.') }}</div></div>
            @endif
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_profile_full_name', 'Nama lengkap') }}" wire:model="profile_name" name="profile_name" />
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_profile_email', 'Email') }}" type="email" wire:model="profile_email" name="profile_email" />
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_profile_phone', 'No. telepon') }}" wire:model="profile_phone" />
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_profile_role', 'Jabatan') }}" value="{{ $this->roleLabel() }}" readonly />
        </div>
        <div class="mf">
            <button type="button" class="mbtn c" @click="profileOpen = false">{{ Translator::t('shared_btn_cancel', 'Batal') }}</button>
            <button type="button" class="mbtn p" wire:click="saveProfile" wire:loading.attr="disabled">{{ Translator::t('shell_btn_save', 'Simpan') }}</button>
        </div>
    </x-ui.drawer>

    {{-- Drawer Ubah password --}}
    <x-ui.drawer open="passwordOpen" icon="fa-solid fa-key" :title="Translator::t('shell_password_modal_title', 'Ubah password')">
        <div class="mb">
            @if ($passwordSaved)
                <div class="mfield"><div class="hint ok">✅ {{ Translator::t('shell_password_updated', 'Password diperbarui.') }}</div></div>
            @endif
            @if ($passwordError)
                <div class="mfield"><div class="hint bad">{{ $passwordError }}</div></div>
            @endif
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_password_old_label', 'Password lama') }}" type="password" wire:model="old_password" placeholder="{{ Translator::t('shell_password_old_placeholder', 'Password saat ini') }}" />
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_password_new_label', 'Password baru') }}" type="password" wire:model="new_password" placeholder="{{ Translator::t('shell_password_new_placeholder', 'Minimal 8 karakter') }}" name="new_password" />
            <x-form.input legacy="mfield" label="{{ Translator::t('shell_password_confirm_label', 'Konfirmasi password baru') }}" type="password" wire:model="new_password_confirmation" placeholder="{{ Translator::t('shell_password_confirm_placeholder', 'Ulangi password baru') }}" />
        </div>
        <div class="mf">
            <button type="button" class="mbtn c" @click="passwordOpen = false">{{ Translator::t('shared_btn_cancel', 'Batal') }}</button>
            <button type="button" class="mbtn p" wire:click="savePassword" wire:loading.attr="disabled">{{ Translator::t('shell_btn_save_password', 'Simpan password') }}</button>
        </div>
    </x-ui.drawer>

    {{-- Drawer "User Configuration" (portal self-service karyawan) -- iframe
         dimuat saat pertama dibuka, tidak dikosongkan saat ditutup. --}}
    @if (auth()->user()->employee_id)
        <x-ui.drawer open="portalOpen" icon="fa-solid fa-id-card" title="User Configuration" size="2xl">
            <iframe class="dr-frame" x-data="{ src: '' }" x-effect="if (portalOpen && ! src) src = @js(route('self-service.index'))" :src="src" title="User Configuration"></iframe>
        </x-ui.drawer>
    @endif

    {{-- Drawer My Security --}}
    @if (auth()->user()->employee_id)
        <x-ui.drawer open="securityOpen" icon="fa-solid fa-shield-halved" :title="Translator::t('shell_mysecurity_title', 'My Security')" size="2xl">
            <iframe class="dr-frame" x-data="{ src: '' }" x-effect="if (securityOpen && ! src) src = @js(route('self-service.index', ['tab' => 'security']))" :src="src" title="My Security"></iframe>
        </x-ui.drawer>
    @endif

    {{-- Drawer Bantuan & Support -- konteks layar (helpCtx) dikirim F1 atau klik menu. --}}
    <x-ui.drawer open="helpOpen" icon="fa-solid fa-circle-question" :title="Translator::t('shell_help_title', 'Bantuan & Support')" size="2xl">
        <iframe class="dr-frame" x-data="{ src: '', ctx: null }"
            x-effect="if (helpOpen && (! src || ctx !== helpCtx)) { ctx = helpCtx; src = @js(route('help.index')) + '?ctx=' + encodeURIComponent(helpCtx || 'Home'); }"
            :src="src" title="Bantuan & Support"></iframe>
    </x-ui.drawer>

    {{-- Drawer Configuration (pengaturan milik tiap user) & Aktivitas saya. --}}
    <x-ui.drawer open="configOpen" icon="fa-solid fa-sliders" :title="Translator::t('shell_section_configuration', 'Configuration')" size="xl">
        <livewire:shell.user-configuration />
    </x-ui.drawer>
    <x-ui.drawer open="activityOpen" icon="fa-solid fa-clock-rotate-left" :title="Translator::t('shell_activity_title', 'Aktivitas saya')" size="lg">
        <livewire:shell.user-activity />
    </x-ui.drawer>

    @if ($standalone)
        <div class="mov" :class="{ on: !!pageUrl }"><div class="modal wide">
            <div class="mh"><h3 x-text="pageTitle"></h3><button type="button" class="x" @click="pageUrl = ''">&times;</button></div>
            <div class="mb">
                <iframe :src="pageUrl" :title="pageTitle"></iframe>
            </div>
        </div></div>
    @endif
</div>
