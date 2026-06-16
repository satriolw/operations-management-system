<?php

namespace App\Modules\Templating;

use App\Models\ReportTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Render layout_json → output (OPS-903, System Design §3.9). Model konten DIPISAH dari transport:
 *  - hybrid       → teks bebas (renderText)
 *  - assisted/full_auto → isi ke SATU parameter besar approved Meta template (forTransport);
 *    bila konten tak muat → fits=false + alert → pemanggil fallback ke hybrid.
 *
 * Angka nol disembunyikan; Rupiah & tanggal locale id-ID.
 */
final class TemplateRenderer
{
    /** Render teks bebas (hybrid). $data dikunci token (mis. ['total_sales'=>10138108, ...]). */
    public function renderText(ReportTemplate $template, array $data): string
    {
        $lines = [];

        foreach ($template->layout_json ?? [] as $block) {
            $type = $block['type'] ?? 'text';

            if (in_array($type, ['greeting', 'section', 'text'], true)) {
                $lines[] = $this->interpolate((string) ($block['text'] ?? ''), $data);

                continue;
            }

            if ($type === 'kv') {
                $token = $block['token'] ?? null;
                $val = $token ? ($data[$token] ?? null) : null;
                if ($this->isZeroOrEmpty($val)) {
                    continue; // hide-zero
                }
                $lines[] = ($block['label'] ?? $token).': '.$this->format($token, $val);

                continue;
            }

            if ($type === 'adjustment') {
                $adj = $data['penyesuaian_revenue'] ?? null;
                if (! $this->isZeroOrEmpty($adj)) {
                    $lines[] = (string) $adj; // blok opsional (OPS-403), hadir hanya bila ada koreksi
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Payload transport Opsi A: satu parameter besar untuk approved Meta template.
     *
     * @return array{fits:bool,text:string,params:array<string,string>,reason:?string}
     */
    public function forTransport(ReportTemplate $template, array $data, ?int $max = null): array
    {
        $max ??= (int) config('reporting.meta_param_max', 1024);
        $text = $this->renderText($template, $data); // isi IDENTIK dgn hybrid
        $fits = mb_strlen($text) <= $max;

        if (! $fits) {
            Log::channel('oms')->warning('template.transport_overflow', [
                'length' => mb_strlen($text),
                'max' => $max,
                'template_id' => $template->id,
            ]); // alert; pemanggil fallback ke hybrid
        }

        return [
            'fits' => $fits,
            'text' => $text,
            'params' => ['1' => $text],
            'reason' => $fits ? null : 'Konten melebihi kapasitas approved Meta template.',
        ];
    }

    private function interpolate(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($data) {
            $token = $m[1];

            return $this->format($token, $data[$token] ?? '');
        }, $text);
    }

    private function format(?string $token, mixed $val): string
    {
        if ($val === null) {
            return '';
        }
        if ($token === 'tanggal' && $val !== '') {
            return CarbonImmutable::parse((string) $val, 'Asia/Jakarta')->locale('id')->translatedFormat('d F Y');
        }
        if ($token !== null && TemplateTokens::isRupiah($token)) {
            return 'Rp'.number_format((float) $val, 0, ',', '.');
        }

        return (string) $val;
    }

    private function isZeroOrEmpty(mixed $val): bool
    {
        if ($val === null || $val === '') {
            return true;
        }

        return is_numeric($val) && (float) $val === 0.0;
    }
}
