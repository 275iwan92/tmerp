<?php

use App\Models\Sys\SecUser;
use App\Models\Sys\SysAppMenu;
use App\Models\Sys\SysAppMenuColor;
use App\Models\Sys\SysChatConversation;
use App\Models\Sys\SysChatMessage;
use App\Models\Sys\SysChatParticipant;
use App\Models\Sys\SysNotification;
use App\Models\Sys\SysSupportTicket;
use App\Models\Sys\SysSupportTicketAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::shell')] #[Title('TELASIH MAKMUR — ERP')] class extends Component
{
    use WithFileUploads;

    public string $profile_name = '';

    public string $profile_email = '';

    public string $profile_phone = '';

    public bool $profileSaved = false;

    public string $old_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public ?string $passwordError = null;

    public bool $passwordSaved = false;

    public ?int $activeConversationId = null;

    public string $chatInput = '';

    // ---- Help & Support (dropdown profil, dulu placeholder toast) ----
    public string $support_description = '';

    public string $support_context = '';

    public array $support_attachments = [];

    public ?string $supportError = null;

    public ?string $supportSubmittedNo = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->profile_name = $user->name ?? '';
        $this->profile_email = $user->email ?? '';
        $this->profile_phone = $user->phone ?? '';
    }

    // code sys_app_menu -> URL route Laravel nyata. Item yang tak disebut di
    // sini tetap tampil di menu (placeholder pane) — lihat resources/js/app-shell.js.
    protected function itemRoutes(): array
    {
        return [
            'SALES_INQ' => route('inquiry.hub'),
            // Imposition & Product Costing (2026-08-22): masing-masing punya worklist
            // GRID sendiri sekarang (inquiry.imposition.list / inquiry.costing.list) --
            // dulu sempat di-alias ke Inquiry Hub yang sama persis, terlihat identik/
            // membingungkan (3 menu -> 1 layar yang sama). Grid double-click membuka
            // layar per-inquiry yang SUDAH ADA sebelumnya (inquiry.imposition/inquiry.costing),
            // tidak diubah. Costing tetap module:COSTING (Sisi A rahasia, CLAUDE.md §4).
            'SALES_IMPO' => route('inquiry.imposition.list'),
            'SALES_COST' => route('inquiry.costing.list'),
            // Nota TIDAK LAGI menu top-level -- dokumen per-inquiry, reachable lewat
            // tombol "🖨 Cetak Nota" di dalam Inquiry Product (inquiry/form.blade.php toolbar).
            // Quotation SEKARANG per-customer (redesain 2026-08-20, bukan lagi
            // per-inquiry) -> punya list sungguhan, tak perlu numpang Inquiry Hub.
            'SALES_QUOT' => route('quotation.list'),
            'SALES_SO' => route('co.list'),
            'SALES_PLAN' => route('co.delivery-plan'),
            // Delivery Order/Surat Jalan (DO+SJ 1 tabel 2 cetakan) -- sebelumnya
            // modul "Dispatch" berdiri sendiri, digabung ke sini 2026-08-28 (lihat
            // migration move_dispatch_menu_into_sales_delivery).
            'SALES_DO' => route('dispatch.do.index'),
            'PROC_PR' => route('pur.pr.list'),
            'PROC_PR_APPR' => route('pur.pr.approval'),
            'PROC_PO' => route('pur.po.list'),
            'PROC_PO_APPR' => route('pur.po.approval'),
            'PROC_RCV' => route('pur.pv.list'),
            'PROC_PQ' => route('pur.pq.list'),
            'PROC_RET' => route('pur.prt.list'),
            'PROC_RET_APPR' => route('pur.prt.approval'),
            'PROC_MON_PR' => route('pur.report.outstanding-pr'),
            'PROC_MON_PO' => route('pur.report.outstanding-po'),
            'MD_PROD' => route('master.produk'),
            'MD_CUST' => route('master.customer'),
            'MD_SUPP' => route('master.supplier'),
            'MD_RAW' => route('master.raw-material.list'),
            'MD_MATCAT' => route('master.mat-category'),
            'MD_WH' => route('master.warehouse.list'),
            'MD_UOM' => route('master.uom'),
            'MD_MACHINE' => route('master.machine'),
            'MD_SHIFT' => route('master.shift'),
            'PROD_MST_PROCESS' => route('production.master-process'),
            'PROD_MST_CREW' => route('production.master-crew'),
            'ACC_COA' => route('finance.coa'),
            'FIN_DP' => route('finance.dp-invoice'),
            'FIN_CASH' => route('finance.cash-bank'),
            // 2026-08-22: Laporan Inventory dipecah 3 menu per kelas stok
            // (RM/FG/SFG) -- 1 route param'd, 6 menu code beda category.
            'INV_RAW_RM' => route('inv.print.master-stock', ['category' => 'RM']),
            'INV_CARD_RM' => route('inv.print.stock-card', ['category' => 'RM']),
            'INV_SLOW_RM' => route('inv.print.slow-moving', ['category' => 'RM']),
            'INV_RAW_FG' => route('inv.print.master-stock', ['category' => 'FG']),
            'INV_CARD_FG' => route('inv.print.stock-card', ['category' => 'FG']),
            'INV_SLOW_FG' => route('inv.print.slow-moving', ['category' => 'FG']),
            'INV_RAW_SFG' => route('inv.print.master-stock', ['category' => 'SFG']),
            'INV_CARD_SFG' => route('inv.print.stock-card', ['category' => 'SFG']),
            'INV_SLOW_SFG' => route('inv.print.slow-moving', ['category' => 'SFG']),
            // Stock In (item pertama di tiap grup Input -- lihat migration
            // 2026_08_22_000007). Stock Out/Return/Adjustment (ITEM2-5) masih NOT_BUILT.
            'INV_RM_ITEM1' => route('inv.stock-in.index', ['category' => 'RM']),
            'INV_FG_ITEM1' => route('inv.stock-in.index', ['category' => 'FG']),
            'INV_SFG_ITEM1' => route('inv.stock-in.index', ['category' => 'SFG']),
            // Stock Out + Return from Production diperluas ke SFG 2026-08-22
            // (barang setengah jadi yg sempat nunggu di gudang lanjut ke proses
            // berikutnya, dan bisa diretur balik kalau cacat/tak kepakai). FG
            // TETAP ditolak di keduanya -- barang jadi keluar cuma lewat
            // Delivery Order (Dispatch, belum dibangun), bukan Stock Out
            // generik (fg_out_bukan_milik_modul, dikonfirmasi user 2026-08-22).
            'INV_RM_ITEM2' => route('inv.stock-out.index', ['category' => 'RM']),
            'INV_SFG_ITEM2' => route('inv.stock-out.index', ['category' => 'SFG']),
            'INV_RM_ITEM3' => route('inv.stock-return.index', ['category' => 'RM']),
            'INV_SFG_ITEM3' => route('inv.stock-return.index', ['category' => 'SFG']),
            'INV_RM_ITEM4' => route('inv.stock-adj.index', ['category' => 'RM']),
            'INV_FG_ITEM4' => route('inv.stock-adj.index', ['category' => 'FG']),
            'INV_SFG_ITEM4' => route('inv.stock-adj.index', ['category' => 'SFG']),
            'INV_RM_ITEM5' => route('inv.stock-adj.approval', ['category' => 'RM']),
            'INV_FG_ITEM5' => route('inv.stock-adj.approval', ['category' => 'FG']),
            'INV_SFG_ITEM5' => route('inv.stock-adj.approval', ['category' => 'SFG']),
            'PROD_MPS' => route('production.scheduling-board'),
            'PROD_WO_LIST' => route('production.wo.list'),
            // Production Output (F15) belum punya layar sendiri -- catat output
            // per task dari WO Detail (masuk lewat Worklist WO), lihat
            // wo-entry.blade.php "Catat Output". Menu ini cuma pintu masuk.
            'PROD_OUTPUT' => route('production.wo.list'),
            'PROD_SCRAP_TYPE' => route('production.scrap-type'),
            'PROD_PROCESS_RATE' => route('production.process-rate'),
            'PROD_MON_WO' => route('production.monitoring'),
            'PROD_MPS_SHEET' => route('production.mps-spreadsheet'),
            'PROD_RULE_LIB' => route('production.rule-library'),
            'PROD_MTS_FORECAST' => route('production.mts-forecast'),
            'PROD_SHIFT_ROSTER' => route('hrm.shift-roster'),
            'HRM_ORG_STRUCTURE' => route('hrm.position'),
            'HRM_ORG_CHART' => route('hrm.org-chart'),
            'HRM_EMPLOYEE' => route('hrm.employee'),
            'HRM_ROSTER' => route('hrm.shift-roster'),
            'HRM_CORRECTION' => route('hrm.correction-requests'),
            'HRM_SELFSERVICE' => route('self-service.index'),
            'HRM_LEAVE_APPROVAL' => route('hrm.leave-requests'),
            // Recruitment (L1-L9 minus L8 + Portal Candidate simulasi) --
            // lihat docs/recruitment_state.yaml.
            'HRM_RCR_REQUISITION' => route('hrm.recruitment.requisition.list'),
            'HRM_RCR_VACANCY' => route('hrm.recruitment.vacancy'),
            'HRM_RCR_TALENTPOOL' => route('hrm.recruitment.talent-pool'),
            'HRM_RCR_PIPELINE' => route('hrm.recruitment.pipeline-board'),
            'HRM_RCR_INTERVIEW' => route('hrm.recruitment.interview-schedule'),
            'HRM_RCR_ASSESSMENT' => route('hrm.recruitment.assessment-result'),
            'HRM_RCR_OFFER' => route('hrm.recruitment.offer.list'),
            'HRM_RCR_PROVISION' => route('hrm.recruitment.hire-provision'),
            'HRM_RCR_PORTAL' => route('hrm.recruitment.portal'),
            'SET_USER' => route('security.index', ['tab' => 'users']),
            'SET_GROUP' => route('security.index', ['tab' => 'groups']),
            'SET_PERM' => route('security.index', ['tab' => 'perms']),
            'SET_MIGRATION' => route('security.index', ['tab' => 'migration']),
            'SET_TICKETS' => route('setup.support-tickets'),
        ];
    }

    // Pohon menu (module->group->item) dari sys_app_menu, difilter izin lewat
    // SecUser::visibleAppMenuCodes() (-> sec_module). Dikirim ke Alpine sekali
    // via @js() — navigasi tab/sidebar sesudahnya murni client-side.
    #[Computed]
    public function menuTree(): array
    {
        $visible = Auth::user()->visibleAppMenuCodes();

        $modules = SysAppMenu::query()
            ->where('menu_type', 'module')
            ->where('activate', true)
            ->whereIn('code', $visible)
            ->orderBy('sort_order')
            ->get();

        $children = SysAppMenu::query()
            ->whereNotNull('parent_id')
            ->where('activate', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('parent_id');

        $routes = $this->itemRoutes();
        $statusColors = SysAppMenuColor::all()->keyBy('status_code');

        // module_status_system (globalrules.yaml): item bawa status_code sendiri
        // dari DB; module/group DERIVE dari anak-anaknya (worst-case menang) --
        // TIDAK disimpan sendiri, supaya "Sales" langsung kelihatan abu kalau ADA
        // satu submenu yang belum dibangun, tanpa perlu expand satu-satu.
        $deriveStatus = function (array $childStatuses): string {
            if (empty($childStatuses) || in_array('NOT_BUILT', $childStatuses, true)) {
                return 'NOT_BUILT';
            }

            return in_array('READY_FOR_QC', $childStatuses, true) ? 'READY_FOR_QC' : 'LOCKED';
        };

        $build = function (SysAppMenu $node) use (&$build, $children, $routes, $statusColors, $deriveStatus) {
            $builtChildren = ($children->get($node->id) ?? collect())->map($build)->values()->all();

            $statusCode = $node->menu_type === 'item'
                ? ($node->status_code ?: 'NOT_BUILT')
                : $deriveStatus(array_column($builtChildren, 'statusCode'));

            $color = $statusColors->get($statusCode);

            return [
                'id' => $node->id,
                'code' => $node->code,
                'label' => $node->label,
                'icon' => $this->iconFor($node),
                'menuType' => $node->menu_type,
                'objectType' => $node->object_type,
                'url' => $routes[$node->code] ?? null,
                'statusCode' => $statusCode,
                'statusColor' => $color->color_hex ?? '#9ca3af',
                'statusLabel' => $color->label ?? 'Belum Tersedia',
                'isClickable' => (bool) ($color->is_clickable ?? false),
                'children' => $builtChildren,
            ];
        };

        return $modules->map($build)->values()->all();
    }

    // Font Awesome: module pakai icon kolom (sudah FA, lihat migration
    // 2026_08_09_000003). Group tak butuh ikon (hanya label capsule di
    // sidebar). Item tanpa icon sendiri jatuh ke ikon per object_type,
    // mirror typeIcon() di docs/mockups/main_menu_v2.html.
    protected function iconFor(SysAppMenu $node): ?string
    {
        if ($node->menu_type === 'group') {
            return null;
        }

        return $node->icon ?: match ($node->object_type) {
            'grid' => 'fa-solid fa-table-list',
            'report' => 'fa-solid fa-chart-column',
            default => 'fa-solid fa-pen-to-square',
        };
    }

    // Daftar datar item (utk command palette & asisten AI preview).
    #[Computed]
    public function flatItems(): array
    {
        $flat = [];

        $walk = function (array $nodes, string $modLabel) use (&$walk, &$flat) {
            foreach ($nodes as $n) {
                $mod = $n['menuType'] === 'module' ? $n['label'] : $modLabel;

                if ($n['menuType'] === 'item') {
                    $flat[] = [
                        'code' => $n['code'],
                        'label' => $n['label'],
                        'icon' => $n['icon'],
                        'objectType' => $n['objectType'],
                        'url' => $n['url'],
                        'statusCode' => $n['statusCode'],
                        'statusColor' => $n['statusColor'],
                        'statusLabel' => $n['statusLabel'],
                        'isClickable' => $n['isClickable'],
                        'mod' => $mod,
                    ];
                }

                if (! empty($n['children'])) {
                    $walk($n['children'], $mod);
                }
            }
        };

        $walk($this->menuTree, '');

        return $flat;
    }

    public function greetName(): string
    {
        $name = Auth::user()->name ?: 'Pengguna';

        return trim(strtok($name, ' '));
    }

    public function roleLabel(): string
    {
        $user = Auth::user();

        if ($user->is_admin) {
            return 'Administrator';
        }

        $names = $user->groups()->pluck('group_name');

        return $names->isNotEmpty() ? $names->implode(', ') : 'Pengguna';
    }

    public function saveProfile(): void
    {
        $this->validate([
            'profile_name' => ['required', 'string', 'max:255'],
            'profile_email' => ['required', 'email', 'max:255'],
            'profile_phone' => ['nullable', 'string', 'max:64'],
        ]);

        Auth::user()->update([
            'name' => $this->profile_name,
            'email' => $this->profile_email,
            'phone' => $this->profile_phone,
        ]);

        $this->profileSaved = true;
    }

    public function savePassword(): void
    {
        $this->passwordError = null;
        $this->passwordSaved = false;

        $user = Auth::user();

        if (! Hash::check($this->old_password, (string) $user->pswd)) {
            $this->passwordError = 'Password lama salah.';

            return;
        }

        $this->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'pswd' => Hash::make($this->new_password),
            'pswd_last_updated' => now(),
        ]);

        $this->old_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->passwordSaved = true;
    }

    #[Computed]
    public function myTickets()
    {
        return SysSupportTicket::where('created_by_user_id', Auth::id())->orderByDesc('id_ticket')->limit(10)->get();
    }

    public function submitSupportTicket(): void
    {
        $this->supportError = null;

        if (trim($this->support_description) === '') {
            $this->supportError = 'Deskripsi wajib diisi.';

            return;
        }

        $year = now()->format('y');
        $month = now()->format('m');
        $seq = SysSupportTicket::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count() + 1;

        $ticket = SysSupportTicket::create([
            'ticket_no' => "SPT{$year}{$month}".str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'created_by_user_id' => Auth::id(),
            'screen_context' => $this->support_context ?: null,
            'description' => $this->support_description,
            'status' => 'OPEN',
        ]);

        foreach ($this->support_attachments as $file) {
            SysSupportTicketAttachment::create([
                'ticket_id' => $ticket->id_ticket,
                'file_path' => $file->store('support-tickets', 'public'),
                'uploaded_at' => now(),
            ]);
        }

        $this->supportSubmittedNo = $ticket->ticket_no;
        $this->support_description = '';
        $this->support_attachments = [];
        unset($this->myTickets);
    }

    public function resetSupportForm(): void
    {
        $this->supportSubmittedNo = null;
        $this->supportError = null;
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ================= NOTIFIKASI (sys_notifications) =================

    #[Computed]
    public function notifications()
    {
        return SysNotification::forUser(Auth::user()->login)->orderByDesc('created_at')->limit(30)->get();
    }

    #[Computed]
    public function unreadNotifCount(): int
    {
        return SysNotification::forUser(Auth::user()->login)->where('is_read', false)->count();
    }

    public function markNotifRead(int $id)
    {
        $notif = SysNotification::findOrFail($id);
        $notif->update(['is_read' => true]);
        unset($this->notifications, $this->unreadNotifCount);

        if ($notif->link) {
            return $this->redirect($notif->link);
        }
    }

    public function markAllNotifRead(): void
    {
        SysNotification::forUser(Auth::user()->login)->where('is_read', false)->update(['is_read' => true]);
        unset($this->notifications, $this->unreadNotifCount);
    }

    // ================= CHAT (sys_chat_*) =================

    #[Computed]
    public function conversations()
    {
        $login = Auth::user()->login;
        $ids = SysChatParticipant::where('user_id', $login)->pluck('conversation_id');

        return SysChatConversation::query()
            ->whereIn('id', $ids)
            ->with(['messages' => fn ($q) => $q->orderByDesc('sent_at')->limit(1)])
            ->get()
            ->map(function (SysChatConversation $c) use ($login) {
                $title = $c->title;

                if (! $c->is_group) {
                    $other = SysChatParticipant::where('conversation_id', $c->id)->where('user_id', '!=', $login)->first();
                    $title = $other ? (SecUser::where('login', $other->user_id)->value('name') ?? $other->user_id) : 'Percakapan';
                }

                return (object) [
                    'id' => $c->id,
                    'title' => $title ?: 'Percakapan',
                    'last' => $c->messages->first()?->body,
                ];
            })
            ->values();
    }

    #[Computed]
    public function unreadChatCount(): int
    {
        $login = Auth::user()->login;

        return SysChatParticipant::where('user_id', $login)
            ->get()
            ->filter(function (SysChatParticipant $p) {
                $lastMsg = SysChatMessage::where('conversation_id', $p->conversation_id)->max('sent_at');

                return $lastMsg && (! $p->last_read_at || $p->last_read_at->lt($lastMsg));
            })
            ->count();
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;

        SysChatParticipant::where('conversation_id', $id)
            ->where('user_id', Auth::user()->login)
            ->update(['last_read_at' => now()]);

        unset($this->conversations, $this->unreadChatCount);
    }

    #[Computed]
    public function activeMessages()
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        return SysChatMessage::where('conversation_id', $this->activeConversationId)->orderBy('sent_at')->get();
    }

    public function sendMessage(): void
    {
        $body = trim($this->chatInput);

        if ($body === '' || ! $this->activeConversationId) {
            return;
        }

        SysChatMessage::create([
            'conversation_id' => $this->activeConversationId,
            'sender' => Auth::user()->login,
            'body' => $body,
        ]);

        SysChatParticipant::where('conversation_id', $this->activeConversationId)
            ->where('user_id', Auth::user()->login)
            ->update(['last_read_at' => now()]);

        $this->chatInput = '';
        unset($this->activeMessages, $this->conversations);
    }
};
?>

