<?php

namespace App\Providers;

use App\Assistant\ErpAsker;
use App\Assistant\ErpKnowledge;
use App\Assistant\ErpNavigator;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysConstant;
use Assistant\Assistant;
use Assistant\AssistantService;
use Assistant\Contracts\Knowledge;
use Assistant\Contracts\Navigator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

// Adaptor ERP untuk paket asisten (packages/assistant): satu-satunya tempat paket itu "mengenal" ERP ini.
// Penanya = user security (level + hak modul), layar = Menu v2, bantuan = Help, setelan = Settings > Constants.
class AssistantHostProvider extends ServiceProvider
{
    // Setelan paket yang berupa teks; selain ini angka.
    private const TEXT_SETTINGS = ['model_url', 'model_name'];

    // Isi drawer asisten untuk x-dl::layer-shell. Database yang belum punya tabel asisten (tm2* sebelum disetujui user)
    // mendapat [] = drawer tetap pratinjau, halaman tidak gagal.
    public static function drawer(SecUser $user): array
    {
        return Schema::connection('tm2sys')->hasTable('sys_assistant_message') ? app(AssistantService::class)->boot($user) : [];
    }

    public function register(): void
    {
        $this->app->scoped(Knowledge::class, ErpKnowledge::class);
        $this->app->scoped(Navigator::class, ErpNavigator::class);
    }

    public function boot(): void
    {
        Assistant::askerUsing(fn ($user) => new ErpAsker($user));
        Assistant::translateUsing(fn (string $en) => t($en));
        // model_enabled -> ASSISTANT_MODEL_ENABLED, dst. Konstanta belum ada / dimatikan = bawaan paket.
        Assistant::settingsUsing(function (string $key, mixed $default) {
            $row = SysConstant::query()->where('constant_key', 'ASSISTANT_'.strtoupper($key))->where('activate', true)->first();
            if (! $row) {
                return $default;
            }

            return in_array($key, self::TEXT_SETTINGS, true) ? ($row->constant_text ?: $default) : ($row->constant_number ?? $default);
        });
    }
}
