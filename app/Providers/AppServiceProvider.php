<?php

namespace App\Providers;

use App\Services\Nlp\AnthropicModelAnalyzer;
use App\Services\Nlp\ModelAnalyzer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ModelAnalyzer::class, AnthropicModelAnalyzer::class);
    }

    public function boot(): void
    {
        $key = fn (Request $r) => (string) ($r->user()?->id ?? $r->ip());

        RateLimiter::for('imports', fn (Request $r) => Limit::perMinute(config('reviewlens.limits.imports_per_minute'))->by($key($r)));
        RateLimiter::for('analyses', fn (Request $r) => Limit::perMinute(config('reviewlens.limits.analyses_per_minute'))->by($key($r)));
        RateLimiter::for('exports', fn (Request $r) => Limit::perMinute(config('reviewlens.limits.exports_per_minute'))->by($key($r)));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(8)->by($r->ip().'|'.mb_strtolower((string) $r->input('email'))));
    }
}
