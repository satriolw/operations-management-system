<?php

namespace App\Modules\Discipline;

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistSubmission;
use App\Models\User;
use App\Modules\Discipline\Contracts\Watermarker;
use App\Modules\Discipline\Exceptions\DisciplineException;
use App\Support\Time\Wib;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Submit item checklist dgn anti-palsu (M3-02). Foto WAJIB via kamera in-app: upload tanpa capture
 * token sah → DITOLAK (galeri). captured_at_server = stempel SERVER (bukan klien). Watermark
 * (timestamp+outlet+item) ditempel SERVER-SIDE sebelum simpan ke disk privat.
 */
final class SubmissionService
{
    public function __construct(
        private readonly CaptureTokenService $tokens,
        private readonly Watermarker $watermarker,
    ) {}

    public function submit(
        ChecklistRun $run,
        ChecklistItem $item,
        User $crew,
        ?UploadedFile $photo,
        ?string $captureToken,
        array $opts = [],
    ): ChecklistSubmission {
        if ((int) $item->template_id !== (int) $run->template_id) {
            throw DisciplineException::itemNotInRun();
        }

        $now = Wib::normalize(now()); // SERVER-side, bukan klien
        $photoRef = null;

        if ($item->requires_photo && $photo === null) {
            throw DisciplineException::photoRequired();
        }

        if ($photo !== null) {
            // GATE anti-palsu: tanpa capture token sah → foto bukan dari kamera in-app → tolak.
            if ($captureToken === null || ! $this->tokens->verify($captureToken, $run, $item, $crew)) {
                throw DisciplineException::invalidCaptureToken();
            }
            $photoRef = $this->storeWatermarked($run, $item, $photo, $now);
        }

        return ChecklistSubmission::updateOrCreate(
            ['run_id' => $run->id, 'item_id' => $item->id],
            [
                'crew_user_id' => $crew->id,
                'photo_ref' => $photoRef,
                'captured_at_server' => $now, // server, abaikan klaim klien
                'gps_lat' => $opts['gps_lat'] ?? null, // opsional, tanpa enforce radius (v1)
                'gps_lng' => $opts['gps_lng'] ?? null,
                'note' => $opts['note'] ?? null,
            ],
        );
    }

    private function storeWatermarked(ChecklistRun $run, ChecklistItem $item, UploadedFile $photo, $now): string
    {
        $stamped = $this->watermarker->stamp((string) file_get_contents($photo->getRealPath()), [
            'timestamp' => $now->format('Y-m-d H:i:s').' WIB',
            'outlet' => 'Outlet '.$run->id_outlet,
            'item' => $item->label,
        ]);

        $dir = trim((string) config('discipline.photo_dir', 'discipline/photos'), '/')."/{$run->id}";
        $path = $dir.'/'.$item->id.'_'.$now->format('His').'.jpg';
        Storage::disk(config('discipline.photo_disk', 'local'))->put($path, $stamped); // disk privat

        return $path;
    }
}
