<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['activeSubscription.plan'])
            ->withCount([
                'announcements',
                'announcements as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'announcements as published_count' => fn ($q) => $q->where('status', 'published'),
            ]);

        if ($request->filled('role') && in_array($request->input('role'), Role::values(), true)) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(fn ($b) => $b->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('company_name', 'like', "%{$q}%"));
        }

        if ($request->filled('status')) {
            match ($request->input('status')) {
                'banned' => $query->whereNotNull('banned_at'),
                'verified_pro' => $query->where('is_verified_pro', true),
                default => null,
            };
        }

        $users = $query->latest()->paginate((int) $request->input('per_page', 20));

$data = $users->map(fn (User $u) => array_merge(
            $u->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro', 'company_name', 'rccm_number', 'kyc_verified_at']),
            [
                'is_banned' => $u->isBanned(),
                'banned_at' => $u->banned_at?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
                'announcements_count' => $u->announcements_count,
                'pending_count' => $u->pending_count,
                'published_count' => $u->published_count,
                'plan' => $u->activeSubscription?->plan?->slug,
                'subscription_status' => $u->activeSubscription?->status,
                'billing_cycle' => $u->activeSubscription?->billing_cycle,
                'subscription_ends_at' => $u->activeSubscription?->ends_at?->toIso8601String(),
            ]
        ));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['activeSubscription.plan', 'city']);

        return response()->json([
            'data' => array_merge(
                $user->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro', 'company_name', 'rccm_number', 'bio', 'kyc_verified_at', 'banned_at', 'ban_reason']),
                [
                    'plan' => $user->activeSubscription?->plan?->slug,
                    'city' => $user->city?->name,
                    'announcements_count' => $user->announcements()->count(),
                    'published_count' => $user->announcements()->where('status', 'published')->count(),
                ]
            ),
        ]);
    }

    /**
     * Bannir / suspendre avec motif.
     */
    public function ban(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $user->banned_at = now();
        $user->ban_reason = $data['reason'];
        $user->save();

        return response()->json(['message' => 'Compte banni.', 'data' => ['banned_at' => $user->banned_at]]);
    }

    public function unban(User $user): JsonResponse
    {
        $user->banned_at = null;
        $user->ban_reason = null;
        $user->save();

        return response()->json(['message' => 'Compte réactivé.']);
    }

    /**
     * Vérification KYC (identité pro) — validation manuelle.
     */
    public function verifyKyc(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'verified' => ['required', 'boolean'],
            'rccm_number' => ['nullable', 'string', 'max:40'],
        ]);

        $user->is_verified_pro = $data['verified'];
        $user->kyc_verified_at = $data['verified'] ? now() : null;
        $user->rccm_number = $data['rccm_number'] ?? $user->rccm_number;
        $user->save();

        return response()->json(['message' => 'Statut KYC mis à jour.']);
    }

    /**
     * Gestion manuelle d'abonnement (activation, prolongation).
     */
    public function setSubscription(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        UserSubscription::where('user_id', $user->id)->where('status', 'active')->update(['status' => 'cancelled']);

        $subscription = $user->subscriptions()->create([
            'plan_id' => $data['plan_id'],
            'status' => 'active',
            'billing_cycle' => $data['billing_cycle'],
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? now()->addMonth(),
            'payment_provider' => 'manual',
        ]);

        return response()->json([
            'message' => 'Abonnement activé manuellement.',
            'data' => $subscription->load('plan'),
        ], 201);
    }

    /**
     * Résilie l'abonnement actif d'un utilisateur (côté admin). */
    public function cancelSubscription(User $user): JsonResponse
    {
        try {
            $subscription = $this->subscriptions->cancel($user);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Abonnement de l\'utilisateur résilié.',
            'data' => ['subscription_id' => $subscription->id, 'status' => $subscription->status],
        ]);
    }
}