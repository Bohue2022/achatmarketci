"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, isLoggedIn } from "@/lib/api";

interface Stats {
  listings: {
    active: number;
    pending: number;
    published_total: number;
    rejected_total: number;
    expired: number;
    suspended: number;
  };
  moderation: {
    pending: number;
    total_actions: number;
    avg_moderation_minutes: number | null;
    approval_rate: number | null;
    by_moderator: { moderator: string; approved: number; rejected: number; total: number }[];
  };
  users: {
    total: number;
    particuliers: number;
    pros: number;
    verified_pros: number;
    banned: number;
    new_this_month: number;
  };
  daily: { date: string; label: string; announcements: number; users: number }[];
  recents: {
    announcements: {
      id: number;
      slug: string;
      title: string;
      status: string;
      price_formatted: string;
      seller_name?: string | null;
      seller_role?: string | null;
      created_at?: string | null;
    }[];
    moderation_actions: {
      id: number;
      action: string;
      reason?: string | null;
      announcement_title?: string | null;
      moderator_name?: string | null;
      created_at?: string | null;
    }[];
    users: {
      id: number;
      name: string;
      email: string;
      role: string;
      is_verified_pro: boolean;
      is_banned: boolean;
      created_at?: string | null;
    }[];
  };
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

function MiniBars({ data }: { data: { label: string; announcements: number; users: number }[] }) {
  const max = Math.max(1, ...data.map((d) => Math.max(d.announcements, d.users)));
  return (
    <div className="flex h-28 items-end gap-1">
      {data.map((d) => (
        <div key={d.label} className="group relative flex flex-1 flex-col items-center gap-1" title={`${d.label} · ${d.announcements} annonces, ${d.users} inscrits`}>
          <div className="flex h-24 w-full items-end justify-center gap-0.5">
            <div className="w-1.5 rounded-t bg-orange-400" style={{ height: `${(d.announcements / max) * 100}%` }} />
            <div className="w-1.5 rounded-t bg-slate-300" style={{ height: `${(d.users / max) * 100}%` }} />
          </div>
          <span className="text-[10px] text-slate-400">{d.label}</span>
        </div>
      ))}
    </div>
  );
}

export default function AdminDashboard() {
  const router = useRouter();
  const [stats, setStats] = useState<Stats | null>(null);

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    api<{ data: Stats }>("/admin/stats")
      .then((r) => setStats(r.data))
      .catch((e) => {
        if (e.status === 403) router.replace("/");
      });
  }, [router]);

  if (!stats) {
    return <div className="p-10 text-center text-slate-500">Chargement du tableau de bord...</div>;
  }

  const cards = [
    { label: "Annonces actives", value: stats.listings.active, color: "text-green-600", href: "/admin/annonces" },
    { label: "En attente de validation", value: stats.listings.pending, color: "text-orange-500", href: "/admin/moderation" },
    { label: "Publiées (total)", value: stats.listings.published_total, color: "text-slate-900", href: "/admin/annonces" },
    { label: "Refusées", value: stats.listings.rejected_total, color: "text-red-600", href: "/admin/annonces" },
    { label: "Utilisateurs", value: stats.users.total, color: "text-slate-900", href: "/admin/users" },
    { label: "Pros vérifiés", value: stats.users.verified_pros, color: "text-green-600", href: "/admin/users" },
    { label: "Nouveaux ce mois", value: stats.users.new_this_month, color: "text-blue-600", href: "/admin/users" },
    { label: "Comptes bannis", value: stats.users.banned, color: "text-red-600", href: "/admin/users" },
  ];

  return (
    <div className="pb-16 md:pb-0">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Tableau de bord</h1>
          <p className="text-sm text-slate-500">Vue d&apos;ensemble de la plateforme.</p>
        </div>
        <Link
          href="/admin/moderation"
          className="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
        >
          File de modération ({stats.listings.pending})
        </Link>
      </div>

      {/* Cartes KPIs */}
      <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        {cards.map((c) => (
          <Link
            key={c.label}
            href={c.href}
            className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:ring-orange-200"
          >
            <p className="text-sm text-slate-500">{c.label}</p>
            <p className={`mt-1 text-2xl font-bold ${c.color}`}>{c.value}</p>
          </Link>
        ))}
      </div>

      {/* Graphiques */}
      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Activité (14 derniers jours)</h2>
          <div className="mt-3">
            <MiniBars data={stats.daily} />
          </div>
          <div className="mt-3 flex gap-4 text-xs text-slate-500">
            <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded bg-orange-400" /> Annonces créées</span>
            <span className="flex items-center gap-1"><span className="h-2.5 w-2.5 rounded bg-slate-300" /> Inscriptions</span>
          </div>
        </div>

        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Modération</h2>
          <ul className="mt-3 space-y-2 text-sm">
            <li className="flex justify-between"><span className="text-slate-600">En attente</span><b className="text-orange-600">{stats.moderation.pending}</b></li>
            <li className="flex justify-between"><span className="text-slate-600">Actions réalisées</span><b>{stats.moderation.total_actions}</b></li>
            <li className="flex justify-between"><span className="text-slate-600">Taux d&apos;approbation</span><b>{stats.moderation.approval_rate ?? "—"}%</b></li>
            <li className="flex justify-between"><span className="text-slate-600">Temps moyen (min)</span><b>{stats.moderation.avg_moderation_minutes ?? "—"}</b></li>
          </ul>
          {stats.moderation.by_moderator.length > 0 && (
            <div className="mt-3 border-t border-slate-100 pt-3">
              {stats.moderation.by_moderator.map((m) => (
                <div key={m.moderator} className="flex items-center justify-between text-xs text-slate-600">
                  <span>{m.moderator}</span>
                  <span>
                    <b className="text-green-600">{m.approved}</b> ✓ · <b className="text-red-600">{m.rejected}</b> ✕ · {m.total}
                  </span>
                </div>
              ))}
            </div>
          )}
          <Link href="/admin/moderation" className="mt-3 inline-block text-sm font-semibold text-orange-600 hover:underline">
            Ouvrir la file de modération →
          </Link>
        </div>
      </div>

      {/* Activité récente */}
      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Dernières annonces</h2>
          <ul className="mt-3 space-y-3">
            {stats.recents.announcements.map((a) => (
              <li key={a.id} className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <Link href={`/annonces/${a.slug}`} className="truncate text-sm font-medium text-slate-900 hover:text-orange-600">
                    {a.title}
                  </Link>
                  <p className="text-xs text-slate-500">
                    {a.seller_name ?? "—"} · {a.price_formatted}
                  </p>
                </div>
                <span className={`shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ${STATUS_BADGE[a.status] ?? "bg-slate-100 text-slate-600"}`}>
                  {STATUS_LABELS[a.status] ?? a.status}
                </span>
              </li>
            ))}
            {stats.recents.announcements.length === 0 && <li className="text-sm text-slate-400">Aucune annonce.</li>}
          </ul>
        </div>

        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Dernières actions de modération</h2>
          <ul className="mt-3 space-y-3">
            {stats.recents.moderation_actions.map((m) => (
              <li key={m.id} className="text-sm">
                <p className="flex items-center gap-1.5">
                  <span className={`h-2 w-2 rounded-full ${m.action === "approved" ? "bg-green-500" : m.action === "rejected" ? "bg-red-500" : "bg-amber-400"}`} />
                  <span className="font-medium text-slate-900">{m.action === "approved" ? "Validée" : m.action === "rejected" ? "Refusée" : m.action}</span>
                  <span className="text-slate-500">par {m.moderator_name ?? "—"}</span>
                </p>
                <p className="ml-3.5 text-xs text-slate-500">{m.announcement_title ?? "—"}</p>
              </li>
            ))}
            {stats.recents.moderation_actions.length === 0 && <li className="text-sm text-slate-400">Aucune action.</li>}
          </ul>
        </div>

        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
          <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Nouveaux utilisateurs</h2>
          <ul className="mt-3 space-y-3">
            {stats.recents.users.map((u) => (
              <li key={u.id} className="flex items-center justify-between gap-2">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-slate-900">{u.name}</p>
                  <p className="truncate text-xs text-slate-500">{u.email}</p>
                </div>
                <div className="flex shrink-0 gap-1 text-[11px]">
                  {u.is_banned && <span className="rounded bg-red-100 px-1.5 py-0.5 font-semibold text-red-700">Banni</span>}
                  {u.is_verified_pro && <span className="rounded bg-green-100 px-1.5 py-0.5 font-semibold text-green-700">Pro ✓</span>}
                </div>
              </li>
            ))}
            {stats.recents.users.length === 0 && <li className="text-sm text-slate-400">Aucun utilisateur.</li>}
          </ul>
        </div>
      </div>
    </div>
  );
}
