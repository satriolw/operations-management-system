<?php

namespace App\Modules\Templating\Http\Controllers;

use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Modules\Templating\TemplateRenderer;
use App\Modules\Templating\TemplateTokens;
use App\Modules\Templating\TemplateVersioning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Builder template drag & drop (OPS-902). Susun blok/token → simpan sebagai DRAFT version
 * (OPS-1004) → publish. Pipeline TIDAK terblokir builder (pakai template aktif/seed/override).
 * R7: konten divalidasi muat approved Meta template (full_auto); jika tidak → peringatan
 * (OPS-903 menegakkan fallback hybrid saat kirim).
 */
class TemplateBuilderController extends Controller
{
    /** Sample token utk preview & uji muat approved template. */
    private const SAMPLE = [
        'nama_outlet' => 'Kemang', 'nama_investor' => 'Pak Andre', 'tanggal' => '2026-06-12',
        'total_sales' => 10138108, 'realized' => 9897108, 'piutang' => 241000, 'txn_count' => 93,
        'avg_transaction' => 109012, 'avg_customer_spending' => 152329, 'volume_kg' => 67, 'volume_pcs' => 121,
    ];

    public function __construct(private readonly TemplateVersioning $versioning, private readonly TemplateRenderer $renderer) {}

    public function edit(ReportTemplate $template): View
    {
        return view('admin.templates.builder', [
            'template' => $template,
            'tokens' => TemplateTokens::ALLOWED,
            'sample' => self::SAMPLE,
            'versions' => ReportTemplateVersion::where('report_template_id', $template->id)->latest('version')->get(),
        ]);
    }

    public function saveDraft(Request $request, ReportTemplate $template): JsonResponse
    {
        $layout = $request->input('layout_json');
        if (! is_array($layout)) {
            return response()->json(['error' => 'layout_json wajib array blok.'], 422);
        }

        try {
            $version = $this->versioning->saveDraft($template, $layout, $request->user()?->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422); // token tak dikenal
        }

        $fits = $this->fitsApprovedTemplate($layout);

        return response()->json([
            'version' => $version->version,
            'status' => $version->status,
            'fits_approved_template' => $fits, // R7
            'warning' => $fits ? null : 'Konten mungkin tak muat approved Meta template (assisted/full_auto) — saat kirim akan fallback hybrid (OPS-903).',
        ], 201);
    }

    public function publish(ReportTemplate $template, ReportTemplateVersion $version): JsonResponse
    {
        abort_unless((int) $version->report_template_id === (int) $template->id, 404);

        $this->versioning->publish($version);

        return response()->json([
            'published_version' => $version->version,
            'fits_approved_template' => $this->fitsApprovedTemplate($version->layout_json),
        ]);
    }

    /** R7: render konten ke satu parameter approved template; muat? */
    private function fitsApprovedTemplate(array $layout): bool
    {
        $transient = ReportTemplate::make(['layout_json' => $layout]);

        return $this->renderer->forTransport($transient, self::SAMPLE)['fits'];
    }
}
