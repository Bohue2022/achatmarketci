<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paliers d'abonnement (monétisation phase 2 — conçus dès maintenant)
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // Gratuit | Starter | Business | Premium
            $table->string('slug')->unique();
            $table->boolean('is_free')->default(false);
            $table->unsignedInteger('price_monthly')->default(0); // FCFA
            $table->unsignedInteger('price_yearly')->default(0);
            $table->unsignedInteger('active_announcement_limit')->nullable(); // null = illimité
            $table->unsignedInteger('listing_duration_days')->default(30);
            $table->json('features')->nullable();   // badge pro, page vitrine, stats, API...
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamp('trial_days')->nullable();
            $table->timestamps();
        });

        // Abonnements actifs des utilisateurs
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 20)->default('active'); // active | cancelled | past_due | expired
            $table->string('billing_cycle', 10)->default('monthly'); // monthly | yearly
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Référence paiement (agrégateur Mobile Money — provider abstrait défini, non codé en phase 1)
            $table->string('payment_provider')->nullable();   // cinetpay | paydunya | orange_money...
            $table->string('payment_reference')->nullable();
            $table->json('provider_meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
    }
};