<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Modules\Admin\Http\Requests\ChecklistItemRequest;
use App\Modules\Admin\Http\Requests\ChecklistTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * CRUD master template & item checklist (M3-01, Modul 3 Discipline). Admin definisikan item per
 * outlet/shift. Blade custom (keputusan se-proyek). Gate master_data.edit.
 */
class ChecklistTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.checklists.index', [
            'templates' => ChecklistTemplate::with('items')->orderBy('id_outlet')->orderBy('name')->get(),
            'schedules' => ChecklistTemplate::SCHEDULES,
        ]);
    }

    public function store(ChecklistTemplateRequest $request): RedirectResponse
    {
        ChecklistTemplate::create([
            'id_outlet' => $request->input('id_outlet') ?: null,
            'name' => $request->input('name'),
            'schedule' => $request->input('schedule'),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.checklists.index')->with('status', 'Template checklist dibuat.');
    }

    public function update(ChecklistTemplateRequest $request, ChecklistTemplate $checklist): RedirectResponse
    {
        $checklist->update([
            'id_outlet' => $request->input('id_outlet') ?: null,
            'name' => $request->input('name'),
            'schedule' => $request->input('schedule'),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('admin.checklists.index')->with('status', 'Template diperbarui.');
    }

    public function destroy(ChecklistTemplate $checklist): RedirectResponse
    {
        $checklist->delete();

        return redirect()->route('admin.checklists.index')->with('status', 'Template dihapus.');
    }

    public function storeItem(ChecklistItemRequest $request, ChecklistTemplate $checklist): RedirectResponse
    {
        $checklist->items()->create([
            'label' => $request->input('label'),
            'requires_photo' => $request->boolean('requires_photo'),
            'order' => (int) $request->input('order', 0),
        ]);

        return redirect()->route('admin.checklists.index')->with('status', 'Item ditambahkan.');
    }

    public function destroyItem(ChecklistItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('admin.checklists.index')->with('status', 'Item dihapus.');
    }
}
