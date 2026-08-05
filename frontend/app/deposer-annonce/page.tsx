"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError, isLoggedIn } from "@/lib/api";
import type { Brand, City } from "@/lib/types";

export default function DeposerAnnoncePage() {
  const router = useRouter();
  const [cities, setCities] = useState<City[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [selectedBrand, setSelectedBrand] = useState("");
  const [cityId, setCityId] = useState("");
  const [error, setError] = useState<Record<string, string> | string>("");
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState("");
  const [modelId, setModelId] = useState("");
  const [communeId, setCommuneId] = useState("");
  const [photos, setPhotos] = useState<File[]>([]);
  const [photoPreviews, setPhotoPreviews] = useState<string[]>([]);

  const [form, setForm] = useState({
    title: "",
    description: "",
    price: "",
    year: "",
    mileage: "",
    fuel_type: "",
    transmission: "",
    condition: "",
    body_type: "",
    is_dedouane: true,
    has_grise: true,
    origin: "",
  });

  const selectedCity = cities.find((c) => String(c.id) === cityId);
  const selectedBrandObj = brands.find((b) => String(b.id) === selectedBrand);

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    Promise.all([
      api<{ data: City[] }>("/references/cities", { auth: false }),
      api<{ data: Brand[] }>("/references/brands", { auth: false }),
    ]).then(([c, b]) => {
      setCities(c.data);
      setBrands(b.data);
    });
  }, [router]);

  function update(field: string, value: string | boolean) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  function onPickPhotos(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files ?? []).slice(0, 20 - photos.length);
    if (!files.length) return;
    setPhotos((p) => [...p, ...files]);
    setPhotoPreviews((p) => [
      ...p,
      ...files.map((f) => URL.createObjectURL(f)),
    ]);
    e.target.value = "";
  }

  function removePhoto(index: number) {
    setPhotos((p) => p.filter((_, i) => i !== index));
    setPhotoPreviews((p) => {
      URL.revokeObjectURL(p[index]);
      return p.filter((_, i) => i !== index);
    });
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setSuccess("");
    setLoading(true);

    if (!modelId) {
      setError({ model_id: "Veuillez choisir un modèle." });
      setLoading(false);
      return;
    }

    const fd = new FormData();
    fd.append("title", form.title);
    fd.append("description", form.description);
    fd.append("price", String(Number(form.price)));
    if (form.year) fd.append("year", String(form.year));
    if (form.mileage) fd.append("mileage", String(form.mileage));
    fd.append("fuel_type", form.fuel_type);
    fd.append("transmission", form.transmission);
    fd.append("condition", form.condition);
    if (form.body_type) fd.append("body_type", form.body_type);
    fd.append("is_dedouane", form.is_dedouane ? "1" : "0");
    fd.append("has_grise", form.has_grise ? "1" : "0");
    if (form.origin) fd.append("origin", form.origin);
    fd.append("brand_id", selectedBrand);
    fd.append("model_id", modelId);
    fd.append("city_id", cityId);
    if (communeId) fd.append("commune_id", communeId);
    photos.forEach((photo) => fd.append("photos[]", photo));

    try {
      await api("/announcements", { method: "POST", body: fd });
      setSuccess("Votre annonce a été soumise pour validation. Elle sera publiée après approbation d'un modérateur.");
      setForm({
        title: "", description: "", price: "", year: "", mileage: "",
        fuel_type: "", transmission: "", condition: "", body_type: "",
        is_dedouane: true, has_grise: true, origin: "",
      });
      setSelectedBrand("");
      setModelId("");
      setCityId("");
      setCommuneId("");
      setPhotoPreviews((p) => {
        p.forEach((url) => URL.revokeObjectURL(url));
        return [];
      });
      setPhotos([]);
    } catch (err) {
      if (err instanceof ApiError && err.data?.errors) setError(err.data.errors);
      else setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    } finally {
      setLoading(false);
    }
  }

  const inputCls =
    "w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500";

  function err(field: string) {
    return typeof error === "object" ? error[field] : undefined;
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <h1 className="text-2xl font-bold text-slate-900">Déposer une annonce</h1>
      <p className="mt-1 text-sm text-slate-500">
        Renseignez les informations de votre véhicule. L&apos;annonce sera modérée avant publication.
      </p>

      {success && (
        <div className="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{success}</div>
      )}
      {typeof error === "string" && error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}

      <form id="annonce-form" onSubmit={onSubmit} className="mt-6 space-y-4">
        {/* Localisation */}
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Localisation</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Ville *</label>
              <select value={cityId} onChange={(e) => setCityId(e.target.value)} required className={inputCls}>
                <option value="">Choisir une ville</option>
                {cities.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Commune</label>
              <select
                id="commune_id"
                value={communeId}
                onChange={(e) => setCommuneId(e.target.value)}
                className={inputCls}
                disabled={!selectedCity}
              >
                <option value="">—</option>
                {selectedCity?.communes.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {/* Véhicule */}
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Véhicule</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Marque *</label>
              <select value={selectedBrand} onChange={(e) => { setSelectedBrand(e.target.value); setModelId(""); }} required className={inputCls}>
                <option value="">Choisir une marque</option>
                {brands.map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Modèle *</label>
              <select
                value={modelId}
                onChange={(e) => setModelId(e.target.value)}
                required
                disabled={!selectedBrandObj}
                className={inputCls}
              >
                <option value="">Choisir un modèle</option>
                {selectedBrandObj?.models.map((m) => (
                  <option key={m.id} value={m.id}>{m.name}</option>
                ))}
              </select>
              {err("model_id") && <p className="mt-1 text-xs text-red-600">{err("model_id")}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Année</label>
              <input type="number" value={form.year} onChange={(e) => update("year", e.target.value)} className={inputCls} placeholder="2019" />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Kilométrage (km)</label>
              <input type="number" value={form.mileage} onChange={(e) => update("mileage", e.target.value)} className={inputCls} placeholder="75000" />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Carburant *</label>
              <select value={form.fuel_type} onChange={(e) => update("fuel_type", e.target.value)} required className={inputCls}>
                <option value="">—</option>
                <option value="essence">Essence</option>
                <option value="diesel">Diesel</option>
                <option value="hybride">Hybride</option>
                <option value="electrique">Électrique</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Boîte *</label>
              <select value={form.transmission} onChange={(e) => update("transmission", e.target.value)} required className={inputCls}>
                <option value="">—</option>
                <option value="manuelle">Manuelle</option>
                <option value="automatique">Automatique</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">État *</label>
              <select value={form.condition} onChange={(e) => update("condition", e.target.value)} required className={inputCls}>
                <option value="">—</option>
                <option value="occasion">Occasion</option>
                <option value="neuf">Neuf</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Type de véhicule</label>
              <select value={form.body_type} onChange={(e) => update("body_type", e.target.value)} className={inputCls}>
                <option value="">—</option>
                <option value="berline">Berline</option>
                <option value="suv">SUV</option>
                <option value="pickup">Pickup</option>
                <option value="utilitaire">Utilitaire</option>
                <option value="4x4">4x4</option>
                <option value="coupe">Coupé</option>
                <option value="cabriolet">Cabriolet</option>
                <option value="monospace">Monospace</option>
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Origine</label>
              <input value={form.origin} onChange={(e) => update("origin", e.target.value)} className={inputCls} placeholder="importe_ue / local" />
            </div>
          </div>

          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" checked={form.is_dedouane} onChange={(e) => update("is_dedouane", e.target.checked)} />
              Véhicule dédouané
            </label>
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" checked={form.has_grise} onChange={(e) => update("has_grise", e.target.checked)} />
              Carte grise disponible
            </label>
          </div>
        </div>

        {/* Prix & infos */}
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Annonce</h2>
          <div className="space-y-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Titre *</label>
              <input value={form.title} onChange={(e) => update("title", e.target.value)} required className={inputCls} placeholder="Toyota RAV4 2020 — Dédouané" />
              {err("title") && <p className="mt-1 text-xs text-red-600">{err("title")}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Prix (FCFA) *</label>
              <input type="number" value={form.price} onChange={(e) => update("price", e.target.value)} required className={inputCls} placeholder="15000000" min="1000" />
              {err("price") && <p className="mt-1 text-xs text-red-600">{err("price")}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Description *</label>
              <textarea value={form.description} onChange={(e) => update("description", e.target.value)} required rows={5} className={inputCls} placeholder="Décrivez l'état du véhicule, son historique, ses options..." />
              {err("description") && <p className="mt-1 text-xs text-red-600">{err("description")}</p>}
            </div>
          </div>
        </div>

        {/* Photos */}
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Photos {photos.length > 0 && <span className="text-orange-500">({photos.length})</span>}
          </h2>
          <p className="mb-3 text-xs text-slate-500">
            Jusqu&apos;à 20 photos (max 10 Mo chacune). La première sera la photo de couverture.
          </p>

          {photoPreviews.length > 0 && (
            <div className="mb-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
              {photoPreviews.map((url, i) => (
                <div key={i} className="group relative aspect-[4/3] overflow-hidden rounded-lg ring-1 ring-slate-200">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={url} alt={`Photo ${i + 1}`} className="h-full w-full object-cover" />
                  {i === 0 && (
                    <span className="absolute left-1 top-1 rounded bg-orange-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                      Cover
                    </span>
                  )}
                  <button
                    type="button"
                    onClick={() => removePhoto(i)}
                    className="absolute right-1 top-1 hidden rounded-full bg-black/60 px-2 py-0.5 text-xs text-white group-hover:block"
                    aria-label="Supprimer la photo"
                  >
                    ✕
                  </button>
                </div>
              ))}
              {photos.length < 20 && (
                <label className="flex aspect-[4/3] cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-slate-300 text-2xl text-slate-400 hover:border-orange-400 hover:text-orange-500">
                  +
                  <input type="file" accept="image/*" multiple className="hidden" onChange={onPickPhotos} />
                </label>
              )}
            </div>
          )}

          <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-orange-400 hover:text-orange-600">
            <input type="file" accept="image/*" multiple className="hidden" onChange={onPickPhotos} />
            {photos.length ? "Ajouter des photos" : "Choisir des photos"}
          </label>
          {err("photos") && <p className="mt-1 text-xs text-red-600">{err("photos")}</p>}
        </div>

        {typeof error === "object" && (
          <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
            {Object.entries(error).map(([k, v]) => (
              <p key={k}>{String(v)}</p>
            ))}
          </div>
        )}

        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-orange-500 px-4 py-3 font-semibold text-white hover:bg-orange-600 disabled:opacity-60"
        >
          {loading ? "Envoi en cours..." : "Soumettre pour validation"}
        </button>
      </form>
    </div>
  );
}