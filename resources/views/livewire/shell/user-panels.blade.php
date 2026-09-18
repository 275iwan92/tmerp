@php use App\Support\Localization\Translator; @endphp
{{--
    Panel dropdown user -- SATU implementasi utk Menu Operasional & Laporan
    Manajemen (lihat App\Livewire\Shell\UserPanels). Flag buka/tutup +
    aiMessages/aiInput/askAi() datang dari x-data shell yg membungkus.
    $standalone (Laporan Manajemen): tambah modal halaman (pageUrl) utk item
    yg di shell Operasional membuka tab/halaman penuh, supaya user tetap di
    mode Laporan.
--}}
<div class="upanels">
    <div class="backdrop" :class="{ on: chatOpen || notifOpen || aiOpen }" @click="chatOpen = notifOpen = aiOpen = false"></div>

    {{-- Drawer Notifikasi (sys_notifications — real) --}}
    <aside class="drawer" :class="{ on: notifOpen }">
        <div class="dr-head"><i class="fa-solid fa-bell ico"></i><span class="dr-title">{{ Translator::t('shell_notif_title', 'Notifikasi') }}</span><button type="button" class="x" @click="notifOpen = false"><i class="fa-solid fa-xmark"></i></button></div>
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
    </aside>

    {{-- Drawer Chat (sys_chat_* — real) --}}
    <aside class="drawer" :class="{ on: chatOpen }">
        <div class="dr-head"><i class="fa-solid fa-comment-dots ico"></i><span class="dr-title">{{ Translator::t('shell_chat_title', 'Chat') }}</span><button type="button" class="x" @click="chatOpen = false"><i class="fa-solid fa-xmark"></i></button></div>
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
    </aside>

    {{-- Drawer Asisten AI (pratinjau, client-side -- askAi() milik shell pembungkus) --}}
    <aside class="drawer" :class="{ on: aiOpen }">
        <div class="dr-head"><i class="fa-solid fa-wand-magic-sparkles ico" style="color:#7a52d0"></i><span class="dr-title">{{ Translator::t('shell_ai_title', 'Asisten AI') }}</span><span style="font-size:11px;color:var(--sh-muted);margin-left:4px">{{ Translator::t('shell_ai_preview_badge', 'pratinjau') }}</span><button type="button" class="x" @click="aiOpen = false"><i class="fa-solid fa-xmark"></i></button></div>
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
    </aside>

    {{-- Modal Edit profil --}}
    <div class="mov" :class="{ on: profileOpen }"><div class="modal">
        <div class="mh"><h3>{{ Translator::t('shell_profile_modal_title', 'Edit profil') }}</h3><button type="button" class="x" @click="profileOpen = false">&times;</button></div>
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
    </div></div>

    {{-- Modal Ubah password --}}
    <div class="mov" :class="{ on: passwordOpen }"><div class="modal">
        <div class="mh"><h3>{{ Translator::t('shell_password_modal_title', 'Ubah password') }}</h3><button type="button" class="x" @click="passwordOpen = false">&times;</button></div>
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
    </div></div>

    {{-- Modal "User Configuration" -- iframe lazy (:src cuma diisi saat portalOpen). --}}
    @if (auth()->user()->employee_id)
        <div class="mov" :class="{ on: portalOpen }"><div class="modal wide">
            <div class="mh"><h3>User Configuration</h3><button type="button" class="x" @click="portalOpen = false">&times;</button></div>
            <div class="mb">
                <iframe :src="portalOpen ? '{{ route('self-service.index') }}' : ''" title="User Configuration"></iframe>
            </div>
        </div></div>
    @endif

    @if ($standalone)
        <div class="mov" :class="{ on: !!pageUrl }"><div class="modal wide">
            <div class="mh"><h3 x-text="pageTitle"></h3><button type="button" class="x" @click="pageUrl = ''">&times;</button></div>
            <div class="mb">
                <iframe :src="pageUrl" :title="pageTitle"></iframe>
            </div>
        </div></div>
    @endif
</div>
