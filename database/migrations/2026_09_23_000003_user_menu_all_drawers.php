<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Keputusan user 2026-09-23: SEMUA menu dropdown profil = drawer kanan
// (menggantikan modal, jendela x-ui.window, tab browser baru & pindah halaman).
// action_target = nama flag di profileDrawers() (resources/js/app-shell.js).
return new class extends Migration
{
    private const DRAWERS = [
        'UM_CHAT' => 'chatOpen',
        'UM_NOTIF' => 'notifOpen',
        'UM_AI' => 'aiOpen',
        'UM_EDITPROFIL' => 'profileOpen',
        'UM_PASSWORD' => 'passwordOpen',
        'UM_PORTALCFG' => 'portalOpen',
        'UM_MYSECURITY' => 'securityOpen',
        'UM_HELP' => 'helpOpen',
        'UM_CONFIG' => 'configOpen',
        'UM_ACTIVITY' => 'activityOpen',
    ];

    private const BEFORE = [
        'UM_CHAT' => ['drawer', 'chatOpen'],
        'UM_NOTIF' => ['drawer', 'notifOpen'],
        'UM_AI' => ['drawer', 'aiOpen'],
        'UM_EDITPROFIL' => ['modal', 'profileOpen'],
        'UM_PASSWORD' => ['modal', 'passwordOpen'],
        'UM_PORTALCFG' => ['modal', 'portalOpen'],
        'UM_MYSECURITY' => ['url', 'self-service.index'],
        'UM_HELP' => ['url', 'help.index'],
        'UM_CONFIG' => ['modal', 'configWinOpen'],
        'UM_ACTIVITY' => ['modal', 'activityWinOpen'],
    ];

    public function up(): void
    {
        foreach (self::DRAWERS as $code => $flag) {
            DB::connection('tm2sys')->table('sys_user_menu')->where('code', $code)
                ->update(['action_type' => 'drawer', 'action_target' => $flag, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::BEFORE as $code => [$type, $target]) {
            DB::connection('tm2sys')->table('sys_user_menu')->where('code', $code)
                ->update(['action_type' => $type, 'action_target' => $target]);
        }
    }
};
