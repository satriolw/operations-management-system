<?php

namespace App\Console\Commands;

use App\Modules\Signals\SignalDigest;
use Illuminate\Console\Command;

/**
 * OPS-1002 · kirim digest sinyal low (terjadwal harian/mingguan).
 */
class SendSignalDigest extends Command
{
    protected $signature = 'oms:signal-digest';

    protected $description = 'Kirim digest sinyal severity rendah (OPS-1002).';

    public function handle(SignalDigest $digest): int
    {
        $r = $digest->build();
        $this->info("Digest sinyal low: {$r['total']} sinyal.");

        return self::SUCCESS;
    }
}
