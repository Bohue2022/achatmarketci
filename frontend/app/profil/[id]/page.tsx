"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError, fetchMe, isLoggedIn } from "@/lib/api";
import type { PublicUserProfile } from "@/lib/types";

export default function ProfilPage() {
  const params = useParams<{ id: string }>();
  const [profile, setProfile] = useState<PublicUserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [isOwnProfile, setIsOwnProfile] = useState(false);

  useEffect(() => {
    setLoading(true);
    setError("");
    let active = true;
    api<{ data: PublicUserProfile }>(`/users/${params.id}/profile`)
      .then((r) => {
        if (!active) return;
        setProfile(r.data);
        if (isLoggedIn()) {
          fetchMe()
            .then((m) => active && setIsOwnProfile(m.user.id === r.data.id))
            .catch(() => {});
        }
      })
      .catch((err) => active && setError(err instanceof ApiError ? err.message : "Profil introuvable."))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [params.id]);

  if (loading) {
    return <p className="py-10 text-center text-sm text-slate-500">Chargement...</p>;
  }

  if (!profile) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-10 text-center text-slate-500">{error || "Profil introuvable."}</div>
    );
  }

  const memberSince = profile.member_since
    ? new Date(profile.member_since).toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" })
    : null;

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      {/* En-tête profil */}
      <div className="flex items-start gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 text-2xl font-bold text-orange-600">
          {profile.name.charAt(0).toUpperCase()}
        </div>
        <div className="min-w-0 flex-1">
          {isOwnProfile && (
            <Link
              href="/mon-profil"
              className="mb-2 inline-block rounded-lg border border-orange-300 px-3 py-1 text-xs font-semibold text-orange-600 hover:bg-orange-50"
            >
              Modifier mon profil
            </Link>
          )}
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-bold text-slate-900">{profile.name}</h1>
            {profile.role === "pro" && (
              <span className={`rounded px-2 py-0.5 text-xs font-semibold ${profile.is_verified_pro ? "bg-green-100 text-green-700" : "bg-slate-100 text-slate-600"}`}>
                {profile.is_verified_pro ? "Pro vérifié" : "Professionnel"}
              </span>
            )}
          </div>
          {profile.company_name && <p className="text-sm text-slate-500">{profile.company_name}</p>}
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
            {profile.city && <span>📍 {profile.city}</span>}
            <span>🗓️ Membre depuis {memberSince ?? "—"}</span>
            <span>
              🚗 {profile.published_announcements_count} annonce(s) active(s)
            </span>
          </div>
          {profile.bio && <p className="mt-3 text-sm leading-relaxed text-slate-600">{profile.bio}</p>}
        </div>
      </div>

      {/* Contact (uniquement si conversation partagée) */}
      {profile.contact && (
        <div className="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <p className="text-sm font-semibold text-slate-700">Coordonnées (vous avez une conversation en commun)</p>
          <div className="mt-2 flex flex-col gap-2 sm:flex-row">
            {profile.contact.phone && (
              <a href={`tel:${profile.contact.phone}`} className="flex-1 rounded-lg bg-green-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-green-700">
                📞 {profile.contact.phone}
              </a>
            )}
            {profile.contact.whatsapp && (
              <a
                href={`https://wa.me/${profile.contact.whatsapp.replace(/[^0-9]/g, "")}`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex-1 rounded-lg bg-emerald-500 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-600"
              >
                WhatsApp
              </a>
            )}
          </div>
        </div>
      )}

      {/* Annonces de l'utilisateur */}
      <h2 className="mt-8 text-lg font-bold text-slate-900">Ses annonces actives</h2>
      {!profile.published_announcements || profile.published_announcements.length === 0 ? (
        <p className="mt-3 rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
          {profile.role === "pro"
            ? "Ce professionnel n'a pas d'annonce active actuellement."
            : "Ce membre n'a pas d'annonce active actuellement."}
        </p>
      ) : (
        <div className="mt-3 grid gap-4 sm:grid-cols-2">
          {profile.published_announcements.map((a) => (
            <Link
              key={a.id}
              href={`/annonces/${a.slug}`}
              className="group flex gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100 transition hover:shadow-md"
            >
              <div className="relative block aspect-[4/3] w-28 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                {a.cover ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={a.cover} alt="" className="h-full w-full object-cover transition group-hover:scale-105" />
                ) : (
                  <div className="flex h-full items-center justify-center text-slate-300">
                    <span className="text-3xl">🚗</span>
                  </div>
                )}
              </div>
              <div className="min-w-0">
                <p className="line-clamp-1 font-semibold text-slate-900">{a.title}</p>
                <p className="mt-1 font-bold text-orange-600">{a.price_formatted}</p>
                {a.city && <p className="mt-1 text-xs text-slate-500">{a.city}</p>}
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}