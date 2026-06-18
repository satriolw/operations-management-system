<?php

namespace App\Modules\Discipline;

use App\Modules\Discipline\Contracts\Watermarker;
use RuntimeException;

/**
 * Watermark via GD (M3-02). Tempel baris timestamp + outlet + item di bagian bawah foto, SERVER-SIDE.
 * Butuh ekstensi GD. Bila absen → RuntimeException (binding bisa diganti adapter lain).
 */
final class GdWatermarker implements Watermarker
{
    public function stamp(string $imageBytes, array $context): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi GD tak tersedia untuk watermark foto.');
        }

        $img = @imagecreatefromstring($imageBytes);
        if ($img === false) {
            throw new RuntimeException('Berkas bukan gambar valid.');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $lineH = 16;
        $lines = [
            $context['timestamp'] ?? '',
            ($context['outlet'] ?? '').' · '.($context['item'] ?? ''),
        ];

        // bilah gelap semi-transparan di bawah
        $bar = imagecolorallocatealpha($img, 0, 0, 0, 50);
        imagefilledrectangle($img, 0, $h - ($lineH * count($lines) + 8), $w, $h, $bar);
        $white = imagecolorallocate($img, 255, 255, 255);
        foreach ($lines as $i => $text) {
            imagestring($img, 3, 6, $h - ($lineH * (count($lines) - $i)) - 4, $text, $white);
        }

        ob_start();
        imagejpeg($img, null, 85);
        $out = (string) ob_get_clean();
        imagedestroy($img);

        return $out;
    }
}
