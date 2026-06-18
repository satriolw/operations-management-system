<?php

namespace App\Modules\Discipline\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan checklist (M3-02). Dilempar saat capture token tak sah (foto bukan dari
 * kamera in-app / galeri), foto wajib tak ada, atau item tak cocok run.
 */
class DisciplineException extends RuntimeException
{
    public static function invalidCaptureToken(): self
    {
        return new self('Foto harus dari kamera in-app (capture token tak sah / kedaluwarsa / sudah dipakai).');
    }

    public static function photoRequired(): self
    {
        return new self('Item ini wajib bukti foto.');
    }

    public static function itemNotInRun(): self
    {
        return new self('Item tidak termasuk dalam run checklist ini.');
    }
}
