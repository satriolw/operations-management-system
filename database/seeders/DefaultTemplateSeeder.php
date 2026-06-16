<?php

namespace Database\Seeders;

use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

/**
 * OPS-901 · seed satu template master default agar pipeline laporan bisa jalan
 * tanpa menunggu builder (OPS-902). Idempoten.
 */
class DefaultTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ReportTemplate::query()->firstOrCreate(
            ['scope' => ReportTemplate::SCOPE_MASTER, 'name' => 'Laporan Harian (default)'],
            ['active' => true, 'layout_json' => self::defaultLayout()],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function defaultLayout(): array
    {
        return [
            ['type' => 'greeting', 'text' => 'Halo {{nama_investor}}, berikut ringkasan {{nama_outlet}} · {{tanggal}}.'],
            ['type' => 'section', 'text' => 'PENJUALAN'],
            ['type' => 'kv', 'label' => 'Total penjualan', 'token' => 'total_sales'],
            ['type' => 'kv', 'label' => 'Terealisasi', 'token' => 'realized'],
            ['type' => 'kv', 'label' => 'Piutang', 'token' => 'piutang'],
            ['type' => 'kv', 'label' => 'Jumlah transaksi', 'token' => 'txn_count'],
            ['type' => 'kv', 'label' => 'Rata-rata/transaksi', 'token' => 'avg_transaction'],
            ['type' => 'section', 'text' => 'VOLUME'],
            ['type' => 'kv', 'label' => 'Kg', 'token' => 'volume_kg'],
            ['type' => 'kv', 'label' => 'Pcs', 'token' => 'volume_pcs'],
            ['type' => 'adjustment', 'token' => 'penyesuaian_revenue'], // blok opsional (OPS-403)
        ];
    }
}
