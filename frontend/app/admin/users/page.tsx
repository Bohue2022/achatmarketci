"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";

interface AdminUser {
  id: number;
  name: string;
  email: string;
  phone: string;
  role: string;
  is_verified_pro: boolean;
  company_name?: string | null;
  rccm_number?: string | null;
  kyc_verified_at?: string | null;
  is_banned: boolean;
  banned_at?: string | null;
  created_at?: string | null;
  announcements_count: number;
  pending_count: number;
  published_count: number;
  plan?: string | null;
}

interface ListResponse {
  data: AdminUser[];
  meta: { current_page: number; last_page: number; total: number };
}

const ROLE_LABELS: Record<string, string> = { user: "Particulier", pro: "Professionnel", moderator: "Modérateur", admin: "Admin" };

export default function AdminUsersPage() {
  const [list, setList] = useState<ListResponse | null>(null);
  const [q, setQ] = useState("");
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState(false);
  const [banFor, setBanFor] = useState<AdminUser | null>(null);
  const [reason, setReason] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    const params = new URLSearchParams({ page: String(page), per_page: "10" });
    if (q) params.set("q", q);
    if (role) params.set("role", role);
    if (status) params.set("status", status);
    api<ListResponse>(`/admin/users?${params}`)
      .then(setList)
      .catch((e: unknown) => setError(e instanceof Error ? e.message : "Erreur serveur"));
  }, [q, role, status, page]);

  async function toggleBan(user: AdminUser) {
    setBusy(true);
    try {
      if (user.is_banned) {
        await api(`/admin/users/${user.id}/unban`, { method: "POST" });
      } else {
        await api(`/admin/users/${user.id}/ban`, { method: "POST", body: JSON.stringify({ reason }) });
      }
      setBanFor(null);
      setReason("");
      setList((prev) =>
        prev
          ? { ...prev, data: prev.data.map((u) => (u.id === user.id ? { ...u, is_banned: !u.is_banned } : u)) }
          : prev,
      );
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Erreur serveur");
    } finally {
      setBusy(false);
    }
  }

  async function toggleKyc(user: AdminUser) {
    setBusy(true);
    try {
      await api(`/admin/users/${user.id}/kyc`, {
        method: "POST",
        body: JSON.stringify({ verified: !user.is_verified_pro, rccm_number: user.rccm_number }),
      });
      setList((prev) =>
        prev
          ? { ...prev, data: prev.data.map((u) => (u.id === user.id ? { ...u, is_verified_pro: !u.is_verified_pro } : u)) }
          : prev,
      );
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Erreur serveur");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="pb-16 md:pb-0">
      <h1 className="text-2xl font-bold text-slate-900">Utilisateurs</h1>
      <p className="text-sm text-slate-500">{list ? `${list.meta.total} utilisateur(s)` : "Chargement..."}</p>

      {/* Filtres */}
      <div className="mt-4 flex flex-wrap gap-2">
        <input
          value={q}
          onChange={(e) => { setQ(e.target.value); setPage(1); }}
          placeholder="Rechercher (nom, email, société)…"
          className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
        />
        <select value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none">
          <option value="">Tous les rôles</option>
          {Object.entries(ROLE_LABELS).map(([k, v]) => (
            <option key={k} value={k}>{v}</option>
          ))}
        </select>
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none">
          <option value="">Tous les statuts</option>
          <option value="banned">Bannis</option>
          <option value="verified_pro">Pros vérifiés</option>
        </select>
      </div>

      {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

      {/* Table */}
      <div className="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-100">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead>
            <tr className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
              <th className="px-4 py-3">Utilisateur</th>
              <th className="px-4 py-3">Rôle</th>
              <th className="px-4 py-3">KYC</th>
              <th className="px-4 py-3">Annonces</th>
              <th className="px-4 py-3">Créé le</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-50">
            {list?.data.map((u) => (
              <tr key={u.id} className={u.is_banned ? "bg-red-50/40" : ""}>
                <td className="px-4 py-3">
                  <p className="font-medium text-slate-900">{u.name}</p>
                  <p className="text-xs text-slate-500">{u.email}</p>
                  {u.company_name && <p className="text-xs text-slate-400">{u.company_name}</p>}
                </td>
                <td className="px-4 py-3">
                  <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                    {ROLE_LABELS[u.role] ?? u.role}
                  </span>
                  {u.is_banned && <span className="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Banni</span>}
                </td>
                <td className="px-4 py-3">
                  {u.is_verified_pro ? (
                    <span className="text-xs font-semibold text-green-700">Vérifié ✓</span>
                  ) : (
                    <span className="text-xs text-slate-400">{u.rccm_number ? "RCCM: " + u.rccm_number : "Non vérifié"}</span>
                  )}
                </td>
                <td className="px-4 py-3 text-xs text-slate-600">
                  <b>{u.announcements_count}</b> total
                  <span className="text-amber-600"> · {u.pending_count} attente</span>
                  <span className="text-green-600"> · {u.published_count} publiées</span>
                </td>
                <td className="px-4 py-3 text-xs text-slate-500">
                  {u.created_at ? new Date(u.created_at).toLocaleDateString("fr-FR") : "—"}
                </td>
                <td className="px-4 py-3 text-right">
                  <div className="flex justify-end gap-2">
                    <button
                      onClick={() => toggleKyc(u)}
                      disabled={busy}
                      className={`rounded-lg px-2.5 py-1 text-xs font-semibold ${
                        u.is_verified_pro ? "bg-slate-100 text-slate-600 hover:bg-slate-200" : "bg-green-50 text-green-700 hover:bg-green-100"
                      }`}
                    >
                      {u.is_verified_pro ? "Retirer KYC" : "Valider KYC"}
                    </button>
                    {u.is_banned ? (
                      <button
                        onClick={() => toggleBan(u)}
                        disabled={busy}
                        className="rounded-lg bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 hover:bg-green-100"
                      >
                        Réactiver
                      </button>
                    ) : (
                      <button
                        onClick={() => { setBanFor(u); setReason(""); }}
                        disabled={busy}
                        className="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100"
                      >
                        Bannir
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {list && list.data.length === 0 && <p className="p-6 text-center text-sm text-slate-400">Aucun utilisateur trouvé.</p>}
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

      {/* Modale de bannissement */}
      {banFor && (
        <div className="fixed inset-0 z-30 flex items-center justify-center bg-black/40 p-4" onClick={() => setBanFor(null)}>
          <div className="w-full max-w-md rounded-xl bg-white p-5" onClick={(e) => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-slate-900">Bannir {banFor.name}</h2>
            <p className="mt-1 text-sm text-slate-500">Le compte ne pourra plus se connecter ni publier.</p>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Motif du bannissement (min. 5 caractères)…"
              rows={3}
              className="mt-3 w-full rounded-lg border border-slate-200 p-2 text-sm outline-none focus:border-red-400"
            />
            <div className="mt-4 flex justify-end gap-2">
              <button onClick={() => setBanFor(null)} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium">Annuler</button>
              <button
                onClick={() => toggleBan(banFor)}
                disabled={busy || reason.trim().length < 5}
                className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-40"
              >
                Confirmer le bannissement
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
