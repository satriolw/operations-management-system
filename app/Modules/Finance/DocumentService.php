<?php

namespace App\Modules\Finance;

use App\Models\FinancialDocument;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembuatan dokumen keuangan 5 jenis (M2-04, System Design §3.1). Satu model, field umum reusable +
 * spesifik di payload_json + line items (PR/RE/CA/ER). ER = realisasi CA (running balance + parent).
 * Refund = Berita Acara (nevira_transaction_number REFERENSI + PII di payload). Scoping per-outlet
 * (OPS-1003): pengaju hanya boleh membuat untuk outlet yang dapat diaksesnya.
 */
final class DocumentService
{
    /** Jenis yang punya line items. Refund (Berita Acara) tidak. */
    private const ITEMIZED = ['PAYMENT_REQUEST', 'REIMBURSE', 'CASH_ADVANCE', 'EXPENSE_REPORT'];

    public function __construct(private readonly DocNumberGenerator $numbers) {}

    /**
     * @param  array<string,mixed>  $data  doc_type, brand, id_outlet?, scope?, title, amount?, cost_center?,
     *                                     currency?, payload?, lines?, parent_document_id?, nevira_transaction_number?
     */
    public function create(array $data, User $requester, ?CarbonInterface $date = null): FinancialDocument
    {
        $scope = $data['scope'] ?? 'OUTLET';
        $idOutlet = $scope === 'HEAD_OFFICE' ? null : ($data['id_outlet'] ?? null);

        $this->assertCanCreate($requester, $scope, $idOutlet);

        $lines = $this->itemized($data['doc_type']) ? array_values($data['lines'] ?? []) : [];
        $amount = isset($data['amount'])
            ? (float) $data['amount']
            : array_sum(array_map(fn ($l) => (float) ($l['amount'] ?? 0), $lines));

        // ER = rekonsiliasi CA: hitung running balance TURUNAN (CA − kumulatif) + sisa (CA Lebih/Kurang).
        $payload = $data['payload'] ?? [];
        [$lines, $payload] = $this->reconcileExpenseReport($data, $lines, $amount, $payload);

        return DB::transaction(function () use ($data, $requester, $scope, $idOutlet, $lines, $amount, $date, $payload) {
            $doc = new FinancialDocument([
                'doc_type' => $data['doc_type'],
                'brand' => $data['brand'],
                'id_outlet' => $idOutlet,
                'scope' => $scope,
                'requester_user_id' => $requester->id,
                'title' => $data['title'] ?? '',
                'amount' => $amount,
                'amount_band' => FinancialDocument::bandFor($amount),
                'cost_center' => $data['cost_center'] ?? null,
                'currency' => $data['currency'] ?? 'IDR',
                'status' => FinancialDocument::STATUS_DRAFT,
                'current_level' => 0,
                'parent_document_id' => $data['parent_document_id'] ?? null,
                'nevira_transaction_number' => $data['nevira_transaction_number'] ?? null,
                'payload_json' => $payload !== [] ? $payload : null,
            ]);
            $doc->save();

            $doc->doc_number = $this->numbers->generate($doc, $date);
            $doc->save();

            foreach (array_values($lines) as $i => $line) {
                $doc->lines()->create([
                    'description' => $line['description'] ?? '',
                    'merk_type' => $line['merk_type'] ?? null,
                    'qty' => $line['qty'] ?? 1,
                    'unit_price' => $line['unit_price'] ?? 0,
                    'amount' => $line['amount'] ?? (($line['qty'] ?? 1) * ($line['unit_price'] ?? 0)),
                    'balance' => $line['balance'] ?? null, // ER running balance
                    'sort_order' => $i,
                ]);
            }

            return $doc->refresh();
        });
    }

    private function itemized(string $docType): bool
    {
        return in_array($docType, self::ITEMIZED, true);
    }

    /**
     * ER (System Design §3.1): running balance per baris = saldo CA − realisasi kumulatif (TURUNAN,
     * bukan input); sisa = CA − total realisasi → negatif "CA Kurang" (reimburse karyawan),
     * positif "CA Lebih" (kembali ke perusahaan). Non-ER / tanpa parent → tak diubah.
     *
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>}
     */
    private function reconcileExpenseReport(array $data, array $lines, float $amount, array $payload): array
    {
        if ($data['doc_type'] !== 'EXPENSE_REPORT' || empty($data['parent_document_id'])) {
            return [$lines, $payload];
        }

        $parent = FinancialDocument::find($data['parent_document_id']);
        if ($parent === null) {
            return [$lines, $payload];
        }

        $ca = (float) $parent->amount;
        $remaining = $ca;
        foreach ($lines as $k => $line) {
            $remaining = round($remaining - (float) ($line['amount'] ?? 0), 2);
            $lines[$k]['balance'] = $remaining; // override input → turunan deterministik
        }

        $sisa = round($ca - $amount, 2);
        $payload['ca_amount'] = $ca;
        $payload['sisa'] = $sisa;
        $payload['sisa_label'] = $sisa < 0 ? 'CA Kurang' : 'CA Lebih';

        return [$lines, $payload];
    }

    private function assertCanCreate(User $requester, string $scope, ?int $idOutlet): void
    {
        $ok = $scope === 'HEAD_OFFICE'
            ? $requester->canAccessAllOutlets()
            : ($idOutlet !== null && $requester->canAccessOutlet($idOutlet));

        if (! $ok) {
            throw new RuntimeException('Scoping: pengaju tak punya akses ke outlet/scope dokumen ini.');
        }
    }
}
