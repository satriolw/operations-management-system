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

        $lines = $this->itemized($data['doc_type']) ? ($data['lines'] ?? []) : [];
        $amount = isset($data['amount'])
            ? (float) $data['amount']
            : array_sum(array_map(fn ($l) => (float) ($l['amount'] ?? 0), $lines));

        return DB::transaction(function () use ($data, $requester, $scope, $idOutlet, $lines, $amount, $date) {
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
                'payload_json' => $data['payload'] ?? null,
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
