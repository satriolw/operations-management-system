<?php

namespace App\Modules\Signals;

use App\Modules\Signals\Contracts\ReplacementMatcher;
use Illuminate\Support\ServiceProvider;

/**
 * Binding domain Signals. ReplacementMatcher → Null (OPS-604); ganti ke heuristik/field terstruktur
 * tanpa menyentuh detector saat tersedia.
 */
class SignalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReplacementMatcher::class, NullReplacementMatcher::class);
    }
}
