"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError, isLoggedIn } from "@/lib/api";
import type { Announcement } from "@/lib/types";

const STATUS_LABELS: Record<string, string> = {
  draft: "Brouillon",
  pending: "En attente de validation",
  published: "Publiée",
  rejected: "Rejetée",
  expired: "Expirée",
  suspended: "Suspendue",
};

const STATUS_STYLES: Record<string, string> = {
  draft: "bg-slate-100 text-slate-600 ring-slate-200",
  pending: "bg-amber-50 text-amber-700 ring-amber-200",
  published: "bg-green-50 text-green-700 ring-green-200",
  rejected: "bg-red-50 text-red-700 ring-red-200",
  expired: "bg-slate-100 text-slate-500 ring-slate-200",
  suspended: "bg-orange-50 text-orange-700 ring-orange-200",
};

const TABS = [
  { key: "", label: "Toutes" },
  { key: "pending", label: "En attente" },
  { key: "published", label: "Publiées" },
  { key: "draft", label: "Brouillons" },
  { key: "rejected", label: "Rejetées" },
];

export default function MesAnnoncesPage() {
  const router = useRouter();
  const [annonces, setAnnonces] = useState<Announcement[]>([]);
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
  }, [router]);

  useEffect(() => {
    setLoading(true);
    setError("");
    const query = new URLSearchParams();
    if (status) query.set("status", status);
    if (page > 1) query.set("page", String(page));

    api<{ data: Announcement[]; meta?: { current_page: number; last_page: number; total: number } }>(
      `/my/announcements?${query.toString()}`,
    )
      .then((r) => {
        setAnnonces(r.data);
        setLastPage(r.meta?.last_page ?? 1);
        setTotal(r.meta?.total ?? 0);
      })
      .catch((err) => {
        setError(err instanceof ApiError ? err.message : "Impossible de charger vos annonces.");
      })
      .finally(() => setLoading(false));
  }, [status, page]);

  function switchTab(key: string) {
    setStatus(key);
    setPage(1);
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Mes annonces</h1>
          <p className="mt-1 text-sm text-slate-500">{total} annonce(s) sur votre compte</p>
        </div>
        <Link
          href="/deposer-annonce"
          className="rounded-lg bg-orange-500 px-4 py-2 font-semibold text-white hover:bg-orange-600"
        >
          + Déposer une annonce
        </Link>
      </div>

      <div className="mt-6 flex flex-wrap gap-2">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => switchTab(t.key)}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition ${
              status === t.key
                ? "bg-slate-900 text-white"
                : "bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}

      {loading ? (
        <p className="mt-8 text-center text-sm text-slate-500">Chargement...</p>
      ) : annonces.length === 0 ? (
        <div className="mt-10 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
          <p className="text-slate-500">Aucune annonce dans cette catégorie.</p>
          <Link
            href="/deposer-annonce"
            className="mt-3 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
          >
            Déposer votre première annonce
          </Link>
        </div>
      ) : (
        <div className="mt-6 space-y-3">
          {annonces.map((a) => (
            <div
              key={a.id}
              className="flex gap-4 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100"
            >
              <Link
                href={a.status === "published" ? `/annonces/${a.slug}` : "#"}
                className={`relative block aspect-[4/3] w-32 shrink-0 overflow-hidden rounded-lg bg-slate-100 sm:w-40 ${
                  a.status === "published" ? "" : "cursor-default"
                }`}
              >
                {a.photos[0] ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={a.photos[0].url} alt={a.title} className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-slate-300">
                    <span className="text-3xl">🚗</span>
                  </div>
                )}
              </Link>

              <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex flex-wrap items-center gap-2">
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ${STATUS_STYLES[a.status] ?? STATUS_STYLES.draft}`}
                  >
                    {STATUS_LABELS[a.status] ?? a.status}
                  </span>
                  {a.featured && (
                    <span className="rounded-full bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700 ring-1 ring-orange-200">
                      Mis en avant
                    </span>
                  )}
                </div>

                <h3 className="mt-1 line-clamp-1 font-semibold text-slate-900">
                  {a.brand?.name} {a.model?.name} {a.year}
                </h3>
                <p className="text-lg font-bold text-orange-600">{a.price_formatted}</p>

                <div className="mt-auto flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                  <span>
                    {a.views_count.toLocaleString("fr-FR")} vues · {a.contacts_count.toLocaleString("fr-FR")} contacts
                  </span>
                  <div className="flex items-center gap-2">
                    {a.city && <span>{a.city.name}{a.commune ? ` · ${a.commune.name}` : ""}</span>}
                    {a.status === "published" && (
                      <Link href={`/annonces/${a.slug}`} className="font-semibold text-orange-600 hover:underline">
                        Voir →
                      </Link>
                    )}
                  </div>
                </div>

                {a.rejection_reason && (
                  <p className="mt-2 rounded bg-red-50 px-2 py-1 text-xs text-red-700">
                    Motif du rejet : {a.rejection_reason}
                  </p>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {lastPage > 1 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1}
            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40"
          >
            ← Précédent
          </button>
          <span className="text-sm text-slate-500">
            Page {page} / {lastPage}
          </span>
          <button
            onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
            disabled={page >= lastPage}
            className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40"
          >
            Suivant →
          </button>
        </div>
      )}
    </div>
  );
}