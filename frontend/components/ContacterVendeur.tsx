"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { api, ApiError, isLoggedIn } from "@/lib/api";
import type { Conversation } from "@/lib/types";

export default function ContacterVendeur({ slug, sellerId }: { slug: string; sellerId?: number }) {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState("");

  function onOpen() {
    if (!isLoggedIn()) {
      router.push("/connexion");
      return;
    }
    setError("");
    setOpen(true);
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    const text = message.trim();
    if (!text || sending) return;
    setSending(true);
    setError("");
    try {
      const res = await api<{ data: Conversation }>(`/announcements/${slug}/messages`, {
        method: "POST",
        body: JSON.stringify({ message: text }),
      });
      router.push(`/messages/${res.data.id}`);
    } catch (err) {
      if (err instanceof ApiError && err.data?.code === "self_conversation") {
        setError("C'est votre propre annonce, vous ne pouvez pas vous contacter.");
      } else {
        setError(err instanceof ApiError ? err.message : "Échec de l'envoi du message.");
      }
    } finally {
      setSending(false);
    }
  }

  return (
    <>
      <button
        type="button"
        onClick={onOpen}
        className="mt-3 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800"
      >
        💬 Discuter avec le vendeur
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => !sending && setOpen(false)}>
          <form
            onClick={(e) => e.stopPropagation()}
            onSubmit={onSubmit}
            className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"
          >
            <h2 className="text-lg font-bold text-slate-900">Envoyer un message</h2>
            <p className="mt-1 text-sm text-slate-500">
              Le vendeur recevra votre message et pourra vous répondre dans la messagerie.
            </p>
            <textarea
              autoFocus
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              rows={4}
              placeholder="Bonjour, ce véhicule est-il toujours disponible ?"
              className="mt-4 w-full resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
            />
            {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setOpen(false)}
                disabled={sending}
                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                Annuler
              </button>
              <button
                type="submit"
                disabled={sending || !message.trim()}
                className="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50"
              >
                {sending ? "Envoi..." : "Envoyer"}
              </button>
            </div>
          </form>
        </div>
      )}
    </>
  );
}