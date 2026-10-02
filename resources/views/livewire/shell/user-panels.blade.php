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
    <x-ui.drawer open="notifOpen" icon="fa-solid fa-bell" :title="t('Notifications')">
        <div style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--sh-border)">
            <a wire:click="markAllNotifRead" style="font-size:12px;color:var(--sh-accent);cursor:pointer;font-weight:600">{{ t('Mark all as read') }}</a>
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
                <div class="side-hint" style="color:var(--sh-muted)">{{ t('No notifications yet.') }}</div>
            @endforelse
        </div>
        <div style="padding:8px 12px;text-align:center;border-top:1px solid var(--sh-border)">
            @if ($standalone)
                <a href="#" @click.prevent="notifOpen = false; pageTitle = @js(t('Notifications')); pageUrl = @js(route('notifications.index'))" style="font-size:12px;color:var(--sh-accent);font-weight:600;text-decoration:none">{{ t('View all notifications →') }}</a>
            @else
                <a href="{{ route('notifications.index') }}" style="font-size:12px;color:var(--sh-accent);font-weight:600;text-decoration:none">{{ t('View all notifications →') }}</a>
            @endif
        </div>
    </x-ui.drawer>

    {{-- Drawer Chat (sys_chat_* — real) --}}
    <x-ui.drawer open="chatOpen" icon="fa-solid fa-comment-dots" :title="t('Chat')">
        <div class="chat-body">
            <div class="chat-list">
                @forelse ($this->conversations as $c)
                    <div class="cl-item {{ $activeConversationId === $c->id ? 'active' : '' }}" wire:click="selectConversation({{ $c->id }})">
                        <b>{{ $c->title }}</b>
                        <span>{{ \Illuminate\Support\Str::limit($c->last ?? '—', 24) }}</span>
                    </div>
                @empty
                    <div class="side-hint" style="color:var(--sh-muted);padding:12px">{{ t('No conversations yet.') }}</div>
                @endforelse
            </div>
            <div class="chat-main">
                <div class="msgs">
                    @forelse ($this->activeMessages as $m)
                        <div class="msg {{ $m->sender === auth()->user()->login ? 'me' : 'them' }}">{{ $m->body }}</div>
                    @empty
                        <div class="side-hint" style="color:var(--sh-muted)">{{ $activeConversationId ? t('No messages yet.') : t('Select a conversation on the left.') }}</div>
                    @endforelse
                </div>
                <form wire:submit="sendMessage" class="chat-in">
                    <input type="text" wire:model="chatInput" data-dirty-ignore placeholder="{{ t('Write a message…') }}" @if(! $activeConversationId) disabled @endif>
                    <button type="submit" @if(! $activeConversationId) disabled @endif><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </x-ui.drawer>

    {{-- Drawer Asisten AI (pratinjau, client-side -- askAi() milik shell pembungkus) --}}
    <x-ui.drawer open="aiOpen" icon="fa-solid fa-wand-magic-sparkles" :title="t('AI Assistant')" :note="t('preview')">
        <div class="ai-body" x-ref="aiBody">
            @if ($standalone)
                <div class="ai-intro">{{ t('Ask in plain words, e.g. “sales” or “warehouse”, and I will open its report dashboard. This version is still a preview (keyword matching, not a real LLM).') }}
                    <div style="margin-top:10px">
                        <button type="button" class="ai-chip" @click="askAi('sales')">{{ t('sales') }}</button>
                        <button type="button" class="ai-chip" @click="askAi('purchasing')">{{ t('purchasing') }}</button>
                    </div>
                </div>
            @else
                <div class="ai-intro">{!! t('Ask in plain language — e.g. <b>“open inquiry”</b> or <b>“users”</b>, and I’ll open the form/grid for you in a tab. This version is still a preview (keyword matching, not a real LLM).') !!}
                    <div style="margin-top:10px">
                        <button type="button" class="ai-chip" @click="askAi('buka inquiry')">{{ t('open inquiry') }}</button>
                        <button type="button" class="ai-chip" @click="askAi('pengguna')">{{ t('users') }}</button>
                    </div>
                </div>
            @endif
            <template x-for="(m, i) in aiMessages" :key="i">
                <div class="ai-msg" :class="m.role" x-text="m.text"></div>
            </template>
        </div>
        <div class="ai-in">
            <input type="text" x-model="aiInput" data-dirty-ignore placeholder="{{ t('Type a request…') }}" @keydown.enter="askAi(aiInput); aiInput = ''">
            <button type="button" @click="askAi(aiInput); aiInput = ''"><i class="fa-solid fa-arrow-up"></i></button>
        </div>
    </x-ui.drawer>

    {{-- Drawer Edit profil --}}
    <x-ui.drawer open="profileOpen" icon="fa-solid fa-user-pen" :title="t('Edit profile')">
        <div class="mb">
            <div class="photo-row">
                <span class="photo-big"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                <button type="button" class="ghost" disabled title="{{ t('Not available yet') }}">{{ t('Change photo') }}</button>
            </div>
            @if ($profileSaved)
                <div class="mfield"><div class="hint ok">✅ {{ t('Profile saved.') }}</div></div>
            @endif
            <x-form.input legacy="mfield" label="{{ t('Full name') }}" wire:model="profile_name" name="profile_name" />
            <x-form.input legacy="mfield" label="{{ t('Email') }}" type="email" wire:model="profile_email" name="profile_email" />
            <x-form.input legacy="mfield" label="{{ t('Phone number') }}" wire:model="profile_phone" />
            <x-form.input legacy="mfield" label="{{ t('Role') }}" value="{{ $this->roleLabel() }}" readonly />
        </div>
        <div class="mf">
            <button type="button" class="mbtn c" @click="profileOpen = false">{{ t('Cancel') }}</button>
            <button type="button" class="mbtn p" wire:click="saveProfile" wire:loading.attr="disabled">{{ t('Save') }}</button>
        </div>
    </x-ui.drawer>

    {{-- Drawer Ubah password --}}
    <x-ui.drawer open="passwordOpen" icon="fa-solid fa-key" :title="t('Change password')">
        <div class="mb">
            @if ($passwordSaved)
                <div class="mfield"><div class="hint ok">✅ {{ t('Password updated.') }}</div></div>
            @endif
            @if ($passwordError)
                <div class="mfield"><div class="hint bad">{{ $passwordError }}</div></div>
            @endif
            <x-form.input legacy="mfield" label="{{ t('Current password') }}" type="password" wire:model="old_password" placeholder="{{ t('Current password') }}" />
            <x-form.input legacy="mfield" label="{{ t('New password') }}" type="password" wire:model="new_password" placeholder="{{ t('Minimum 8 characters') }}" name="new_password" />
            <x-form.input legacy="mfield" label="{{ t('Confirm new password') }}" type="password" wire:model="new_password_confirmation" placeholder="{{ t('Repeat new password') }}" />
        </div>
        <div class="mf">
            <button type="button" class="mbtn c" @click="passwordOpen = false">{{ t('Cancel') }}</button>
            <button type="button" class="mbtn p" wire:click="savePassword" wire:loading.attr="disabled">{{ t('Save password') }}</button>
        </div>
    </x-ui.drawer>

    {{-- Drawer "User Configuration" (portal self-service karyawan) -- iframe
         dimuat saat pertama dibuka, tidak dikosongkan saat ditutup. --}}
    @if (auth()->user()->employee_id)
        <x-ui.drawer open="portalOpen" icon="fa-solid fa-id-card" :title="t('User Configuration')" size="2xl">
            <iframe class="dr-frame" x-data="{ src: '' }" x-effect="if (portalOpen && ! src) src = @js(route('self-service.index'))" :src="src" title="{{ t('User Configuration') }}"></iframe>
        </x-ui.drawer>
    @endif

    {{-- Drawer My Security --}}
    @if (auth()->user()->employee_id)
        <x-ui.drawer open="securityOpen" icon="fa-solid fa-shield-halved" :title="t('My Security')" size="2xl">
            <iframe class="dr-frame" x-data="{ src: '' }" x-effect="if (securityOpen && ! src) src = @js(route('self-service.index', ['tab' => 'security']))" :src="src" title="{{ t('My Security') }}"></iframe>
        </x-ui.drawer>
    @endif

    {{-- Drawer Bantuan & Support -- konteks layar (helpCtx) dikirim F1 atau klik menu. --}}
    <x-ui.drawer open="helpOpen" icon="fa-solid fa-circle-question" :title="t('Help & Support')" size="2xl">
        <iframe class="dr-frame" x-data="{ src: '', ctx: null }"
            x-effect="if (helpOpen && (! src || ctx !== helpCtx)) { ctx = helpCtx; src = @js(route('help.index')) + '?ctx=' + encodeURIComponent(helpCtx || 'Home'); }"
            :src="src" title="{{ t('Help & Support') }}"></iframe>
    </x-ui.drawer>

    {{-- Drawer Configuration (pengaturan milik tiap user) & Aktivitas saya. --}}
    <x-ui.drawer open="configOpen" icon="fa-solid fa-sliders" :title="t('Configuration')" size="xl">
        <livewire:shell.user-configuration />
    </x-ui.drawer>
    <x-ui.drawer open="activityOpen" icon="fa-solid fa-clock-rotate-left" :title="t('My activity')" size="lg">
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
