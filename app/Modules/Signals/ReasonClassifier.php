<?php

namespace App\Modules\Signals;

/**
 * Taksonomi alasan void/refund (OPS-603): input_error / abandoned / change_request / other.
 * Kata kunci input-error dapat dikonfigurasi (config/signals.php).
 */
final class ReasonClassifier
{
    public const INPUT_ERROR = 'input_error';
    public const ABANDONED = 'abandoned';
    public const CHANGE_REQUEST = 'change_request';
    public const OTHER = 'other';

    public function classify(?string $reason): string
    {
        $n = mb_strtolower(trim((string) $reason));
        if ($n === '') {
            return self::OTHER;
        }

        foreach ((array) config('signals.input_error_keywords', []) as $kw) {
            if (str_contains($n, mb_strtolower($kw))) {
                return self::INPUT_ERROR;
            }
        }
        if (str_contains($n, 'belum')) {
            return self::ABANDONED;
        }
        foreach (['ganti', 'ubah', 'pisah', 'ingin', 'customer'] as $kw) {
            if (str_contains($n, $kw)) {
                return self::CHANGE_REQUEST;
            }
        }

        return self::OTHER;
    }

    public function isInputError(?string $reason): bool
    {
        return $this->classify($reason) === self::INPUT_ERROR;
    }
}
