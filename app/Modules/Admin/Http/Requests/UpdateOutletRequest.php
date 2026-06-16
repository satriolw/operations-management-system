<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi Edit Outlet (OPS-803). Gate: master_data.edit. Aturan emas: tanpa PII customer.
 */
class UpdateOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        // OPS-801 gate aksi sensitif. (Scoping per-outlet utk Area Manager = OPS-1003, menyusul.)
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
            'report_time' => ['required', 'date_format:H:i'],

            'checkpoints' => ['array'],
            'checkpoints.*.hour' => ['required', 'integer', 'between:0,23'],
            'checkpoints.*.threshold' => ['required', 'integer', 'between:0,100'],

            'operating_hours' => ['array'],
            'operating_hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'operating_hours.*.open' => ['required', 'date_format:H:i'],
            'operating_hours.*.close' => ['required', 'date_format:H:i'],

            'holidays' => ['array'],
            'holidays.*.date' => ['required', 'date_format:Y-m-d'],
            'holidays.*.note' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateCheckpointsUnique($v);
            $this->validateOperatingHours($v);
        });
    }

    private function validateCheckpointsUnique(Validator $v): void
    {
        $hours = collect($this->input('checkpoints', []))->pluck('hour');
        if ($hours->count() !== $hours->unique()->count()) {
            $v->errors()->add('checkpoints', 'Jam cek outlet-diam tidak boleh duplikat.');
        }
    }

    private function validateOperatingHours(Validator $v): void
    {
        $byDay = collect($this->input('operating_hours', []))->groupBy('weekday');

        foreach ($byDay as $weekday => $windows) {
            // close > open per jendela
            foreach ($windows as $i => $w) {
                if (isset($w['open'], $w['close']) && $w['close'] <= $w['open']) {
                    $v->errors()->add("operating_hours.{$i}.close", 'Jam tutup harus setelah jam buka.');
                }
            }

            // tumpang tindih antar jendela di hari yang sama
            $sorted = $windows->filter(fn ($w) => isset($w['open'], $w['close']))
                ->sortBy('open')->values();
            for ($j = 1; $j < $sorted->count(); $j++) {
                if ($sorted[$j]['open'] < $sorted[$j - 1]['close']) {
                    $v->errors()->add('operating_hours', "Jam operasional tumpang tindih pada hari {$weekday}.");
                    break;
                }
            }
        }
    }
}
