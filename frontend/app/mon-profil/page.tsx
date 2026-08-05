"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError, fetchMe, isLoggedIn } from "@/lib/api";
import type { City, User } from "@/lib/types";

export default function MonProfilPage() {
  const router = useRouter();
  const [cities, setCities] = useState<City[]>([]);
  const [user, setUser] = useState<User | null>(null);
  const [avatar, setAvatar] = useState<File | null>(null);
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
  const [error, setError] = useState<Record<string, string> | string>("");
  const [success, setSuccess] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    name: "",
    phone: "",
    whatsapp: "",
    bio: "",
    company_name: "",
    rccm_number: "",
    city_id: "",
  });

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    Promise.all([fetchMe(), api<{ data: City[] }>("/references/cities", { auth: false })])
      .then(([m, c]) => {
        setUser(m.user);
        setCities(c.data);
        setForm({
          name: m.user.name ?? "",
          phone: m.user.phone ?? "",
          whatsapp: m.user.whatsapp ?? "",
          bio: m.user.bio ?? "",
          company_name: m.user.company_name ?? "",
          rccm_number: m.user.rccm_number ?? "",
          city_id: m.user.city_id ? String(m.user.city_id) : "",
        });
      })
      .catch((err) => setError(err instanceof ApiError ? err.message : "Impossible de charger votre profil."))
      .finally(() => setLoading(false));
  }, [router]);

  function update(field: string, value: string) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  function onAvatar(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setAvatar(file);
    setAvatarPreview(URL.createObjectURL(file));
  }

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError("");
    setSuccess("");
    setSaving(true);

    const fd = new FormData();
    fd.append("name", form.name);
    fd.append("phone", form.phone);
    if (form.whatsapp) fd.append("whatsapp", form.whatsapp);
    fd.append("bio", form.bio);
    if (form.company_name) fd.append("company_name", form.company_name);
    if (form.rccm_number) fd.append("rccm_number", form.rccm_number);
    if (form.city_id) fd.append("city_id", form.city_id);
    if (avatar) fd.append("avatar", avatar);

    try {
      const res = await api<{ message: string; user: User }>("/auth/profile", { method: "POST", body: fd });
      setUser(res.user);
      setAvatar(null);
      setSuccess("Profil mis à jour.");
      router.refresh();
    } catch (err) {
      if (err instanceof ApiError && err.data?.errors) setError(err.data.errors);
      else setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    } finally {
      setSaving(false);
    }
  }

  const inputCls =
    "w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500";

  function err(field: string) {
    return typeof error === "object" ? error[field] : "";
  }

  if (loading) {
    return <p className="py-10 text-center text-sm text-slate-500">Chargement...</p>;
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <h1 className="text-2xl font-bold text-slate-900">Mon profil</h1>
      <p className="mt-1 text-sm text-slate-500">Ces informations sont visibles par les autres membres.</p>

      {success && (
        <div className="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{success}</div>
      )}
      {typeof error === "string" && error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}

      <form onSubmit={onSubmit} className="mt-6 space-y-4">
        {/* Avatar */}
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Photo de profil</h2>
          <div className="flex items-center gap-4">
            <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-orange-100 text-2xl font-bold text-orange-600">
              {avatarPreview ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={avatarPreview} alt="Aperçu" className="h-full w-full object-cover" />
              ) : user?.avatar ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={user.avatar} alt={user.name} className="h-full w-full object-cover" />
              ) : (
                (user?.name ?? "?").charAt(0).toUpperCase()
              )}
            </div>
            <label className="cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-orange-400 hover:text-orange-600">
              Changer de photo
              <input type="file" accept="image/*" className="hidden" onChange={onAvatar} />
            </label>
          </div>
        </div>

        {/* Identité */}
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Identité</h2>
          <div className="space-y-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Nom complet *</label>
              <input value={form.name} onChange={(e) => update("name", e.target.value)} required className={inputCls} />
              {err("name") && <p className="mt-1 text-xs text-red-600">{err("name")}</p>}
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Téléphone *</label>
                <input value={form.phone} onChange={(e) => update("phone", e.target.value)} required className={inputCls} placeholder="+2250700000000" />
                {err("phone") && <p className="mt-1 text-xs text-red-600">{err("phone")}</p>}
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">WhatsApp</label>
                <input value={form.whatsapp} onChange={(e) => update("whatsapp", e.target.value)} className={inputCls} placeholder="+2250700000000" />
                {err("whatsapp") && <p className="mt-1 text-xs text-red-600">{err("whatsapp")}</p>}
              </div>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Ville</label>
              <select value={form.city_id} onChange={(e) => update("city_id", e.target.value)} className={inputCls}>
                <option value="">—</option>
                {cities.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">Bio</label>
              <textarea value={form.bio} onChange={(e) => update("bio", e.target.value)} rows={3} maxLength={1000} className={inputCls} placeholder="Quelques mots sur vous..." />
            </div>
          </div>
        </div>

        {/* Infos professionnelles */}
        {user?.role === "pro" && (
          <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
            <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Informations professionnelles</h2>
            <div className="space-y-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Nom de l&apos;entreprise</label>
                <input value={form.company_name} onChange={(e) => update("company_name", e.target.value)} className={inputCls} />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-slate-700">Numéro RCCM</label>
                <input value={form.rccm_number} onChange={(e) => update("rccm_number", e.target.value)} className={inputCls} />
              </div>
            </div>
          </div>
        )}

        {typeof error === "object" && (
          <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
            {Object.entries(error).map(([k, v]) => (
              <p key={k}>{String(v)}</p>
            ))}
          </div>
        )}

        <button
          type="submit"
          disabled={saving}
          className="w-full rounded-lg bg-orange-500 px-4 py-3 font-semibold text-white hover:bg-orange-600 disabled:opacity-60"
        >
          {saving ? "Enregistrement..." : "Enregistrer mon profil"}
        </button>
      </form>
    </div>
  );
}