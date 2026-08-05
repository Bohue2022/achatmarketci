<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicUserResource;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Profil public d'un utilisateur : date d'inscription, rôle, annonces actives.
     */
    public function show(Request $request, User $user): PublicUserResource
    {
        $user->load(['city', 'activeAnnouncements.photos']);

        return new PublicUserResource($user->loadCount('activeAnnouncements'));
    }
}