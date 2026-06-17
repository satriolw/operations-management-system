<?php

namespace App\Modules\Templating;

use App\Models\Outlet;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Versioning template (OPS-1004): draft → preview → publish, rollback. Edit master tak live-merusak
 * outlet pewaris — perubahan baru berlaku saat di-publish. Token divalidasi (OPS-901) sebelum draft.
 */
final class TemplateVersioning
{
    /** Simpan draft versi baru (tak memengaruhi yang live). */
    public function saveDraft(ReportTemplate $template, array $layout, ?int $userId = null): ReportTemplateVersion
    {
        if (! TemplateTokens::isValid($layout)) {
            throw new InvalidArgumentException('Token tak dikenal: '.implode(', ', TemplateTokens::invalidTokens($layout)));
        }

        $next = (int) ReportTemplateVersion::query()->where('report_template_id', $template->id)->max('version') + 1;

        return ReportTemplateVersion::create([
            'report_template_id' => $template->id,
            'version' => $next,
            'layout_json' => $layout,
            'status' => ReportTemplateVersion::DRAFT,
            'created_by' => $userId,
        ]);
    }

    /** Publish satu versi → jadi live; versi published lain di-archive. */
    public function publish(ReportTemplateVersion $version): ReportTemplate
    {
        return DB::transaction(function () use ($version) {
            ReportTemplateVersion::query()
                ->where('report_template_id', $version->report_template_id)
                ->where('status', ReportTemplateVersion::PUBLISHED)
                ->update(['status' => ReportTemplateVersion::ARCHIVED]);

            $version->update(['status' => ReportTemplateVersion::PUBLISHED, 'published_at' => now()]);

            $template = $version->template;
            $template->update(['layout_json' => $version->layout_json, 'active' => true]);

            return $template->refresh();
        });
    }

    /** Rollback: jadikan versi lama live kembali (publish ulang). */
    public function rollback(ReportTemplate $template, int $version): ReportTemplate
    {
        $v = ReportTemplateVersion::query()
            ->where('report_template_id', $template->id)->where('version', $version)->firstOrFail();

        return $this->publish($v);
    }

    /**
     * Dampak publish master: outlet aktif yang MEWARISI master (tanpa override aktif) → terpengaruh.
     *
     * @return Collection<int,int> id_outlet terdampak
     */
    public function impactOfMaster(): Collection
    {
        $overridden = ReportTemplate::query()
            ->where('scope', ReportTemplate::SCOPE_OUTLET)->where('active', true)
            ->whereNotNull('id_outlet')->pluck('id_outlet');

        return Outlet::query()->where('active', true)
            ->whereNotIn('id_outlet', $overridden)
            ->pluck('id_outlet');
    }
}
