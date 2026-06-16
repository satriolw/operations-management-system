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
    private const MIN_GAP_MINUTES = 30;

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
            'silent_threshold_pct' => ['required', 'integer', 'between:0,100'],
            'comparison_basis' => ['required', 'in:avg_14d,avg_30d,same_dow'],

            'checkpoints' => ['array'],
            'checkpoints.*.time' => ['required', 'date_format:H:i'],

            'operating_hours' => ['array'],
            'operating_hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'operating_hours.*.is_closed' => ['nullable', 'boolean'],
            'operating_hours.*.open' => ['nullable', 'required_if:operating_hours.*.is_closed,0,false', 'date_format:H:i'],
            'operating_hours.*.close' => ['nullable', 'required_if:operating_hours.*.is_closed,0,false', 'date_format:H:i'],

            'holidays' => ['array'],
            'holidays.*.date' => ['required', 'date_format:Y-m-d'],
            'holidays.*.note' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->validateCheckTimeGaps($v);
            $this->validateOperatingHours($v);
        });
    }

    /** Jam cek wajib unik & berjarak >= 30 menit (state "jam tumpang tindih"). */
    private function validateCheckTimeGaps(Validator $v): void
    {
        $mins = collect($this->input('checkpoints', []))
            ->pluck('time')
            ->filter()
            ->map(fn ($t) => $this->toMinutes($t))
            ->sort()
            ->values();

        for ($i = 1; $i < $mins->count(); $i++) {
            $gap = $mins[$i] - $mins[$i - 1];
            if ($gap < self::MIN_GAP_MINUTES) {
                $v->errors()->add('checkpoints',
                    "Jam cek tumpang tindih: hanya berjarak {$gap} menit. Setiap jam cek harus unik & berjarak minimal 30 menit.");

                return;
            }
        }
    }

    private function validateOperatingHours(Validator $v): void
    {
        foreach ($this->input('operating_hours', []) as $i => $w) {
            $closed = filter_var($w['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($closed) {
                continue;
            }
            if (isset($w['open'], $w['close']) && $w['close'] <= $w['open']) {
                $v->errors()->add("operating_hours.{$i}.close", 'Jam tutup harus setelah jam buka.');
            }
        }
    }

    private function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }
}
