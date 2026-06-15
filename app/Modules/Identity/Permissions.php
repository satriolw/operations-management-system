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

    /** @return string[] */
    public static function all(): array
    {
        return [self::APPROVE_AND_SEND, self::REVIEW_SIGNALS, self::EDIT_MASTER_DATA];
    }

    /** @return string[] */
    public static function roles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_HEAD_STORE, self::ROLE_AREA_MANAGER, self::ROLE_OPS];
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
        ];
    }
}
