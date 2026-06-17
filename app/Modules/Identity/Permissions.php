<?php

namespace App\Modules\Identity;

/**
 * Katalog role & permission OMS (OPS-801, System Design §3.10).
 * Aksi sensitif di-gate lewat permission ini — domain memeriksa via IdentityProvider::can().
 */
final class Permissions
{
    // Aksi sensitif
    public const APPROVE_AND_SEND = 'deliver.approve_and_send'; // "Setujui & Kirim" (assisted)
    public const REVIEW_SIGNALS = 'signals.review';            // tindak lanjut signal_events
    public const EDIT_MASTER_DATA = 'master_data.edit';        // CRUD master data

    // Role aplikasi
    public const ROLE_ADMIN = 'admin';
    public const ROLE_HEAD_STORE = 'head_store';
    public const ROLE_AREA_MANAGER = 'area_manager';
    public const ROLE_OPS = 'ops';
    // Modul 2 (Finance) — rantai approval per band (System Design §4): AM→OM (LOW), OM→HoO (HIGH).
    public const ROLE_OPERATIONS_MANAGER = 'operations_manager';
    public const ROLE_HEAD_OF_OPERATIONS = 'head_of_operations';

    /** @return string[] */
    public static function all(): array
    {
        return [self::APPROVE_AND_SEND, self::REVIEW_SIGNALS, self::EDIT_MASTER_DATA];
    }

    /** @return string[] */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN, self::ROLE_HEAD_STORE, self::ROLE_AREA_MANAGER, self::ROLE_OPS,
            self::ROLE_OPERATIONS_MANAGER, self::ROLE_HEAD_OF_OPERATIONS,
        ];
    }

    /**
     * Scope outlet per role (OPS-802/OPS-1003):
     *  all = semua outlet (admin, tanpa assignment) · single = tepat 1 (head_store) · multi = ≥1.
     *
     * @return array<string, string>
     */
    public static function roleScopes(): array
    {
        return [
            self::ROLE_ADMIN => 'all',
            self::ROLE_HEAD_STORE => 'single',
            self::ROLE_AREA_MANAGER => 'multi',
            self::ROLE_OPS => 'multi',
            self::ROLE_OPERATIONS_MANAGER => 'all', // mengawasi jaringan (approval lintas outlet)
            self::ROLE_HEAD_OF_OPERATIONS => 'all',
        ];
    }

    public static function scopeFor(string $role): string
    {
        return self::roleScopes()[$role] ?? 'multi';
    }

    /**
     * Peta role → permission (kebijakan default; dapat disesuaikan via Admin nanti).
     *
     * @return array<string, string[]>
     */
    public static function rolePermissions(): array
    {
        return [
            self::ROLE_ADMIN => self::all(), // semua
            self::ROLE_HEAD_STORE => [self::APPROVE_AND_SEND, self::REVIEW_SIGNALS],
            self::ROLE_AREA_MANAGER => [self::REVIEW_SIGNALS],
            self::ROLE_OPS => [self::REVIEW_SIGNALS],
            // OM/HoO: approval Modul 2 via keanggotaan chain (M2-03), bukan permission Modul 1.
            self::ROLE_OPERATIONS_MANAGER => [],
            self::ROLE_HEAD_OF_OPERATIONS => [],
        ];
    }
}
