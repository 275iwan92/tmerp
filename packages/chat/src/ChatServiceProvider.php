<?php

namespace Chat;

use Chat\Contracts\Directory;
use Chat\Http\ChatController;
use Chat\Http\ReadOnlySession;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Satu pintu paket chat: rute, migrasi tabel, setelan, dan kait sign out. Aplikasi pemakai cukup mendaftarkan provider ini,
// (bila perlu) mengikat Directory miliknya, dan menaruh layar chat yang diisi ChatService::boot(). Lihat README.md.
class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chat.php', 'chat');
        $this->app->bindIf(Directory::class, EloquentDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/chat.php' => config_path('chat.php')], 'chat-config');

        // poll (tiap beberapa detik) dan potongan upload tidak menulis sesi (ReadOnlySession).
        Route::middleware(config('chat.middleware'))->prefix(config('chat.prefix'))->name('chat.')->group(function () {
            Route::get('/poll', [ChatController::class, 'poll'])->middleware(ReadOnlySession::class)->name('poll');
            Route::get('/messages', [ChatController::class, 'messages'])->name('messages');
            Route::post('/upload', [ChatController::class, 'upload'])->middleware(ReadOnlySession::class)->name('upload');
            Route::post('/send', [ChatController::class, 'send'])->name('send');
            Route::get('/file/{id}', [ChatController::class, 'file'])->whereNumber('id')->name('file');
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user && $e->guard === (config('chat.guard') ?? config('auth.defaults.guard'))) {
                $this->app->make(ChatService::class)->signOut($e->user);
            }
        });
    }
}
