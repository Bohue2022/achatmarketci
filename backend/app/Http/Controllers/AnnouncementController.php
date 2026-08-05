<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementStatus;
use App\Exceptions\QuotaExceededException;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $service)
    {
    }

    /**
     * Recherche publique avec filtres marché ivoirien.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 20), 50);

        $query = Announcement::query()
            ->active()
            ->with(['brand', 'model', 'city', 'commune', 'photos'])
            ->select('announcements.*');

        // Trie les mises en avant en premier par défaut
        $query->orderByRaw('announcements.featured DESC');

        $this->applyFilters($request, $query);

        $sort = $request->input('sort', 'recent');
        $this->applySort($sort, $query);

        $announcements = $query->paginate($perPage);

        if ($request->boolean('with_facets')) {
            return response()->json([
                'data' => AnnouncementResource::collection($announcements),
                'filters' => $this->facets($request),
            ]);
        }

        return AnnouncementResource::collection($announcements);
    }

    /**
     * Lister les villes/marques disponibles pour les filtres (facettes).
     */
    protected function facets(Request $request): array
    {
        return [
            'total' => Announcement::active()->count(),
            'min_price' => (int) Announcement::active()->min('price'),
            'max_price' => (int) Announcement::active()->max('price'),
            'years' => Announcement::active()->whereNotNull('year')->pluck('year')->unique()->sortDesc()->take(30)->values(),
        ];
    }

    protected function applyFilters(Request $request, Builder $query): void
    {
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function (Builder $b) use ($q) {
                $b->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->input('commune_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        if ($request->filled('model_id')) {
            $query->where('model_id', $request->input('model_id'));
        }

        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->input('fuel_type'));
        }

        if ($request->filled('transmission')) {
            $query->where('transmission', $request->input('transmission'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        if ($request->filled('body_type')) {
            $query->where('body_type', $request->input('body_type'));
        }

        if ($request->has('is_dedouane')) {
            $query->where('is_dedouane', $request->boolean('is_dedouane'));
        }

        if ($request->filled('year_min')) {
            $query->where('year', '>=', $request->input('year_min'));
        }
        if ($request->filled('year_max')) {
            $query->where('year', '<=', $request->input('year_max'));
        }

        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->input('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->input('price_max'));
        }

        if ($request->filled('mileage_max')) {
            $query->where('mileage', '<=', $request->input('mileage_max'));
        }

        if ($request->filled('pro_only') && $request->boolean('pro_only')) {
            $query->whereHas('user', fn ($q) => $q->where('role', 'pro')->where('is_verified_pro', true));
        }
    }

    protected function applySort(string $sort, Builder $query): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'mileage_asc' => $query->orderBy('mileage'),
            'oldest' => $query->orderBy('published_at'),
            default => $query->orderBy('published_at', 'desc'), // recent
        };
    }

    /**
     * Détail public d'une annonce (+ incrément vues).
     */
    public function show(Announcement $announcement): JsonResponse
    {
        if ($announcement->status !== AnnouncementStatus::Published->value) {
            abort(404);
        }

        $announcement->load(['brand', 'model', 'city', 'commune', 'photos', 'user.city']);

        // Incrément atomique des vues
        $announcement->increment('views_count');

        return (new AnnouncementResource($announcement))->response();
    }

    /**
     * Les annonces gérées par l'utilisateur connecté (tous statuts).
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $status = $request->input('status');

        $query = $request->user()->announcements()
            ->with(['brand', 'model', 'city', 'commune', 'photos'])
            ->withCount(['photos'])
            ->latest();

        if ($status && in_array($status, AnnouncementStatus::values(), true)) {
            $query->where('status', $status);
        }

        $announcements = $query->paginate(20);

        return AnnouncementResource::collection($announcements);
    }

    /**
     * Création + soumission en modération (avec contrôle de quota).
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $user = $request->user();

        // Contrôle de quota avec guidance vers plan pro
        try {
            $this->service->assertCanPublish($user);
        } catch (QuotaExceededException $e) {
            return $e->render($request);
        }

        $data = $request->safe()->except('photos');

        $announcement = DB::transaction(function () use ($user, $data, $request) {
            $announcement = $user->announcements()->create($data);
            $announcement->slug = $this->service->buildSlug($announcement);
            $announcement->save();

            $this->attachPhotos($announcement, $request);

            return $announcement;
        });

        $this->service->submitForReview($announcement);

        $announcement->load(['brand', 'model', 'city', 'commune', 'photos']);

        return response()->json([
            'message' => 'Votre annonce a été soumise pour validation.',
            'data' => new AnnouncementResource($announcement),
        ], 201);
    }

    /**
     * Mise à jour (propriétaire, tant que pas encore validée).
     */
    public function update(StoreAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        if (in_array($announcement->status, [AnnouncementStatus::Published->value, AnnouncementStatus::Suspended->value], true)) {
            return response()->json([
                'message' => 'Une annonce publiée ne peut pas être modifiée. Mettez-la en pause avant.',
                'code' => 'published_locked',
            ], 422);
        }

        $data = $request->safe()->except('photos');

        $announcement->fill($data);
        $announcement->slug = $this->service->buildSlug($announcement);
        $announcement->save();

        if ($request->has('photos')) {
            $this->replacePhotos($announcement, $request);
        }

        // Renseigner en modération
        $this->service->submitForReview($announcement);

        $announcement->load(['brand', 'model', 'city', 'commune', 'photos']);

        return response()->json([
            'message' => 'Annonce mise à jour et renvoyée en validation.',
            'data' => new AnnouncementResource($announcement),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return response()->json(['message' => 'Annonce supprimée.']);
    }

    protected function attachPhotos(Announcement $announcement, Request $request): void
    {
        $photos = $request->file('photos', []);
        foreach ($photos as $index => $file) {
            $path = $file->store('announcements/' . $announcement->id, 'public');
            $announcement->photos()->create([
                'path' => $path,
                'disk' => 'public',
                'position' => $index,
                'is_cover' => $index === 0,
            ]);
        }
    }

    protected function replacePhotos(Announcement $announcement, Request $request): void
    {
        $announcement->photos()->delete();
        $this->attachPhotos($announcement, $request);
    }
}