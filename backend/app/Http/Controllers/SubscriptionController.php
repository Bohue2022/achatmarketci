<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /** Offre (public) : liste des formules actives. */
    public function plans(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->get()
            ->map(fn (Plan $p) => array_merge(
                $p->only(['id', 'name', 'slug', 'is_free', 'price_monthly', 'price_yearly', 'active_announcement_limit', 'listing_duration_days', 'features']),
                [
                    'announcement_limit_label' => $p->active_announcement_limit === null ? 'Illimité' : (string) $p->active_announcement_limit,
                ]
            ));

        return response()->json(['data' => $plans]);
    }

    /** Mon abonnement (auth) : actif + historique + statistiques. */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['activeSubscription.plan']);

        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->latest()
            ->get()
            ->map(fn (UserSubscription $s) => $this->subscriptionPayload($s));

        return response()->json([
            'data' => [
                'active' => $this->subscriptionPayload($user->activeSubscription),
                'history' => $subscriptions,
                'active_announcement_limit' => $user->activeAnnouncementLimit(),
                'listing_duration_days' => $user->listingDurationDays(),
            ],
        ]);
    }

    /**
     * Demande d'abonnement / changement de formule (démo).
     * Crée un paiement en attente et renvoie une URL de checkout mock.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        try {
            $result = $this->subscriptions->start($request->user(), $plan, $data['billing_cycle']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Demande de paiement initiée (mode démo).',
            'data' => [
                'payment' => $this->paymentPayload($result['payment']),
                'mock_payment_url' => $result['mock_payment_url'],
            ],
        ], 201);
    }

    /** Confirme le paiement démo et active l'abonnement. */
    public function mockConfirm(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePayment($request, $payment);

        try {
            $subscription = $this->subscriptions->confirm($payment);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Paiement confirmé, abonnement activé.',
            'data' => ['subscription' => $this->subscriptionPayload($subscription)],
        ]);
    }

    /** Annule le paiement démo en attente. */
    public function mockDecline(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePayment($request, $payment);

        try {
            $this->subscriptions->decline($payment);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Paiement annulé.']);
    }

    /** Résilie mon abonnement actif. */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $subscription = $this->subscriptions->cancel($request->user());
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Abonnement résilié. Votre quota revient au palier gratuit.',
            'data' => ['subscription' => $this->subscriptionPayload($subscription)],
        ]);
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        abort_if($payment->user_id !== $request->user()->id, 403, 'Paiement introuvable.');
    }

    private function subscriptionPayload(?UserSubscription $s): ?array
    {
        if (!$s) {
            return null;
        }
        return [
            'id' => $s->id,
            'plan_id' => $s->plan_id,
            'plan' => $s->plan ? array_merge(
                $s->plan->only(['name', 'slug', 'is_free', 'price_monthly', 'price_yearly', 'active_announcement_limit']),
                ['announcement_limit_label' => $s->plan->active_announcement_limit === null ? 'Illimité' : (string) $s->plan->active_announcement_limit]
            ) : null,
            'status' => $s->status,
            'billing_cycle' => $s->billing_cycle,
            'starts_at' => $s->starts_at?->toISOString(),
            'ends_at' => $s->ends_at?->toISOString(),
            'cancelled_at' => $s->cancelled_at?->toISOString(),
        ];
    }

    private function paymentPayload(Payment $p): array
    {
        return [
            'id' => $p->id,
            'provider' => $p->provider,
            'transaction_id' => $p->transaction_id,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'created_at' => $p->created_at?->toISOString(),
        ];
    }
}