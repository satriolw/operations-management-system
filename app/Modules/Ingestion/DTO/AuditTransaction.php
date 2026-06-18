<?php

namespace App\Modules\Ingestion\DTO;

/**
 * Transaksi untuk audit anomali (OPS-1401, Epic N, System Design §3.17). Null-safe. MINIM-PII:
 * hanya referensi `transaction_number`/`id_customer` (aturan emas #3) — TANPA nama/telepon/alamat.
 * Field promo/payment/services/quantity/deposit untuk OPS-1402..1406.
 */
final class AuditTransaction
{
    /** @param array<string,mixed> $row */
    public function __construct(private readonly array $row) {}

    public static function fromRow(array $row): self
    {
        return new self($row);
    }

    public function transactionNumber(): ?string
    {
        return $this->row['transaction_number'] ?? null;
    }

    public function idCashier(): ?int
    {
        return isset($this->row['id_cashier']) ? (int) $this->row['id_cashier'] : null;
    }

    public function idCustomer(): ?int
    {
        return isset($this->row['customer']['id_customer']) ? (int) $this->row['customer']['id_customer']
            : (isset($this->row['id_customer']) ? (int) $this->row['id_customer'] : null);
    }

    public function grandTotal(): float
    {
        return (float) ($this->row['grand_total'] ?? 0);
    }

    /** @return array<int,array{name:string,amount:float}> */
    public function promos(): array
    {
        return collect($this->row['promos'] ?? [])->map(fn ($p) => [
            'name' => (string) ($p['name'] ?? $p['label'] ?? 'PROMO'),
            'amount' => (float) ($p['amount'] ?? $p['value'] ?? 0),
        ])->all();
    }

    public function promoTotal(array $whitelist = []): float
    {
        return collect($this->promos())
            ->reject(fn ($p) => in_array($p['name'], $whitelist, true))
            ->sum('amount');
    }

    /** @return array<int,array{amount:float,change_amount:float,method:?string,payment_proof:?string}> */
    public function payments(): array
    {
        return collect($this->row['payments'] ?? [])->map(fn ($p) => [
            'amount' => (float) ($p['amount'] ?? 0),
            'change_amount' => (float) ($p['change_amount'] ?? 0),
            'method' => $p['payment_method'] ?? $p['method'] ?? null,
            'payment_proof' => $p['payment_proof'] ?? null,
        ])->all();
    }

    /** @return array<int,array{price:float,list_price:?float,quantity:float,actual_quantity:?float}> */
    public function services(): array
    {
        return collect($this->row['services'] ?? [])->map(fn ($s) => [
            'price' => (float) ($s['price'] ?? 0),
            'list_price' => isset($s['service_data']['price']) ? (float) $s['service_data']['price']
                : (isset($s['service']['price']) ? (float) $s['service']['price'] : null),
            'quantity' => (float) ($s['quantity'] ?? 0),
            'actual_quantity' => isset($s['actual_quantity']) ? (float) $s['actual_quantity'] : null,
        ])->all();
    }

    /** @return array{balance:?float,active_until:?string,status:?string,id_customer_group:?int} */
    public function deposit(): array
    {
        $c = $this->row['customer'] ?? [];

        return [
            'balance' => isset($c['deposit_balance']) ? (float) $c['deposit_balance'] : null,
            'active_until' => $c['deposit_active_until'] ?? null,
            'status' => $c['deposit_status'] ?? null,
            'id_customer_group' => isset($c['id_customer_group']) ? (int) $c['id_customer_group'] : null,
        ];
    }
}
