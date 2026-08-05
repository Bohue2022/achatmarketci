<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logique métier des abonnements.
 *
 * Paiement en MODE DÉMO/MOCK : un abonnement "pending" + un Payment "pending"
 * sont créés, puis $confirm() simule le succès d'un agrégateur Mobile Money.
 * Le branchement réel (CinetPay/PayDunya) se fera dans le webhook [docs/PAIEMENTS].
 */
class SubscriptionService
{
    private const FRONTEND_URL = 'http://127.0.0.1:3000';

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY = 'yearly';

    /**
     * Démarre une demande d'abonnement (création d'un paiement en attente, mode démo).
     *
     * @return array{payment: Payment, mock_payment_url: string}
     * @throws ValidationException
     */
    public function start(User $user, Plan $plan, string $billingCycle): array
    {
        if (!$plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'Ce plan n\'est plus disponible.']);
        }
        if ($plan->is_free) {
            throw ValidationException::withMessages(['plan_id' => 'Sélectionnez une formule payante.']);
        }
        if (!in_array($billingCycle, [self::CYCLE_MONTHLY, self::CYCLE_YEARLY], true)) {
            throw ValidationException::withMessages(['billing_cycle' => 'Cycle de facturation invalide.']);
        }

        // Idempotence : retourner la demande en cours si elle n'est pas encore confirmée
        $existingPending = Payment::query()
            ->join('user_subscriptions', 'user_subscriptions.id', 'payments.user_subscription_id')
            ->where('payments.user_id', $user->id)
            ->where('payments.status', Payment::STATUS_PENDING)
            ->where('user_subscriptions.plan_id', $plan->id)
            ->where('user_subscriptions.billing_cycle', $billingCycle)
            ->first('payments.*');

        if ($existingPending) {
            $subscription = $existingPending->subscription;
            return [
                'payment' => $existingPending,
                'mock_payment_url' => self::mockUrl($existingPending),
            ];
        }

        // Déjà abonné à ce plan, même cycle, actif → refus
        $alreadyActive = $this->currentActiveSubscription($user);
        if ($alreadyActive && $alreadyActive->plan_id === $plan->id && $alreadyActive->billing_cycle === $billingCycle) {
            throw ValidationException::withMessages(['plan_id' => 'Vous êtes déjà abonné à cette formule.']);
        }

        $subscription = new UserSubscription();
        $subscription->user_id = $user->id;
        $subscription->plan_id = $plan->id;
        $subscription->status = 'active';
        $subscription->billing_cycle = $billingCycle;
        $subscription->starts_at = now();
        $subscription->ends_at = $this->computeEndsAt($billingCycle);
        $subscription->payment_provider = 'mock';
        $subscription->save();

        $payment = new Payment();
        $payment->user_id = $user->id;
        $payment->user_subscription_id = $subscription->id;
        $payment->provider = 'mock';
        $payment->transaction_id = 'mock_' . Str::uuid();
        $payment->amount = (int) ($billingCycle === self::CYCLE_YEARLY ? $plan->price_yearly : $plan->price_monthly);
        $payment->currency = 'XOF';
        $payment->status = Payment::STATUS_PENDING;
        $payment->save();

        return [
            'payment' => $payment,
            'mock_payment_url' => self::mockUrl($payment),
        ];
    }

    /**
     * Confirme le paiement (démo) et active l'abonnement associé.
     * Annule tout autre abonnement actif du même utilisateur (changement de formule).
     */
    public function confirm(Payment $payment): UserSubscription
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw ValidationException::withMessages(['payment' => 'Paiement déjà traité.']);
        }

        /** @var UserSubscription $subscription */
        $subscription = $payment->subscription;

        DB::transaction(function () use ($payment, $subscription) {
            // Changer de formule : on résilie les autres abonnements actifs, puis on active
            UserSubscription::where('user_id', $subscription->user_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'ends_at' => now(),
                ]);

            $subscription->status = 'active';
            $subscription->starts_at = now();
            $subscription->ends_at = $this->computeEndsAt($subscription->billing_cycle);
            $subscription->cancelled_at = null;
            $subscription->payment_provider = 'mock';
            $subscription->payment_reference = $payment->transaction_id;
            $subscription->save();

            $payment->status = Payment::STATUS_SUCCEEDED;
            $payment->paid_at = now();
            $payment->save();

            $user = $subscription->user;
            if (!$user->isPro()) {
                $user->role = Role::Pro;
                $user->save();
            }
        });

        return $subscription->load('plan');
    }

    /** Refuse/décline le paiement démo : le paiement passe "failed", l'abonnement "cancelled". */
    public function decline(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw ValidationException::withMessages(['payment' => 'Paiement déjà traité.']);
        }

        $payment->status = Payment::STATUS_FAILED;
        $payment->save();

        $subscription = $payment->subscription;
        if ($subscription && $subscription->status === 'active') {
            $subscription->status = 'cancelled';
            $subscription->cancelled_at = now();
            $subscription->ends_at = now();
            $subscription->save();
        }
    }

    /**
     * Résilie l'abonnement actif de l'utilisateur.
     * Un pro non vérifié (devenu pro via abonnement) revient au statut particulier (quota gratuit).
     */
    public function cancel(User $user): UserSubscription
    {
        $active = $this->currentActiveSubscription($user);

        if (!$active) {
            throw ValidationException::withMessages(['subscription' => 'Aucun abonnement actif à résilier.']);
        }

        $active->status = 'cancelled';
        $active->cancelled_at = now();
        $active->ends_at = now();
        $active->save();

        // Rétrogradation : seuls les pros VÉRIFIÉS conservent leur statut professionnel.
        // On recharge l'instance pour ne pas dépendre d'un rôle périmé en mémoire.
        $user->refresh();
        if ($active->plan && !$active->plan->is_free && !$user->is_verified_pro) {
            $user->role = Role::User;
            $user->save();
        }

        return $active->load('plan');
    }

    private function computeEndsAt(string $cycle): \Illuminate\Support\Carbon
    {
        return $cycle === self::CYCLE_YEARLY ? now()->addYear() : now()->addMonth();
    }

    /**
     * Abonnement actif courant (requête directe, pour éviter le cache de relation
     * d'une instance d'User déjà utilisée dans la requête).
     */
    private function currentActiveSubscription(User $user): ?UserSubscription
    {
        return UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('id')
            ->first();
    }

    private static function mockUrl(Payment $payment): string
    {
        return rtrim(self::FRONTEND_URL, '/') . '/abonnement/payer/' . $payment->id;
    }
}