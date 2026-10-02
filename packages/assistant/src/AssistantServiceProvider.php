<?php

namespace Assistant;

use Assistant\Console\PruneCommand;
use Assistant\Contracts\Knowledge;
use Assistant\Contracts\LanguageModel;
use Assistant\Contracts\Navigator;
use Assistant\Http\AssistantController;
use Assistant\Llm\Ollama;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Satu pintu paket asisten: rute, migrasi tabel, setelan. Aplikasi pemakai mendaftarkan provider ini, mengikat Navigator dan
// Knowledge miliknya, memberi tahu siapa penanya (Assistant::askerUsing), dan mendaftar kelas metric di config. Lihat README.md.
class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/assistant.php', 'assistant');
        $this->app->bindIf(LanguageModel::class, Ollama::class);
        $this->app->bindIf(Navigator::class, NoScreens::class);
        $this->app->bindIf(Knowledge::class, NoHelp::class);
        $this->app->scoped(MetricCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/assistant.php' => config_path('assistant.php')], 'assistant-config');
        $this->commands([PruneCommand::class]);

        Route::middleware(config('assistant.middleware'))->prefix(config('assistant.prefix'))->name('assistant.')->group(function () {
            Route::post('/ask', [AssistantController::class, 'ask'])->name('ask');
            Route::post('/feedback', [AssistantController::class, 'feedback'])->name('feedback');
        });
    }
}
