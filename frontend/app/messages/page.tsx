"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError, isLoggedIn } from "@/lib/api";
import type { Conversation } from "@/lib/types";

export default function MessagesPage() {
  const router = useRouter();
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    api<{ data: Conversation[] }>("/conversations")
      .then((r) => setConversations(r.data))
      .catch((err) => setError(err instanceof ApiError ? err.message : "Impossible de charger vos messages."))
      .finally(() => setLoading(false));
  }, [router]);

  function formatDate(iso?: string | null) {
    if (!iso) return "";
    return new Date(iso).toLocaleDateString("fr-FR", { day: "2-digit", month: "short" });
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <h1 className="text-2xl font-bold text-slate-900">Messages</h1>
      <p className="mt-1 text-sm text-slate-500">Vos échanges avec acheteurs et vendeurs.</p>

      {error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}

      {loading ? (
        <p className="mt-8 text-center text-sm text-slate-500">Chargement...</p>
      ) : conversations.length === 0 ? (
        <div className="mt-10 rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
          <p className="text-slate-500">Aucune conversation pour le moment.</p>
          <p className="mt-1 text-sm text-slate-400">
            Contactez un vendeur depuis une annonce pour démarrer une discussion.
          </p>
          <Link
            href="/"
            className="mt-4 inline-block rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
          >
            Parcourir les véhicules
          </Link>
        </div>
      ) : (
        <div className="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-100">
          {conversations.map((c, i) => (
            <Link
              key={c.id}
              href={`/messages/${c.id}`}
              className={`flex items-center gap-4 p-4 transition hover:bg-slate-50 ${i > 0 ? "border-t border-slate-100" : ""}`}
            >
              <div className="relative block aspect-[4/3] w-20 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                {c.announcement?.cover ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={c.announcement.cover} alt="" className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full items-center justify-center text-slate-300">
                    <span className="text-2xl">🚗</span>
                  </div>
                )}
              </div>

              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-3">
                  <p className="truncate font-semibold text-slate-900">
                    {c.other_party?.name}
                    {c.other_party?.is_verified_pro && (
                      <span className="ml-2 rounded bg-green-100 px-1.5 py-0.5 text-xs font-semibold text-green-700">Pro</span>
                    )}
                  </p>
                  <span className="shrink-0 text-xs text-slate-400">{formatDate(c.updated_at)}</span>
                </div>
                <p className="truncate text-sm text-slate-500">
                  {c.announcement?.title ?? "Annonce"} —{" "}
                  {c.last_message ? (
                    <span className={c.unread_count > 0 ? "font-semibold text-slate-700" : ""}>{c.last_message.body}</span>
                  ) : (
                    "Nouvelle conversation"
                  )}
                </p>
              </div>

              {c.unread_count > 0 && (
                <span className="rounded-full bg-orange-500 px-2.5 py-1 text-xs font-bold text-white">
                  {c.unread_count}
                </span>
              )}
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}