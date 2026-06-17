<?php

namespace App\Modules\Finance;

use App\Models\DocNumberSequence;
use App\Models\FinancialDocument;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generator doc_number (M2-04, System Design §3.2):
 *   YYMMDD-{BRAND}{OUTLET2|HO}/{TYPE}/{DIV}/{SEQ3}   mis. 260610-LW07/RF/OPS/001
 * SEQ reset BULANAN, atomik per (brand, outlet_or_ho, doc_type, period=YYYY-MM) via lockForUpdate.
 */
final class DocNumberGenerator
{
    public function generate(FinancialDocument $doc, ?CarbonInterface $date = null): string
    {
        $date = $date ? Wib::normalize($date) : Wib::normalize(now());
        $brand = $doc->brand;
        $outletOrHo = $this->outletCode($doc);
        $division = (string) config('finance.division', 'OPS');
        $typeCode = config('finance.type_codes')[$doc->doc_type] ?? 'XX';
        $period = $date->format('Y-m');

        $seq = DB::transaction(function () use ($brand, $outletOrHo, $doc, $period) {
            $row = DocNumberSequence::query()
                ->where(['brand' => $brand, 'outlet_or_ho' => $outletOrHo, 'doc_type' => $doc->doc_type, 'period' => $period])
                ->lockForUpdate()->first();

            if ($row === null) {
                $row = DocNumberSequence::create([
                    'brand' => $brand, 'outlet_or_ho' => $outletOrHo, 'doc_type' => $doc->doc_type,
                    'period' => $period, 'last_seq' => 0,
                ]);
            }

            $row->increment('last_seq');

            return (int) $row->last_seq;
        });

        return sprintf('%s-%s%s/%s/%s/%03d', $date->format('ymd'), $brand, $outletOrHo, $typeCode, $division, $seq);
    }

    /** Head Office → 'HO'; selain itu kode 2-digit dari peta (Lampiran A), fallback 2-digit id_outlet. */
    private function outletCode(FinancialDocument $doc): string
    {
        if ($doc->scope === 'HEAD_OFFICE' || $doc->id_outlet === null) {
            return 'HO';
        }

        $map = (array) config('finance.outlet_codes', []);
        if (isset($map[$doc->id_outlet])) {
            return (string) $map[$doc->id_outlet];
        }

        Log::channel('oms')->warning('finance.outlet_code_missing', ['id_outlet' => $doc->id_outlet]);

        return str_pad((string) ((int) $doc->id_outlet % 100), 2, '0', STR_PAD_LEFT);
    }
}
