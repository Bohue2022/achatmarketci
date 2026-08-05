<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\City;
use Illuminate\Http\JsonResponse;

class ReferenceController extends Controller
{
    /**
     * Marques + modèles — référentiel public pour les formulaires et filtres.
     */
    public function brands(): JsonResponse
    {
        $brands = Brand::with(['models' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $brands]);
    }

    /**
     * Villes + communes — Abidjan avec ses communes.
     */
    public function cities(): JsonResponse
    {
        $cities = City::with(['communes' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'latitude', 'longitude']);

        return response()->json(['data' => $cities]);
    }
}