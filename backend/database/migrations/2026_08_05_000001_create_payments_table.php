<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paiements (Mobile Money — mode mock/démo en phase courante)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30)->default('mock');  // mock | cinetpay | paydunya ...
            $table->string('transaction_id', 64)->unique();    // idempotence
            $table->unsignedInteger('amount');                 // FCFA
            $table->string('currency', 8)->default('XOF');
            $table->string('status', 20)->default('pending'); // pending | succeeded | failed | refunded
            $table->json('raw')->nullable();                  // payload agrégateur (webhook) si réel
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
