<?php

namespace App\Console\Commands;

use App\Models\DocumentAttachment;
use App\Modules\Finance\AttachmentService;
use App\Support\Time\Wib;
use Illuminate\Console\Command;

/**
 * M2-06 · retensi lampiran bukti (selaras OPS-705). Hapus lampiran (file privat + baris) yang lebih
 * tua dari finance.attachment_retention_days. Dokumen keuangan disimpan lama (default 5 tahun).
 */
class PurgeFinanceAttachments extends Command
{
    protected $signature = 'oms:purge-finance-attachments';

    protected $description = 'Hapus lampiran bukti dokumen melewati masa retensi (M2-06).';

    public function handle(AttachmentService $service): int
    {
        $days = (int) config('finance.attachment_retention_days', 1825);
        $cutoff = Wib::normalize(now())->subDays($days);

        $count = 0;
        DocumentAttachment::query()->where('created_at', '<', $cutoff)->each(function (DocumentAttachment $a) use ($service, &$count) {
            $service->delete($a);
            $count++;
        });

        $this->info("Retensi lampiran: {$count} dihapus (lebih tua dari {$days} hari).");

        return self::SUCCESS;
    }
}
