"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";

interface AdminAnnouncement {
  id: number;
  slug: string;
  title: string;
  price_formatted: string;
  status: string;
  rejection_reason?: string | null;
  views_count: number;
  photos_count: number;
  created_at?: string | null;
  brand?: { id: number; name: string } | null;
  model?: { id: number; name: string } | null;
  city?: { id: number; name: string } | null;
  photos: { id: number; url: string; is_cover: boolean; position: number }[];
  seller?: { id: number; name: string; role: string; is_verified_pro: boolean; company_name?: string | null } | null;
}

interface ListResponse {
  data: AdminAnnouncement[];
  counts: Record<string, number>;
  meta: { current_page: number; last_page: number; total: number };
}

const STATUS_BADGE: Record<string, string> = {
  draft: "bg-slate-100 text-slate-600",
  pending: "bg-amber-50 text-amber-700",
  published: "bg-green-50 text-green-700",
  rejected: "bg-red-50 text-red-700",
  expired: "bg-slate-100 text-slate-500",
  suspended: "bg-orange-50 text-orange-700",
};

const STATUS_LABELS: Record<string, string> = {
  draft: "Brouillon",
  pending: "En attente",
  published: "Publiée",
  rejected: "Refusée",
  expired: "Expirée",
  suspended: "Suspendue",
};

const TABS = [
  { key: "", label: "Toutes" },
  { key: "pending", label: "En attente" },
  { key: "published", label: "Publiées" },
  { key: "rejected", label: "Refusées" },
  { key: "expired", label: "Expirées" },
  { key: "suspended", label: "Suspendues" },
  { key: "draft", label: "Brouillons" },
];

export default function AdminAnnouncementsPage() {
  const [list, setList] = useState<ListResponse | null>(null);
  const [status, setStatus] = useState("");
  const [q, setQ] = useState("");
  const [sellerType, setSellerType] = useState("");
  const [sort, setSort] = useState("newest");
  const [page, setPage] = useState(1);

  useEffect(() => {
    const params = new URLSearchParams({ page: String(page), per_page: "10", sort });
    if (status) params.set("status", status);
    if (q) params.set("q", q);
    if (sellerType) params.set("seller_type", sellerType);
    api<ListResponse>(`/admin/announcements?${params}`).then(setList).catch(() => {});
  }, [status, q, sellerType, sort, page]);

  return (
    <div className="pb-16 md:pb-0">
      <h1 className="text-2xl font-bold text-slate-900">Annonces</h1>
      <p className="text-sm text-slate-500">{list ? `${list.meta.total} annonce(s)` : "Chargement..."}</p>

      {/* Onglets par statut */}
      <div className="mt-4 flex flex-wrap gap-1.5">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => { setStatus(t.key); setPage(1); }}
            className={`rounded-full px-3 py-1.5 text-xs font-semibold ${
              status === t.key ? "bg-slate-900 text-white" : "bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50"
            }`}
          >
            {t.label}{list && (list.counts[t.key] ?? 0) > 0 && <span className="ml-1 opacity-60">· {list.counts[t.key] ?? 0}</span>}
          </button>
        ))}
      </div>

      {/* Filtres */}
      <div className="mt-3 flex flex-wrap gap-2">
        <input
          value={q}
          onChange={(e) => { setQ(e.target.value); setPage(1); }}
          placeholder="Rechercher (titre, vendeur, email)…"
          className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
        />
        <select value={sellerType} onChange={(e) => { setSellerType(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none">
          <option value="">Tous les vendeurs</option>
          <option value="pro">Professionnels</option>
          <option value="user">Particuliers</option>
        </select>
        <select value={sort} onChange={(e) => { setSort(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none">
          <option value="newest">Plus récentes</option>
          <option value="oldest">Plus anciennes</option>
          <option value="expensive">Prix décroissant</option>
          <option value="cheap">Prix croissant</option>
        </select>
      </div>

      {/* Liste */}
      <div className="mt-4 space-y-3">
        {list?.data.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            Aucune annonce pour ces critères.
          </div>
        )}
        {list?.data.map((a) => (
          <div key={a.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
            <div className="flex flex-wrap items-start gap-3">
              <div className="h-16 w-24 flex-none overflow-hidden rounded-lg bg-slate-100">
                {a.photos[0] ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={a.photos[0].url} alt="" className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-slate-300">🚗</div>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-semibold text-slate-900">{a.title}</p>
                  <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${STATUS_BADGE[a.status] ?? "bg-slate-100 text-slate-600"}`}>
                    {STATUS_LABELS[a.status] ?? a.status}
                  </span>
                </div>
                <p className="text-sm text-orange-600">{a.price_formatted}</p>
                <p className="mt-0.5 truncate text-sm text-slate-500">
                  {a.brand?.name} {a.model?.name} · {a.city?.name ?? ""} · {a.photos_count} photo(s) · {a.views_count} vues
                </p>
                <p className="text-xs text-slate-400">
                  Vendeur : {a.seller?.company_name ?? a.seller?.name}
                  {a.seller?.role === "pro" ? " (Pro)" : ""} · {a.created_at ? new Date(a.created_at).toLocaleDateString("fr-FR") : ""}
                </p>
                {a.status === "rejected" && a.rejection_reason && (
                  <p className="mt-1 text-xs text-red-600">Motif : {a.rejection_reason}</p>
                )}
              </div>
              <div className="flex shrink-0 gap-2">
                <Link href={`/annonces/${a.slug}`} target="_blank" className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                  Voir
                </Link>
                {a.status === "pending" && (
                  <Link href="/admin/moderation" className="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-600">
                    Modérer
                  </Link>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Pagination */}
      {list && list.meta.last_page > 1 && (
        <div className="mt-4 flex items-center justify-center gap-2">
          <button
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            disabled={page <= 1}
            className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40"
          >
            ← Précédent
          </button>
          <span className="text-sm text-slate-500">Page {list.meta.current_page} / {list.meta.last_page}</span>
          <button
            onClick={() => setPage((p) => Math.min(list.meta.last_page, p + 1))}
            disabled={page >= list.meta.last_page}
            className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40"
          >
            Suivant →
          </button>
        </div>
      )}
    </div>
  );
}
