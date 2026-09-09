<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Verifica la "clave de autorización" (PIN) que un administrador carga en su
 * usuario para aprobar operaciones sensibles hechas por otro empleado — hoy,
 * cancelar la cuenta de una mesa.
 *
 * El PIN se guarda hasheado en `users.pin_hash` (nunca viaja al espejo). Como
 * no se puede revertir el hash, la verificación prueba el PIN contra cada
 * administrador activo del negocio que tenga clave cargada.
 */
class ManagerPinService
{
    public const AUTHORIZING_PERMISSION = 'ventas.cancelar_cuenta';

    /**
     * Devuelve el usuario administrador cuyo PIN coincide, o null si ninguno
     * coincide (clave incorrecta) o si no hay administradores con clave.
     */
    public function resolveAuthorizer(int $businessId, string $pin): ?User
    {
        $pin = trim($pin);

        if ($pin === '') {
            return null;
        }

        $candidates = User::query()
            ->permission(self::AUTHORIZING_PERMISSION)
            ->whereNotNull('pin_hash')
            ->where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('business_id', $businessId))
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($pin, $candidate->pin_hash)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * ¿Hay al menos un administrador del negocio con clave de autorización
     * cargada? Si no, la cancelación con clave es imposible y conviene avisarlo.
     */
    public function hasAuthorizer(int $businessId): bool
    {
        return User::query()
            ->permission(self::AUTHORIZING_PERMISSION)
            ->whereNotNull('pin_hash')
            ->where('is_active', true)
            ->whereHas('branch', fn ($q) => $q->where('business_id', $businessId))
            ->exists();
    }
}
