"use client";

import { useCallback, useEffect, useState } from "react";
import AnnouncementCard from "@/components/AnnouncementCard";
import { api } from "@/lib/api";
import type { Announcement, Brand, City } from "@/lib/types";

const FUEL = [
  { value: "", label: "Tous carburants" },
  { value: "essence", label: "Essence" },
  { value: "diesel", label: "Diesel" },
  { value: "hybride", label: "Hybride" },
  { value: "electrique", label: "Électrique" },
];

export default function HomePage() {
  const [annonces, setAnnonces] = useState<Announcement[]>([]);
  const [cities, setCities] = useState<City[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [loading, setLoading] = useState(true);

  const [q, setQ] = useState("");
  const [cityId, setCityId] = useState("");
  const [communeId, setCommuneId] = useState("");
  const [brandId, setBrandId] = useState("");
  const [fuelType, setFuelType] = useState("");
  const [condition, setCondition] = useState("");
  const [maxPrice, setMaxPrice] = useState("");
  const [sort, setSort] = useState("recent");

  const selectedCity = cities.find((c) => String(c.id) === cityId);

  const loadReferences = useCallback(async () => {
    const [c, b] = await Promise.all([
      api<{ data: City[] }>("/references/cities", { auth: false }),
      api<{ data: Brand[] }>("/references/brands", { auth: false }),
    ]);
    setCities(c.data);
    setBrands(b.data);
  }, []);

  const search = useCallback(async () => {
    setLoading(true);
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (cityId) params.set("city_id", cityId);
    if (communeId) params.set("commune_id", communeId);
    if (brandId) params.set("brand_id", brandId);
    if (fuelType) params.set("fuel_type", fuelType);
    if (condition) params.set("condition", condition);
    if (maxPrice) params.set("price_max", maxPrice);
    params.set("sort", sort);

    try {
      const res = await api<{ data: Announcement[] }>(`/announcements?${params}`, { auth: false });
      setAnnonces(res.data);
    } finally {
      setLoading(false);
    }
  }, [q, cityId, communeId, brandId, fuelType, condition, maxPrice, sort]);

  useEffect(() => {
    loadReferences();
  }, [loadReferences]);

  useEffect(() => {
    search();
  }, [search]);

  // Réinitialiser la commune si la ville change
  function onCityChange(value: string) {
    setCityId(value);
    setCommuneId("");
  }

  const selectCls =
    "rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500";

  return (
    <div className="mx-auto max-w-6xl px-4 py-6">
      {/* Barre de recherche */}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          search();
        }}
        className="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
      >
        <div className="flex flex-col gap-3 md:flex-row">
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Rechercher un véhicule, une marque..."
            className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
          />
          <button
            type="submit"
            className="rounded-lg bg-orange-500 px-6 py-2 text-sm font-semibold text-white hover:bg-orange-600"
          >
            Rechercher
          </button>
        </div>

        <div className="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
          <select value={cityId} onChange={(e) => onCityChange(e.target.value)} className={selectCls}>
            <option value="">Toutes les villes</option>
            {cities.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <select value={communeId} onChange={(e) => setCommuneId(e.target.value)} className={selectCls} disabled={!selectedCity}>
            <option value="">Toutes communes</option>
            {selectedCity?.communes.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <select value={brandId} onChange={(e) => setBrandId(e.target.value)} className={selectCls}>
            <option value="">Toutes marques</option>
            {brands.map((b) => (
              <option key={b.id} value={b.id}>
                {b.name}
              </option>
            ))}
          </select>
          <select value={fuelType} onChange={(e) => setFuelType(e.target.value)} className={selectCls}>
            {FUEL.map((f) => (
              <option key={f.value} value={f.value}>
                {f.label}
              </option>
            ))}
          </select>
          <select value={condition} onChange={(e) => setCondition(e.target.value)} className={selectCls}>
            <option value="">Neuf & occasion</option>
            <option value="neuf">Neuf</option>
            <option value="occasion">Occasion</option>
          </select>
          <input
            value={maxPrice}
            onChange={(e) => setMaxPrice(e.target.value)}
            placeholder="Prix max (FCFA)"
            inputMode="numeric"
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
          />
        </div>
      </form>

      {/* Tri */}
      <div className="mb-4 flex items-center justify-between">
        <p className="text-sm text-slate-500">
          {loading ? "Chargement..." : `${annonces.length} véhicule(s) trouvé(s)`}
        </p>
        <select value={sort} onChange={(e) => setSort(e.target.value)} className={selectCls}>
          <option value="recent">Plus récents</option>
          <option value="price_asc">Prix croissant</option>
          <option value="price_desc">Prix décroissant</option>
          <option value="mileage_asc">Kilométrage croissant</option>
        </select>
      </div>

      {/* Grille d'annonces */}
      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-72 animate-pulse rounded-xl bg-slate-200" />
          ))}
        </div>
      ) : annonces.length === 0 ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
          <p className="text-5xl">🚗</p>
          <p className="mt-3 text-slate-600">Aucun véhicule ne correspond à votre recherche.</p>
          <p className="text-sm text-slate-400">Modifiez vos filtres pour voir plus de résultats.</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {annonces.map((a) => (
            <AnnouncementCard key={a.id} annonce={a} />
          ))}
        </div>
      )}
    </div>
  );
}