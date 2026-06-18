<?php

namespace App\Modules\Delivery;

/**
 * Validasi konten muat ke approved Meta template (R7, System Design §3.9): pesan bisnis-initiated ke
 * grup WAJIB approved template berparameter. Model konten dipisah dari transport → render layout_json
 * diisi ke SATU parameter body besar. Sebelum kirim full_auto/assisted, pastikan muat; jika tidak →
 * pemanggil fallback hybrid (paste manual tak terbatas template).
 */
final class ApprovedTemplateFit
{
    /** Muat bila tak kosong & panjang ≤ batas parameter approved template (configurable). */
    public function fits(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $max = (int) config('whatsapp.template.max_param_chars', 1024);

        return mb_strlen($text) <= $max;
    }
}
