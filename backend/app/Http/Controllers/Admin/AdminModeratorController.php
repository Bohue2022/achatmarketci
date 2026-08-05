<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Gestion des comptes modérateurs dédiés (admin uniquement).
 * Un modérateur est un compte créé avec ses propres identifiants,
 * distinct des comptes utilisateurs/vendeurs du site.
 */
class AdminModeratorController extends Controller
{
    public function index(): JsonResponse
    {
        $moderators = User::where('role', Role::Moderator)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $u) => $u->only(['id', 'name', 'email', 'phone', 'created_at']));

        return response()->json(['data' => $moderators]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^[+0-9 ]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->symbols()],
        ]);

        $moderator = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => Role::Moderator,
        ]);

        return response()->json([
            'message' => 'Compte modérateur créé. Les identifiants sont le mail et le mot de passe fournis.',
            'data' => $moderator->only(['id', 'name', 'email', 'phone', 'role', 'created_at']),
        ], 201);
    }

    /**
     * Désactive le compte modérateur (soft delete) : plus de connexion possible,
     * l'historique de ses actions de modération est conservé.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->role !== Role::Moderator) {
            return response()->json(['message' => 'Cet utilisateur n\'est pas modérateur.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Compte modérateur retiré.']);
    }
}
