<?php

namespace App\Services;

use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Génère, stocke (haché) et vérifie les codes OTP de vérification e-mail (6 chiffres).
 */
class OtpService
{
    public function generate(): string
    {
        $static = config('otp.static');

        return $static ? (string) $static : (string) random_int(100000, 999999);
    }

    /**
     * Génère un code pour l'utilisateur, invalide les précédents et le retourne (en clair, pour l'envoi).
     */
    public function create(User $user): string
    {
        EmailVerificationCode::where('user_id', $user->id)->delete();

        $code = $this->generate();

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'code_hash' => bcrypt($code),
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes')),
        ]);

        return $code;
    }

    /**
     * Vérifie le code soumis : correct et non expiré ?
     */
    public function verify(User $user, string $code): bool
    {
        $record = EmailVerificationCode::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record) {
            return false;
        }

        return password_verify($code, $record->code_hash);
    }

    public function purge(User $user): void
    {
        EmailVerificationCode::where('user_id', $user->id)->delete();
    }
}