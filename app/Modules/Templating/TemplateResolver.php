<?php

namespace App\Modules\Templating;

use App\Models\ReportTemplate;

/**
 * Pewarisan template (OPS-901): override outlet diutamakan, jatuh ke master grup.
 * Pipeline tak pernah kekurangan template selama master default ter-seed (DefaultTemplateSeeder).
 */
final class TemplateResolver
{
    /** Override outlet aktif bila ada; jika tidak, master aktif. Null bila belum di-seed. */
    public function forOutlet(int $idOutlet): ?ReportTemplate
    {
        $override = ReportTemplate::query()
            ->where('scope', ReportTemplate::SCOPE_OUTLET)
            ->where('id_outlet', $idOutlet)
            ->where('active', true)
            ->latest('id')
            ->first();

        return $override ?? $this->master();
    }

    public function master(): ?ReportTemplate
    {
        return ReportTemplate::query()
            ->where('scope', ReportTemplate::SCOPE_MASTER)
            ->where('active', true)
            ->latest('id')
            ->first();
    }
}
