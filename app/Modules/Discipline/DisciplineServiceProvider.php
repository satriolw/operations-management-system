<?php

namespace App\Modules\Discipline;

use App\Modules\Discipline\Contracts\Watermarker;
use Illuminate\Support\ServiceProvider;

/**
 * Binding domain Discipline (Modul 3). Watermarker → GD (M3-02); ganti adapter (Imagick/fake)
 * tanpa menyentuh SubmissionService.
 */
class DisciplineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Watermarker::class, GdWatermarker::class);
    }
}
