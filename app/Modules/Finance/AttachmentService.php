<?php

namespace App\Modules\Finance;

use App\Models\DocumentAttachment;
use App\Models\FinancialDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Penyimpanan lampiran bukti dokumen (M2-06, §6). File di disk PRIVAT (bukan publik); hanya
 * DocumentAttachment.file_ref (pointer) yang disimpan di DB. Akses unduh ter-scope (controller).
 */
final class AttachmentService
{
    public function disk(): string
    {
        return (string) config('finance.attachment_disk', 'local');
    }

    public function store(FinancialDocument $doc, UploadedFile $file, string $kind, ?int $userId): DocumentAttachment
    {
        $dir = trim((string) config('finance.attachment_dir', 'finance/attachments'), '/')."/{$doc->id}";
        $path = $file->store($dir, $this->disk()); // disk privat

        return $doc->attachments()->create([
            'file_ref' => $path,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $userId,
        ]);
    }

    public function delete(DocumentAttachment $attachment): void
    {
        Storage::disk($this->disk())->delete($attachment->file_ref);
        $attachment->delete();
    }
}
