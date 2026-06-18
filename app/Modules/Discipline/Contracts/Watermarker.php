<?php

namespace App\Modules\Discipline\Contracts;

/**
 * Tempel watermark (timestamp + outlet + item) ke foto SERVER-SIDE (M3-02). Di balik interface agar
 * implementasi (GD/Imagick) dapat ditukar & di-fake saat test. Klien TIDAK pernah menempel watermark.
 *
 * @return string  byte gambar ber-watermark
 */
interface Watermarker
{
    /** @param array{timestamp:string,outlet:string,item:string} $context */
    public function stamp(string $imageBytes, array $context): string;
}
