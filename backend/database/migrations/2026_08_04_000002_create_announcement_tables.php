<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Annonces — cœur du produit
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained();
            $table->foreignId('model_id')->constrained('vehicle_models');
            $table->foreignId('city_id')->constrained();
            $table->unsignedBigInteger('commune_id')->nullable();

            $table->string('title');
            $table->string('slug')->nullable()->unique();
            $table->text('description');

            $table->unsignedBigInteger('price'); // FCFA
            $table->string('currency', 3)->default('XOF');

            $table->smallInteger('year')->nullable();
            $table->unsignedInteger('mileage')->nullable(); // km

            $table->string('fuel_type', 20); // essence | diesel | hybride | electrique
            $table->string('transmission', 20); // manuelle | automatique
            $table->string('condition', 20); // neuf | occasion
            $table->string('body_type', 30)->nullable(); // berline | suv | pickup ...

            // Statut local important
            $table->boolean('is_dedouane')->default(false);
            $table->boolean('has_grise')->default(false);
            $table->string('origin', 60)->nullable(); // importe_ue | importe_asia | local

            // Fiche technique
            $table->unsignedInteger('engine_cc')->nullable();
            $table->unsignedInteger('power_hp')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedTinyInteger('number_of_owners')->nullable();
            $table->string('transmission_defect')->nullable();
            $table->text('equipment')->nullable(); // json

            // Cycle de vie / modération
            $table->string('status', 20)->default('draft');
            // draft | pending | published | rejected | expired | suspended
            $table->text('rejection_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('moderated_at')->nullable();

            // Stats & mise en avant
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('contacts_count')->default(0);
            $table->boolean('featured')->default(false);
            $table->boolean('boosted')->default(false);
            $table->timestamp('boost_expires_at')->nullable();

            $table->timestamps();

            $table->foreign('commune_id')->references('id')->on('cities')->nullOnDelete();

            $table->index(['status', 'published_at']);
            $table->index(['city_id', 'status']);
            $table->index('price');
            $table->index('featured');
        });

        // Photos de l'annonce
        Schema::create('announcement_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // chemin stockage S3 / local
            $table->string('disk', 20)->default('public');
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });

        // Traçabilité de la modération
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20); // approved | rejected | request_changes | on_hold
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // Signalements (fraude, doublon, prix suspect...)
        Schema::create('announcement_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open'); // open | under_review | resolved | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'reporter_id', 'reason']);
        });

        // Favoris
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'announcement_id']);
        });

        // Prises de contact (messagerie évoluée en phase 3)
        Schema::create('announcement_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->string('channel', 20)->default('form'); // form | whatsapp
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_contacts');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('announcement_reports');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('announcement_photos');
        Schema::dropIfExists('announcements');
    }
};