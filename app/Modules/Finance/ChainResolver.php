<?php

namespace App\Modules\Finance;

use App\Models\ApprovalChain;
use App\Models\FinancialDocument;
use App\Models\User;
use App\Modules\Finance\Exceptions\ApprovalException;
use App\Modules\Identity\Permissions;

/**
 * Resolusi rantai approval efektif untuk satu dokumen (M2-03, System Design §4). Pilih rantai per
 * band+scope (doc_type spesifik > generik/null), lalu terapkan reviewer≠requester: bila pengaju
 * menempati level role-based, level itu di-SKIP dan chain DIGESER NAIK di ladder peran sampai tetap
 * ada approver distinct (boleh tinggal 1 bila lewat puncak). Level user-pinned: skip bila = pengaju.
 */
final class ChainResolver
{
    /** Ladder peran ops (rank menanjak) untuk skip+geser. */
    private const LADDER = [
        Permissions::ROLE_HEAD_STORE => 0,
        Permissions::ROLE_AREA_MANAGER => 1,
        Permissions::ROLE_OPERATIONS_MANAGER => 2,
        Permissions::ROLE_HEAD_OF_OPERATIONS => 3,
    ];

    /**
     * @return array<int,array{role:?string,user_id:?int}> level efektif terurut (index 0 = level 1)
     */
    public function resolve(FinancialDocument $doc): array
    {
        $configured = $this->configuredChain($doc);
        if ($configured === []) {
            throw ApprovalException::noChain();
        }

        $requesterRank = $this->rankOfUser($doc->requester);
        $effective = [];

        foreach ($configured as $spec) {
            if ($spec['user_id'] !== null) {
                if ((int) $spec['user_id'] !== (int) $doc->requester_user_id) {
                    $effective[] = $spec; // user-pinned: pertahankan kecuali = pengaju
                }

                continue;
            }
            // role-based: pertahankan hanya bila rank di atas pengaju (selain itu di-geser via refill)
            if (($this->LADDER_rank($spec['role'])) > $requesterRank) {
                $effective[] = $spec;
            }
        }

        $this->refillUpward($effective, count($configured), $requesterRank);

        return array_values($effective);
    }

    /** @return array<int,array{role:?string,user_id:?int}> */
    private function configuredChain(FinancialDocument $doc): array
    {
        $band = $doc->amount_band ?: FinancialDocument::bandFor((float) $doc->amount);

        $base = ApprovalChain::query()->where('amount_band', $band)->where('scope', $doc->scope);

        // Spesifik per doc_type menang; jika tak ada, pakai generik (doc_type null).
        $specific = (clone $base)->where('doc_type', $doc->doc_type)->orderBy('level')->get();
        $rows = $specific->isNotEmpty() ? $specific : (clone $base)->whereNull('doc_type')->orderBy('level')->get();

        return $rows->map(fn (ApprovalChain $c) => [
            'role' => $c->approver_role,
            'user_id' => $c->approver_user_id !== null ? (int) $c->approver_user_id : null,
        ])->all();
    }

    /** Isi ulang level role-based yang ter-skip dengan peran lebih tinggi (jaga jumlah level). */
    private function refillUpward(array &$effective, int $needed, int $requesterRank): void
    {
        if (count($effective) >= $needed) {
            return;
        }

        $topRank = $requesterRank;
        foreach ($effective as $spec) {
            if ($spec['role'] !== null) {
                $topRank = max($topRank, $this->LADDER_rank($spec['role']));
            }
        }

        // tambah peran di atas topRank, menanjak, sampai jumlah terpenuhi atau ladder habis
        $ladder = self::LADDER;
        asort($ladder);
        foreach ($ladder as $role => $rank) {
            if (count($effective) >= $needed) {
                break;
            }
            if ($rank > $topRank && ! $this->hasRole($effective, $role)) {
                $effective[] = ['role' => $role, 'user_id' => null];
                $topRank = $rank;
            }
        }
    }

    private function hasRole(array $effective, string $role): bool
    {
        foreach ($effective as $spec) {
            if ($spec['role'] === $role) {
                return true;
            }
        }

        return false;
    }

    private function rankOfUser(?User $user): int
    {
        if ($user === null) {
            return -1;
        }
        $max = -1;
        foreach ($user->getRoleNames() as $role) {
            $max = max($max, self::LADDER[$role] ?? -1);
        }

        return $max;
    }

    private function LADDER_rank(?string $role): int
    {
        return self::LADDER[$role] ?? -1;
    }
}
