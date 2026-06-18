<?php

namespace App\Modules\Discipline;

/**
 * Skor ternormalisasi leaderboard (M3-05, System Design §6). Tiap metrik di-min-max ke 0..100
 * LINTAS outlet → outlet kecil tetap kompetitif (growth %, revenue PER KAPASITAS, kepatuhan) —
 * BUKAN revenue absolut. Metrik null (mis. kapasitas belum ada, OPS-1101) → dilewati; bobot
 * di-redistribusi ke metrik yang tersedia. Bobot configurable.
 */
final class NormalizedScorer
{
    public const METRICS = ['growth', 'revenue_per_capacity', 'compliance'];

    /**
     * @param  array<int,array{growth:?float,revenue_per_capacity:?float,compliance:?float}>  $rows  keyed id_outlet
     * @param  array<string,float>  $weights
     * @return array<int,array{score:float,components:array<string,?float>}>
     */
    public function compute(array $rows, array $weights): array
    {
        // Skala min-max per metrik lintas outlet (abaikan null).
        $scaled = [];
        foreach (self::METRICS as $m) {
            $vals = [];
            foreach ($rows as $id => $row) {
                $v = $row[$m] ?? null;
                if ($v !== null) {
                    $vals[$id] = (float) $v;
                }
            }
            $scaled[$m] = $this->minMax($vals);
        }

        $out = [];
        foreach ($rows as $id => $row) {
            $num = 0.0;
            $wsum = 0.0;
            $components = [];
            foreach (self::METRICS as $m) {
                $nv = $scaled[$m][$id] ?? null;
                $components[$m] = $nv;
                if ($nv !== null) {
                    $w = (float) ($weights[$m] ?? 0);
                    $num += $w * $nv;
                    $wsum += $w;
                }
            }
            $out[$id] = [
                'score' => $wsum > 0 ? round($num / $wsum, 2) : 0.0,
                'components' => $components,
            ];
        }

        return $out;
    }

    /** @param array<int,float> $vals @return array<int,float> 0..100 (semua sama → 100) */
    private function minMax(array $vals): array
    {
        if ($vals === []) {
            return [];
        }
        $min = min($vals);
        $max = max($vals);
        $out = [];
        foreach ($vals as $id => $v) {
            $out[$id] = $max === $min ? 100.0 : round(($v - $min) / ($max - $min) * 100, 2);
        }

        return $out;
    }
}
