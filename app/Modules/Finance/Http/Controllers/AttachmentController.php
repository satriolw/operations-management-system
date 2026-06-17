<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Models\DocumentAttachment;
use App\Models\FinancialDocument;
use App\Modules\Finance\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload & unduh lampiran bukti dokumen keuangan (M2-06, §6). Disk privat + akses ter-scope
 * per-outlet (OPS-1003). Dokumen FINAL immutable → tak menerima lampiran baru.
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachments) {}

    public function store(Request $request, FinancialDocument $document): RedirectResponse
    {
        $this->assertAccess($request, $document);
        abort_if($document->isFinal(), 403, 'Dokumen FINAL immutable.');

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'kind' => ['nullable', 'in:receipt,other'],
        ]);

        $this->attachments->store($document, $request->file('file'), $request->input('kind', 'receipt'), $request->user()?->id);

        return back()->with('status', 'Lampiran tersimpan.');
    }

    public function download(Request $request, FinancialDocument $document, DocumentAttachment $attachment): StreamedResponse
    {
        $this->assertAccess($request, $document);
        abort_unless((int) $attachment->document_id === (int) $document->id, 404);

        $disk = Storage::disk($this->attachments->disk());
        abort_unless($disk->exists($attachment->file_ref), 404);

        return $disk->download($attachment->file_ref, $attachment->original_name);
    }

    /** Otorisasi per-outlet (OPS-1003). HEAD_OFFICE → akses-semua. */
    private function assertAccess(Request $request, FinancialDocument $document): void
    {
        $user = $request->user();
        $ok = $document->id_outlet === null
            ? $user->canAccessAllOutlets()
            : $user->canAccessOutlet((int) $document->id_outlet);

        abort_unless($ok, 403);
    }
}