<div class="shell"
     x-data="appShell(@js($this->menuTree), @js($this->flatItems), 'Home')"
     :class="{ mini: mini, nosidebar: nosidebar, notopbar: notopbar }"
     @keydown.ctrl.k.window.prevent="paletteOpen ? closePalette() : openPalette()"
     @keydown.cmd.k.window.prevent="paletteOpen ? closePalette() : openPalette()"
     @keydown.escape.window="closePalette()"
     @click.outside="ddOpen = false">

    <aside>
        <div class="side-head">
            <button type="button" class="logo-home" @click="selectModule('HOME')" title="Kembali ke Home">
                <span class="logo-badge"></span>
                <span class="logo-txt"><b>TELASIH MAKMUR</b><br><span>packaging solution</span></span>
            </button>
            <button type="button" class="side-collapse" @click="mini = !mini" title="Ciutkan sidebar (mini)">⊟</button>
        </div>
        <div class="mod-title">
            <template x-if="currentModule">
                <span class="ic"><i :class="currentModule.icon"></i></span>
            </template>
            <template x-if="!currentModule">
                <span class="ic"><i class="fa-solid fa-house"></i></span>
            </template>
            <span class="lbl" x-text="currentModule ? currentModule.label.toUpperCase() : 'HOME'"></span>
        </div>
        <div class="side-nav">
            <template x-if="activeMod === 'HOME'">
                <div class="side-hint">Modul <b>Home</b>. Pilih modul lain di atas untuk menampilkan submenunya di sini.</div>
            </template>
            <template x-for="grp in sidebarGroups" :key="grp.id">
                <div x-data="{ open: true }">
                    <template x-if="grp.menuType === 'group'">
                        <button type="button" class="side-cap-btn" @click="open = !open" :title="open ? 'Ciutkan' : 'Buka'">
                            <span class="side-cap" x-text="grp.label"></span>
                            <i class="fa-solid fa-chevron-down chev" :class="{ collapsed: !open }"></i>
                        </button>
                    </template>
                    <template x-for="it in (grp.menuType === 'group' ? grp.children : [grp])" :key="it.id">
                        <button type="button" class="leaf" x-show="grp.menuType !== 'group' || open"
                            :class="{ active: activeTabCode === it.code, 'not-built': !it.isClickable }" :title="it.statusLabel" @click="openTab(it.code)">
                            <span class="lic"><i :class="it.icon || 'fa-solid fa-file-lines'"></i></span>
                            <span class="lbl" x-text="it.label"></span>
                            <span class="status-dot" :style="{ background: it.statusColor }"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
        @if (Auth::user()->is_admin)
            {{-- "Security" -- instruksi user 2026-08-30: dipasak PALING BAWAH
            sidebar kiri, HANYA utk is_admin, dan berupa MENU GROUP (bukan 1
            tombol) berisi sub-item Account/Group/Group per Submodule/
            Migration/Support Tickets -- ini pintu masuk ke tab2 yang SUDAH
            ADA di security.index (SET_USER/SET_GROUP/SET_PERM/SET_MIGRATION)
            + setup.support-tickets, cuma dipecah jadi leaf terpisah (dulu
            digabung 1 tombol yg selalu lompat ke tab 'users' saja). Portal
            Karyawan tab Security (profil+password non-admin) TIDAK
            berubah/tidak kena gate ini. --}}
            <div class="side-footer" x-data="{ open: false }">
                <button type="button" class="side-cap-btn" @click="open = !open" :title="open ? 'Ciutkan' : 'Buka'">
                    <span class="side-cap">Security</span>
                    <i class="fa-solid fa-chevron-down chev" :class="{ collapsed: !open }"></i>
                </button>
                <template x-for="it in [
                    { code: 'SET_USER', label: 'Account', icon: 'fa-solid fa-user-gear' },
                    { code: 'SET_GROUP', label: 'Group', icon: 'fa-solid fa-people-group' },
                    { code: 'SET_PERM', label: 'Group per Submodule', icon: 'fa-solid fa-diagram-project' },
                    { code: 'SET_MIGRATION', label: 'Migration', icon: 'fa-solid fa-database' },
                    { code: 'SET_TICKETS', label: 'Support Tickets', icon: 'fa-solid fa-ticket' },
                ]" :key="it.code">
                    <button type="button" class="leaf" x-show="open" :class="{ active: activeTabCode === it.code }" @click="openTab(it.code)">
                        <span class="lic"><i :class="it.icon"></i></span>
                        <span class="lbl" x-text="it.label"></span>
                    </button>
                </template>
            </div>
        @endif
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="tb-btn" @click="nosidebar = !nosidebar" title="Sembunyikan sidebar">☰</button>
            <nav class="mega">
                {{-- SETUP sengaja tidak muncul di topbar -- khusus admin, aksesnya
                     lewat menu profil ("Pengaturan (Setup)" di dropdown avatar). --}}
                <template x-for="m in menuTree.filter(x => x.code !== 'SETUP')" :key="m.id">
                    <button type="button" class="mega-item" :class="{ active: activeMod === m.code }" :title="m.label + (m.statusLabel ? ' — '+m.statusLabel : '')" @click="selectModule(m.code)">
                        <span class="mi"><i :class="m.icon"></i></span> <span x-text="topLabel(m.code, m.label)"></span>
                        <span class="status-dot" :style="{ background: m.statusColor }"></span>
                    </button>
                </template>
            </nav>
            <div class="tb-spacer"></div>
            <button type="button" class="tb-btn" @click="openPalette()" title="Cari / perintah (Ctrl K)"><i class="fa-solid fa-magnifying-glass"></i></button>
            <button type="button" class="tb-btn" @click="notopbar = true" title="Sembunyikan top bar"><i class="fa-solid fa-chevron-up"></i></button>

            <button type="button" class="tb-btn hasbadge" @click="chatOpen = true" title="Chat">
                <i class="fa-solid fa-comment-dots"></i>
                @if ($this->unreadChatCount > 0)
                    <span class="tbadge">{{ $this->unreadChatCount }}</span>
                @endif
            </button>
            <button type="button" class="tb-btn hasbadge" @click="notifOpen = true" title="Notifikasi">
                <i class="fa-solid fa-bell"></i>
                @if ($this->unreadNotifCount > 0)
                    <span class="tbadge">{{ $this->unreadNotifCount }}</span>
                @endif
            </button>

            <div class="pop">
                <button type="button" class="avatar-btn" @click.stop="ddOpen = !ddOpen" title="{{ Auth::user()->name }}">
                    <span class="avatar">
                        <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg>
                        <span class="av-dot" :class="{ hide: {{ $this->unreadChatCount + $this->unreadNotifCount }} === 0 }"></span>
                    </span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:#9aa3c2"></i>
                </button>
                <div class="pop-menu" :class="{ open: ddOpen }" @click.stop>
                    <div class="um-head">
                        <span class="um-av"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                        <div>
                            <div class="um-name">{{ Auth::user()->name ?: 'Pengguna' }}</div>
                            <div class="um-mail">{{ Auth::user()->email }}</div>
                            <span class="um-role">{{ $this->roleLabel() }}</span>
                        </div>
                    </div>
                    <div class="um-list">
                        <div class="um-cap">Pesan &amp; info</div>
                        <button type="button" class="um-item" @click="ddOpen = false; chatOpen = true"><i class="fa-solid fa-comment-dots"></i> Chat
                            @if ($this->unreadChatCount > 0)<span class="um-count">{{ $this->unreadChatCount }}</span>@endif
                        </button>
                        <button type="button" class="um-item" @click="ddOpen = false; notifOpen = true"><i class="fa-solid fa-bell"></i> Notifikasi
                            @if ($this->unreadNotifCount > 0)<span class="um-count">{{ $this->unreadNotifCount }}</span>@endif
                        </button>
                        <button type="button" class="um-item" @click="ddOpen = false; aiOpen = true"><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--sh-violet)"></i> Asisten AI</button>
                        <div class="um-sep"></div>
                        <div class="um-cap">Akun</div>
                        @if (Auth::user()->employee_id)
                            {{-- Akun tertaut karyawan -- "Edit profil"/"Ubah password" DIGABUNG ke
                            Portal Karyawan tab Security (instruksi user 2026-08-29 "jadikan satu").
                            Instruksi user 2026-08-30: SEMUA urusan user profile pakai layar pop up
                            KECUALI security -- jadi Portal Karyawan sekarang popup (iframe lebar
                            proporsional, bukan navigasi keluar shell), sementara link Security
                            tetap navigasi biasa (dikecualikan, +tetap ada gate admin terpisah
                            "Account Security" di sidebar kiri paling bawah -- lihat di atas). --}}
                            <button type="button" class="um-item" @click="ddOpen = false; portalOpen = true"><i class="fa-solid fa-id-card"></i> Portal Karyawan</button>
                            <a href="{{ route('self-service.index', ['tab' => 'security']) }}" class="um-item"><i class="fa-solid fa-shield-halved"></i> My Security</a>
                        @else
                            {{-- Fallback KHUSUS akun tanpa employee_id (mis. admin teknis) --
                            Portal Karyawan sama sekali tidak bisa diakses akun ini. --}}
                            <button type="button" class="um-item" @click="ddOpen = false; profileOpen = true"><i class="fa-solid fa-user-pen"></i> Edit profil</button>
                            <button type="button" class="um-item" @click="ddOpen = false; passwordOpen = true"><i class="fa-solid fa-key"></i> Ubah password</button>
                        @endif
                        @if (Auth::user()->canSetup() && ! Auth::user()->is_admin)
                            {{-- Full admin (is_admin) sudah punya jalur sendiri: "Account Security"
                            dipasak di bagian bawah sidebar kiri (lihat <aside> di atas). Item ini
                            cuma utk user yg cuma dapat akses modul SETUP tanpa is_admin penuh. --}}
                            <button type="button" class="um-item" @click="ddOpen = false; openTab('SET_USER')"><i class="fa-solid fa-gear"></i> Pengaturan (Setup)</button>
                        @endif
                        <button type="button" class="um-item" @click="ddOpen = false; openTab('SET_USER')"><i class="fa-solid fa-clock-rotate-left"></i> Aktivitas saya</button>
                        <button type="button" class="um-item" @click="ddOpen = false; supportOpen = true; $wire.set('support_context', (openTabs.find(t => t.code === activeTabCode)?.label || 'Home') + ' — ' + new Date().toLocaleString('id-ID'))"><i class="fa-solid fa-circle-question"></i> Help &amp; Support</button>
                        <div class="um-item" style="cursor:default"><i class="fa-solid fa-globe"></i> Bahasa
                            <span class="um-langs" style="margin-left:auto;display:flex;gap:6px">
                                <button type="button" class="um-flag active" @click.stop="toast('Bahasa: Indonesia')">🇮🇩</button>
                                <button type="button" class="um-flag" @click.stop="toast('Bahasa: English')">🇬🇧</button>
                            </span>
                        </div>
                        <div class="um-sep"></div>
                        <button type="button" class="um-item danger" wire:click="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar aplikasi</button>
                    </div>
                </div>
            </div>
        </header>
        <div id="topbar-restore"><button type="button" @click="notopbar = false">▾ Tampilkan top bar</button></div>

        <div class="tabbar">
            <template x-for="t in openTabs" :key="t.code">
                <div class="tab" :class="{ active: activeTabCode === t.code }" @click="activate(t.code)">
                    <i :class="t.icon || 'fa-solid fa-file-lines'"></i>
                    <span class="tlbl" x-text="t.label"></span>
                    <button type="button" class="tx" @click.stop="closeTab(t.code)" title="Tutup">×</button>
                </div>
            </template>
        </div>

        <div class="content">
            <div class="pane" :class="{ active: activeTabCode === null }">
                <div class="home">
                    <div class="hero"></div>
                    <h1>Selamat datang, {{ $this->greetName() }} 👋</h1>
                    <p>Pilih modul di <b>mega menu</b> atas — submenunya muncul di sidebar. Setiap form/grid terbuka sebagai <b>tab</b> di frame ini dan tetap terbuka meski Anda berpindah modul.</p>
                    <div class="hint">Ctrl+K cari cepat · <kbd>⊟</kbd> mini sidebar · <kbd>☰</kbd> sembunyikan sidebar · <kbd>︿</kbd> sembunyikan top bar.</div>
                </div>
            </div>
            <template x-for="t in openTabs" :key="'pane-'+t.code">
                <div class="pane" :class="{ active: activeTabCode === t.code, framed: !!t.url }">
                    <template x-if="t.url"><iframe :src="t.url" :title="t.label"></iframe></template>
                    <template x-if="!t.url"><div x-html="placeholderHtml(t)"></div></template>
                </div>
            </template>
        </div>
    </div>

    <div class="backdrop" :class="{ on: chatOpen || notifOpen || aiOpen }" @click="chatOpen = notifOpen = aiOpen = false"></div>

    {{-- Drawer Notifikasi (sys_notifications — real) --}}
    <aside class="drawer" :class="{ on: notifOpen }">
        <div class="dr-head"><i class="fa-solid fa-bell ico"></i><span class="dr-title">Notifikasi</span><button type="button" class="x" @click="notifOpen = false"><i class="fa-solid fa-xmark"></i></button></div>
        <div style="padding:8px 12px;text-align:right;border-bottom:1px solid var(--sh-border)">
            <a wire:click="markAllNotifRead" style="font-size:12px;color:var(--sh-accent);cursor:pointer;font-weight:600">Tandai semua terbaca</a>
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
                <div class="side-hint" style="color:var(--sh-muted)">Belum ada notifikasi.</div>
            @endforelse
        </div>
        <div style="padding:8px 12px;text-align:center;border-top:1px solid var(--sh-border)">
            <a href="{{ route('notifications.index') }}" style="font-size:12px;color:var(--sh-accent);font-weight:600;text-decoration:none">Lihat semua notifikasi →</a>
        </div>
    </aside>

    {{-- Drawer Chat (sys_chat_* — real) --}}
    <aside class="drawer" :class="{ on: chatOpen }">
        <div class="dr-head"><i class="fa-solid fa-comment-dots ico"></i><span class="dr-title">Chat</span><button type="button" class="x" @click="chatOpen = false"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="chat-body">
            <div class="chat-list">
                @forelse ($this->conversations as $c)
                    <div class="cl-item {{ $activeConversationId === $c->id ? 'active' : '' }}" wire:click="selectConversation({{ $c->id }})">
                        <b>{{ $c->title }}</b>
                        <span>{{ Str::limit($c->last ?? '—', 24) }}</span>
                    </div>
                @empty
                    <div class="side-hint" style="color:var(--sh-muted);padding:12px">Belum ada percakapan.</div>
                @endforelse
            </div>
            <div class="chat-main">
                <div class="msgs">
                    @forelse ($this->activeMessages as $m)
                        <div class="msg {{ $m->sender === Auth::user()->login ? 'me' : 'them' }}">{{ $m->body }}</div>
                    @empty
                        <div class="side-hint" style="color:var(--sh-muted)">{{ $activeConversationId ? 'Belum ada pesan.' : 'Pilih percakapan di sebelah kiri.' }}</div>
                    @endforelse
                </div>
                <form wire:submit="sendMessage" class="chat-in">
                    <input type="text" wire:model="chatInput" placeholder="Tulis pesan…" @if(! $activeConversationId) disabled @endif>
                    <button type="submit" @if(! $activeConversationId) disabled @endif><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Drawer Asisten AI (pratinjau, client-side saja — memetakan ke registry menu) --}}
    <aside class="drawer" :class="{ on: aiOpen }">
        <div class="dr-head"><i class="fa-solid fa-wand-magic-sparkles ico" style="color:#7a52d0"></i><span class="dr-title">Asisten AI</span><span style="font-size:11px;color:var(--sh-muted);margin-left:4px">pratinjau</span><button type="button" class="x" @click="aiOpen = false"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="ai-body" x-ref="aiBody">
            <div class="ai-intro">Tanya dengan bahasa biasa — mis. <b>“buka inquiry”</b> atau <b>“pengguna”</b>, saya bantu bukakan form/grid-nya di tab. Versi ini masih pratinjau (pencocokan kata kunci, bukan LLM sungguhan).
                <div style="margin-top:10px">
                    <button type="button" class="ai-chip" @click="askAi('buka inquiry')">buka inquiry</button>
                    <button type="button" class="ai-chip" @click="askAi('pengguna')">pengguna</button>
                </div>
            </div>
            <template x-for="(m, i) in aiMessages" :key="i">
                <div class="ai-msg" :class="m.role" x-text="m.text"></div>
            </template>
        </div>
        <div class="ai-in">
            <input type="text" x-model="aiInput" placeholder="Ketik permintaan…" @keydown.enter="askAi(aiInput); aiInput = ''">
            <button type="button" @click="askAi(aiInput); aiInput = ''"><i class="fa-solid fa-arrow-up"></i></button>
        </div>
    </aside>

    {{-- Command palette --}}
    <div class="pal-ov" :class="{ open: paletteOpen }" @click="$event.target === $el && closePalette()">
        <div class="pal">
            <div class="pal-in"><span>🔎</span>
                <input type="text" x-ref="paletteInput" x-model="paletteQuery" placeholder="Ketik nama form, grid, atau laporan…" autocomplete="off"
                       @keydown.down.prevent="paletteMove(1)" @keydown.up.prevent="paletteMove(-1)"
                       @keydown.enter="paletteChoose()" @keydown.escape="closePalette()">
            </div>
            <div class="pal-list">
                <template x-if="paletteResults.length === 0">
                    <div class="pal-empty">Tak ada yang cocok.</div>
                </template>
                <template x-for="(o, i) in paletteResults" :key="o.code">
                    <div class="pal-row" :class="{ sel: i === paletteSel }" @click="openTab(o.code); closePalette()">
                        <span class="lic"><i :class="o.icon || 'fa-solid fa-file-lines'"></i></span>
                        <div><div class="nm" x-text="o.label"></div><div class="mm" x-text="o.mod"></div></div>
                    </div>
                </template>
            </div>
            <div class="pal-foot"><span><kbd>↑</kbd><kbd>↓</kbd> pilih</span><span><kbd>↵</kbd> buka</span><span><kbd>esc</kbd> tutup</span></div>
        </div>
    </div>

    {{-- Modal Edit profil --}}
    <div class="mov" :class="{ on: profileOpen }"><div class="modal">
        <div class="mh"><h3>Edit profil</h3><button type="button" class="x" @click="profileOpen = false">&times;</button></div>
        <div class="mb">
            <div class="photo-row">
                <span class="photo-big"><svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v1h18v-1c0-3.5-4-6-9-6z"/></svg></span>
                <button type="button" class="ghost" disabled title="Belum tersedia">Ubah foto</button>
            </div>
            @if ($profileSaved)
                <div class="mfield"><div class="hint ok">✅ Profil tersimpan.</div></div>
            @endif
            <x-form.input legacy="mfield" label="Nama lengkap" wire:model="profile_name" name="profile_name" />
            <x-form.input legacy="mfield" label="Email" type="email" wire:model="profile_email" name="profile_email" />
            <x-form.input legacy="mfield" label="No. telepon" wire:model="profile_phone" />
            <x-form.input legacy="mfield" label="Jabatan" value="{{ $this->roleLabel() }}" readonly />
        </div>
        <div class="mf">
            <button type="button" class="mbtn c" @click="profileOpen = false">Batal</button>
            <button type="button" class="mbtn p" wire:click="saveProfile" wire:loading.attr="disabled">Simpan</button>
        </div>
    </div></div>

    {{-- Modal Ubah password --}}
    <div class="mov" :class="{ on: passwordOpen }"><div class="modal">
        <div class="mh"><h3>Ubah password</h3><button type="button" class="x" @click="passwordOpen = false">&times;</button></div>
        <div class="mb">
            @if ($passwordSaved)
                <div class="mfield"><div class="hint ok">✅ Password diperbarui.</div></div>
            @endif
            @if ($passwordError)
                <div class="mfield"><div class="hint bad">{{ $passwordError }}</div></div>
            @endif
            <x-form.input legacy="mfield" label="Password lama" type="password" wire:model="old_password" placeholder="Password saat ini" />
            <x-form.input legacy="mfield" label="Password baru" type="password" wire:model="new_password" placeholder="Minimal 8 karakter" name="new_password" />
            <x-form.input legacy="mfield" label="Konfirmasi password baru" type="password" wire:model="new_password_confirmation" placeholder="Ulangi password baru" />
        </div>
        <div class="mf">
            <button type="button" class="mbtn c" @click="passwordOpen = false">Batal</button>
            <button type="button" class="mbtn p" wire:click="savePassword" wire:loading.attr="disabled">Simpan password</button>
        </div>
    </div></div>

    {{-- Modal Portal Karyawan -- instruksi user 2026-08-30: pop up lebar
    proporsional tanpa scroll ganda (bukan lagi navigasi <a href> keluar
    shell). Iframe di-lazy-load (:src cuma diisi saat portalOpen=true) supaya
    tidak ikut ke-load diam-diam waktu shell pertama render. --}}
    @if (Auth::user()->employee_id)
        <div class="mov" :class="{ on: portalOpen }"><div class="modal wide">
            <div class="mh"><h3>Portal Karyawan</h3><button type="button" class="x" @click="portalOpen = false">&times;</button></div>
            <div class="mb">
                <iframe :src="portalOpen ? '{{ route('self-service.index') }}' : ''" title="Portal Karyawan"></iframe>
            </div>
        </div></div>
    @endif

    {{-- Modal Help & Support (dulu placeholder toast "segera") --}}
    <div class="mov" :class="{ on: supportOpen }"><div class="modal">
        <div class="mh"><h3>Help &amp; Support</h3><button type="button" class="x" @click="supportOpen = false; $wire.resetSupportForm()">&times;</button></div>
        <div class="mb">
            @if ($supportSubmittedNo)
                <div class="mfield" style="text-align:center;padding:18px 0">
                    <div style="font-size:32px">✅</div>
                    <div style="font-weight:600;margin-top:6px">Ticket submitted</div>
                    <div class="hint">Ticket #{{ $supportSubmittedNo }} — we'll get back to you.</div>
                </div>
            @else
                <div class="hint" style="margin-bottom:8px">{{ $support_context ?: 'Screen context unavailable' }}</div>
                @if ($supportError)
                    <div class="mfield"><div class="hint bad">{{ $supportError }}</div></div>
                @endif
                <div class="mfield">
                    <textarea wire:model="support_description" rows="4" placeholder="What happened? The more detail, the faster it gets fixed." style="width:100%;resize:vertical"></textarea>
                </div>
                <div class="mfield">
                    <label class="hint" style="display:block;margin-bottom:4px">Attach screenshots (optional)</label>
                    <input type="file" wire:model="support_attachments" multiple accept="image/*">
                    <div wire:loading wire:target="support_attachments" class="hint">Uploading…</div>
                    @if (count($support_attachments))
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                            @foreach ($support_attachments as $i => $file)
                                <div style="position:relative;width:52px;height:52px">
                                    <img src="{{ $file->temporaryUrl() }}" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
                                    <button type="button" wire:click="$set('support_attachments.{{ $i }}', null)" title="Remove"
                                        style="position:absolute;top:-6px;right:-6px;background:#fff;border:1px solid #d7dbe2;border-radius:999px;width:18px;height:18px;font-size:11px;line-height:1;cursor:pointer">×</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($this->myTickets->isNotEmpty())
                    <div style="border-top:1px solid #eef1f5;margin-top:14px;padding-top:10px">
                        <div class="hint" style="font-weight:600;margin-bottom:6px">My tickets</div>
                        <div style="display:flex;flex-direction:column;gap:6px;max-height:150px;overflow-y:auto">
                            @foreach ($this->myTickets as $t)
                                <div style="display:flex;align-items:center;gap:8px;font-size:12px">
                                    <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:999px;white-space:nowrap;{{ match ($t->status) {
                                        'DONE' => 'background:#eaf7ee;color:#1f8a4c',
                                        'CLOSED' => 'background:#eef1f5;color:#4b5568',
                                        'IN_PROGRESS' => 'background:#fff5e0;color:#8a5a00',
                                        default => 'background:#eaf2ff;color:#1d4ed8',
                                    } }}">{{ str_replace('_', ' ', $t->status) }}</span>
                                    <span style="flex:1;color:#334155">{{ Str::limit($t->description, 46) }}</span>
                                    <span style="color:#94a3b8">{{ $t->created_at->diffForHumans(null, true) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
        <div class="mf">
            @if ($supportSubmittedNo)
                <button type="button" class="mbtn p" @click="supportOpen = false; $wire.resetSupportForm()">Close</button>
            @else
                <button type="button" class="mbtn c" @click="supportOpen = false">Cancel</button>
                <button type="button" class="mbtn p" wire:click="submitSupportTicket" wire:loading.attr="disabled">Submit</button>
            @endif
        </div>
    </div></div>

    <div x-text="toastMsg"
         style="position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:#2f3a5e;color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;transition:opacity .25s;z-index:130;pointer-events:none"
         :style="{ opacity: toastShow ? 1 : 0 }"></div>
</div>
