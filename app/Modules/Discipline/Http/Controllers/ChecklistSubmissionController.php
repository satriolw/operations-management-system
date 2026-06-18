<?php

namespace App\Modules\Discipline\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistSubmission;
use App\Modules\Discipline\CaptureTokenService;
use App\Modules\Discipline\Exceptions\DisciplineException;
use App\Modules\Discipline\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Submission checklist + capture token (M3-02). Anti-palsu: foto wajib via kamera in-app (capture
 * token). Akses ter-scope per-outlet (OPS-1003). Foto = data sensitif → disk privat, unduh scoped.
 */
class ChecklistSubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionService $submissions,
        private readonly CaptureTokenService $tokens,
    ) {}

    /** Terbitkan capture token saat kamera in-app dibuka utk (run,item). */
    public function captureToken(Request $request, ChecklistRun $run, ChecklistItem $item): JsonResponse
    {
        $this->assertAccess($request, $run);

        return response()->json(['capture_token' => $this->tokens->issue($run, $item, $request->user())]);
    }

    public function submit(Request $request, ChecklistRun $run, ChecklistItem $item): JsonResponse
    {
        $this->assertAccess($request, $run);

        $request->validate([
            'photo' => ['nullable', 'file', 'image', 'max:10240'],
            'capture_token' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $sub = $this->submissions->submit(
                $run, $item, $request->user(),
                $request->file('photo'), $request->input('capture_token'),
                $request->only(['gps_lat', 'gps_lng', 'note']),
            );
        } catch (DisciplineException $e) {
            return response()->json(['error' => $e->getMessage()], 422); // galeri/foto wajib ditolak
        }

        return response()->json(['id' => $sub->id, 'captured_at_server' => $sub->captured_at_server], 201);
    }

    public function photo(Request $request, ChecklistRun $run, ChecklistSubmission $submission): StreamedResponse
    {
        $this->assertAccess($request, $run);
        abort_unless((int) $submission->run_id === (int) $run->id, 404);

        $disk = Storage::disk(config('discipline.photo_disk', 'local'));
        abort_unless($submission->photo_ref && $disk->exists($submission->photo_ref), 404);

        return $disk->download($submission->photo_ref);
    }

    private function assertAccess(Request $request, ChecklistRun $run): void
    {
        abort_unless($request->user()->canAccessOutlet((int) $run->id_outlet), 403);
    }
}
