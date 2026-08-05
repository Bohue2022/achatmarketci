<?php

use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\AdminAnnouncementController;
use App\Http\Controllers\Admin\AdminModeratorController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API — Documentation : voir docs/api.md
|--------------------------------------------------------------------------
*/

// --- Santé / publiques ---
Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

// Référentiels (public, souvent mis en cache)
Route::get('/references/brands', [ReferenceController::class, 'brands']);
Route::get('/references/cities', [ReferenceController::class, 'cities']);

// Profil public d'un utilisateur (contact révélé uniquement si conversation partagée)
Route::get('/users/{user}/profile', [ProfileController::class, 'show']);

// Auth publique
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Vérification e-mail par OTP (anti-spam / anti brute-force)
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,15');
Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:5,10');

// Recherche publique d'annonces
Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::get('/announcements/{announcement:slug}', [AnnouncementController::class, 'show']);

// Prise de contact publique (anti-spam : throttle 10/min)
Route::post('/announcements/{announcement:slug}/contact', [ContactController::class, 'send'])
    ->middleware('throttle:10,1');

// --- Routes authentifiées ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    // POST (et non PUT) : PHP ne peuple $_POST/$_FILES que pour POST en multipart.
    Route::post('/auth/profile', [AuthController::class, 'updateProfile']);

    // Gestion de ses annonces
    Route::get('/my/announcements', [AnnouncementController::class, 'mine']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

    // Favoris
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/announcements/{announcement:slug}/favorite', [FavoriteController::class, 'toggle']);

    // Messagerie client <-> vendeur
    Route::post('/announcements/{announcement:slug}/messages', [MessageController::class, 'start'])
        ->middleware('throttle:20,1');
    Route::get('/conversations', [MessageController::class, 'index']);
    Route::get('/conversations/unread-count', [MessageController::class, 'unreadCount']);
    Route::get('/conversations/{conversation}', [MessageController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'send']);
});

// --- Routes modération / admin ---
Route::middleware(['auth:sanctum', 'moderator'])->prefix('admin')->group(function () {
    Route::get('/moderation/queue', [ModerationController::class, 'queue']);
    Route::get('/moderation/{announcement}', [ModerationController::class, 'show']);
    Route::post('/moderation/{announcement}/moderate', [ModerationController::class, 'moderate']);
    Route::post('/moderation/bulk', [ModerationController::class, 'bulk']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::post('/users/{user}/ban', [AdminUserController::class, 'ban']);
    Route::post('/users/{user}/unban', [AdminUserController::class, 'unban']);
    Route::post('/users/{user}/kyc', [AdminUserController::class, 'verifyKyc']);
    Route::post('/users/{user}/subscription', [AdminUserController::class, 'setSubscription']);

    Route::get('/stats', [AdminStatsController::class, 'dashboard']);

    Route::get('/announcements', [AdminAnnouncementController::class, 'index']);

    // Gestion des comptes modérateurs — admin uniquement
    Route::middleware('admin')->group(function () {
        Route::get('/moderators', [AdminModeratorController::class, 'index']);
        Route::post('/moderators', [AdminModeratorController::class, 'store']);
        Route::delete('/moderators/{user}', [AdminModeratorController::class, 'destroy']);
    });
});