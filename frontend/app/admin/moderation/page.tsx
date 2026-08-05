"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError, isLoggedIn } from "@/lib/api";
import type { Announcement } from "@/lib/types";

type Action = "approved" | "rejected";

export default function ModerationPage() {
  const router = useRouter();
  const [annonces, setAnnonces] = useState<Announcement[]>([]);
  const [pendingCount, setPendingCount] = useState(0);
  const [selected, setSelected] = useState<number[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [bulkAction, setBulkAction] = useState<Action>("approved");

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    let active = true;
    api<{ data: Announcement[]; counts: { pending: number } }>("/admin/moderation/queue")
      .then((res) => {
        if (!active) return;
        setAnnonces(res.data);
        setPendingCount(res.counts.pending);
      })
      .catch(() => {
        if (active) setError("Impossible de charger la file de modération.");
      })
      .finally(() => setLoading(false));
    return () => {
      active = false;
    };
  }, [router]);

  function toggle(id: number) {
    setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
  }

  function reload() {
    api<{ data: Announcement[]; counts: { pending: number } }>("/admin/moderation/queue").then((res) => {
      setAnnonces(res.data);
      setPendingCount(res.counts.pending);
    });
  }

  async function moderate(annonce: Announcement, action: Action, reason?: string) {
    setError("");
    try {
      await api(`/admin/moderation/${annonce.id}/moderate`, {
        method: "POST",
        body: JSON.stringify({ action, reason }),
      });
      reload();
    } catch (e) {
      if (e instanceof ApiError) setError(e.data?.errors?.reason?.[0] ?? e.message ?? "Action impossible.");
      else setError("Action impossible.");
    }
  }

  async function moderateBulk() {
    if (!selected.length) return;
    setError("");
    try {
      await api("/admin/moderation/bulk", {
        method: "POST",
        body: JSON.stringify({ ids: selected, action: bulkAction }),
      });
      setSelected([]);
      reload();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Action impossible.");
    }
  }

  if (loading) return <div className="p-10 text-center text-slate-500">Chargement...</div>;

  return (
    <div className="pb-16 md:pb-0">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">File de modération</h1>
          <p className="text-sm text-slate-500">{pendingCount} annonce(s) en attente de validation.</p>
        </div>
      </div>

      {error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}

      {/* Actions en masse */}
      <div className="mt-6 flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
        <select
          value={bulkAction}
          onChange={(e) => setBulkAction(e.target.value as Action)}
          className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
        >
          <option value="approved">Valider</option>
          <option value="rejected">Refuser</option>
        </select>
        <button
          onClick={moderateBulk}
          disabled={!selected.length}
          className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-40"
        >
          Appliquer à {selected.length || 0} annonce(s)
        </button>
      </div>

      {/* Liste */}
      <div className="mt-4 space-y-3">
        {annonces.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white py-12 text-center text-slate-500">
            Aucune annonce en attente. 🎉
          </div>
        )}

        {annonces.map((a) => (
          <div key={a.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
            <div className="flex flex-wrap items-start gap-3">
              <input
                type="checkbox"
                checked={selected.includes(a.id)}
                onChange={() => toggle(a.id)}
                className="mt-1 h-5 w-5"
              />
              <div className="h-16 w-24 flex-none overflow-hidden rounded-lg bg-slate-100">
                {a.photos[0] ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={a.photos[0].url} alt="" className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-slate-300">🚗</div>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-slate-900">{a.title}</p>
                <p className="text-sm text-orange-600">{a.price_formatted}</p>
                <p className="mt-0.5 truncate text-sm text-slate-500">
                  {a.brand?.name} {a.model?.name} · {a.city?.name} {a.commune ? `· ${a.commune.name}` : ""} · {a.body_type ?? ""} · {a.year ?? ""}
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                  <span>
                    Vendeur : {a.seller?.company_name ?? a.seller?.name}
                    {a.seller?.role === "pro" ? " (Pro)" : ""}
                  </span>
                  <span>· Dédouané : {a.is_dedouane ? "Oui" : "Non"}</span>
                </div>
              </div>
              <div className="flex shrink-0 gap-2">
                <button
                  onClick={() => moderate(a, "approved")}
                  className="rounded-lg bg-green-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-green-700"
                >
                  Valider
                </button>
                <button
                  onClick={() => {
                    const reason = window.prompt("Motif du refus (obligatoire) :");
                    if (reason && reason.trim()) moderate(a, "rejected", reason.trim());
                  }}
                  className="rounded-lg bg-red-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
                >
                  Refuser
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}