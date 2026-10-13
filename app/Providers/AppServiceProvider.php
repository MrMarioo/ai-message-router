<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('viewApiDocs', fn (?object $user = null): bool => true);

        Scramble::configure()
            ->expose(
                ui: '/api/v1/docs',
                document: '/api/v1/docs.json',
            )
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->info->title = 'AI Message Router';
                $openApi->info->description = 'Proof of concept: an AI agent (local LLM via Ollama) classifies incoming messages and emails the responsible department using a tool call.';
            });
    }
}
