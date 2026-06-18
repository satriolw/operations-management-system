<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;

/**
 * Template checklist default grup (M3-01). id_outlet null = diwarisi semua outlet; Ops sesuaikan
 * via Admin. Idempoten (firstOrCreate). Item generik harian (sebagian wajib foto, anti-palsu M3-02).
 */
class DefaultChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $template = ChecklistTemplate::firstOrCreate(
            ['id_outlet' => null, 'name' => 'Checklist Harian Default'],
            ['schedule' => 'daily', 'active' => true],
        );

        $items = [
            ['label' => 'Buka outlet', 'requires_photo' => true, 'order' => 1],
            ['label' => 'Kebersihan area', 'requires_photo' => true, 'order' => 2],
            ['label' => 'Cek mesin', 'requires_photo' => true, 'order' => 3],
            ['label' => 'Stok bahan', 'requires_photo' => false, 'order' => 4],
        ];

        foreach ($items as $i) {
            ChecklistItem::firstOrCreate(
                ['template_id' => $template->id, 'label' => $i['label']],
                ['requires_photo' => $i['requires_photo'], 'order' => $i['order']],
            );
        }
    }
}
