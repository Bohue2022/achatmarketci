<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Mail\VerificationOtp;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^[+0-9 ]{8,20}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->symbols()],
            // Choix du type de compte à l'inscription
            'account_type' => ['required', 'in:particulier,professionnel'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => $data['account_type'] === 'professionnel' ? Role::Pro : Role::User,
        ]);

        // Envoi du code de vérification (OTP) par e-mail.
        $code = $this->otp->create($user);
        Mail::to($user)->send(new VerificationOtp($user, $code));

        $response = [
            'message' => 'Compte créé. Un code de vérification a été envoyé à votre adresse e-mail.',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'role']),
            'requires_email_verification' => true,
        ];

        // Confort de développement : code visible uniquement en local tant que le
        // mailer réel (SMTP) n'est pas configuré. Jamais en production, ni en SMTP.
        if (app()->environment('local') && config('mail.default') === 'log') {
            $response['dev_code'] = $code;
        }

        return response()->json($response, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        if ($user->isBanned()) {
            return response()->json(['message' => 'Ce compte a été suspendu.', 'code' => 'account_banned'], 403);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Veuillez vérifier votre adresse e-mail avant de vous connecter.',
                'code' => 'email_not_verified',
                'data' => ['email' => $user->email],
            ], 403);
        }

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro']),
            'token' => $user->createToken('auth')->plainTextToken,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Compte introuvable.'], 404);
        }

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Adresse e-mail déjà vérifiée. Vous pouvez vous connecter.',
                'user' => $user->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro']),
                'token' => $user->createToken('auth')->plainTextToken,
            ]);
        }

        if (! $this->otp->verify($user, $data['otp'])) {
            $hasAny = \App\Models\EmailVerificationCode::where('user_id', $user->id)->first();

            return response()->json([
                'message' => $hasAny ? 'Code incorrect.' : 'Code expiré. Demandez un nouveau code.',
                'code' => $hasAny ? 'invalid_otp' : 'otp_expired',
            ], 422);
        }

        $user->email_verified_at = now();
        $user->save();
        $this->otp->purge($user);

        return response()->json([
            'message' => 'Adresse e-mail vérifiée.',
            'user' => $user->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro']),
            'token' => $user->createToken('auth')->plainTextToken,
        ]);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->email_verified_at === null) {
            $code = $this->otp->create($user);
            Mail::to($user)->send(new VerificationOtp($user, $code));
        }

        $response = [
            'message' => 'Si le compte existe et n\'est pas encore vérifié, un nouveau code vient d\'être envoyé.',
        ];

        if (app()->environment('local') && config('mail.default') === 'log' && $user && $user->email_verified_at === null) {
            $response['dev_code'] = $code;
        }

        return response()->json($response);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['city', 'activeSubscription.plan']);

        return response()->json([
            'user' => [
                ...$user->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro', 'company_name', 'bio', 'whatsapp', 'rccm_number']),
                'city_id' => $user->city_id,
                'city' => $user->city?->name,
                'avatar' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                'plan' => $user->activeSubscription?->plan?->slug,
                'active_announcement_limit' => $user->activeAnnouncementLimit(),
                'is_moderator' => $user->isModerator(),
                'email_verified' => $user->email_verified_at !== null,
            ],
        ]);
    }

    /**
     * Mise à jour du profil de l'utilisateur connecté (multipart : peut contenir un avatar).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[+0-9 ]{8,20}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^[+0-9 ]{8,20}$/'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'rccm_number' => ['nullable', 'string', 'max:60'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        // Avatar : remplace l'ancien s'il existe
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill(Arr::except($data, ['avatar']));
        $user->save();
        $user->load('city');

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user' => $this->userPayload($user),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'email', 'phone', 'role', 'is_verified_pro', 'company_name', 'bio', 'whatsapp', 'rccm_number']),
            'city_id' => $user->city_id,
            'city' => $user->city?->name,
            'avatar' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
            'is_moderator' => $user->isModerator(),
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}