<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Outlet;
use App\Models\ReportTemplate;
use App\Modules\Admin\Http\Requests\ReportTemplateRequest;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Kelola template laporan (OPS-901): daftar master + override per-outlet, buat, hapus.
 * Penyuntingan blok/token dilakukan di builder drag & drop (OPS-902). Master tak bisa dihapus
 * bila masih diwarisi override (jaga pewarisan System Design §3.9). Template baru lahir dengan
 * layout default valid agar pipeline laporan tak terblokir builder.
 */
class ReportTemplateController extends Controller
{
    public function index(): View
    {
        $templates = ReportTemplate::query()
            ->orderByRaw("CASE scope WHEN 'master' THEN 0 ELSE 1 END")
            ->orderBy('id_outlet')
            ->get();

        return view('admin.templates.index', [
            'masters' => $templates->where('scope', ReportTemplate::SCOPE_MASTER)->values(),
            'overrides' => $templates->whereIn('scope', [ReportTemplate::SCOPE_OUTLET, ReportTemplate::SCOPE_TARGET])->values(),
            'outlets' => Outlet::query()->orderBy('name')->get(['id_outlet', 'name']),
        ]);
    }

    public function store(ReportTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        // Override mewarisi layout master; master baru pakai layout default valid.
        $parent = ! empty($data['parent_template_id']) ? ReportTemplate::find($data['parent_template_id']) : null;
        $data['layout_json'] = $parent?->layout_json ?? DefaultTemplateSeeder::defaultLayout();
        $data['active'] = true;
        $data['updated_by'] = $request->user()->id;

        $tpl = ReportTemplate::create($data);

        return redirect()->route('admin.templates.builder', $tpl)
            ->with('status', 'Template dibuat. Susun blok & token di builder.');
    }

    public function destroy(ReportTemplate $template): RedirectResponse
    {
        // Jangan hapus master yang masih diwarisi override (pewarisan rusak).
        if ($template->scope === ReportTemplate::SCOPE_MASTER
            && ReportTemplate::where('parent_template_id', $template->id)->exists()) {
            return back()->withErrors(['template' => 'Master masih diwarisi override outlet — hapus override dulu.']);
        }

        $template->delete();

        return back()->with('status', 'Template dihapus.');
    }
}
