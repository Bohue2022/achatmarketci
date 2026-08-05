"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { FormEvent, useEffect, useRef, useState } from "react";
import { api, ApiError, isLoggedIn } from "@/lib/api";
import type { ChatMessage, Conversation } from "@/lib/types";

export default function ConversationPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const [conversation, setConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [draft, setDraft] = useState("");
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);

  async function load() {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    setLoading(true);
    try {
      const res = await api<{ data: Conversation }>(`/conversations/${params.id}`);
      setConversation(res.data);
      setMessages(res.data.messages ?? []);
      setError("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de charger la conversation.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
    // Rafraîchit les nouveaux messages toutes les 5 secondes (pull, pas de WebSocket pour l'instant).
    const timer = setInterval(load, 5000);
    return () => clearInterval(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [params.id]);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages.length]);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    const text = draft.trim();
    if (!text || sending) return;
    setSending(true);
    setError("");
    try {
      const res = await api<{ data: ChatMessage }>(`/conversations/${params.id}/messages`, {
        method: "POST",
        body: JSON.stringify({ message: text }),
      });
      setMessages((m) => [...m, res.data]);
      setDraft("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Échec de l'envoi.");
    } finally {
      setSending(false);
    }
  }

  if (loading) {
    return <p className="py-10 text-center text-sm text-slate-500">Chargement...</p>;
  }

  if (!conversation) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-10 text-center">
        <p className="text-slate-500">{error || "Conversation introuvable."}</p>
        <Link href="/messages" className="mt-3 inline-block text-sm font-semibold text-orange-600 hover:underline">
          ← Retour à mes messages
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto flex h-[calc(100vh-56px)] max-w-2xl flex-col px-4 py-4">
      {/* En-tête */}
      <div className="flex items-center gap-3 border-b border-slate-200 pb-3">
        <Link href="/messages" className="text-slate-400 hover:text-slate-600">
          ←
        </Link>
        <div className="min-w-0">
          <Link
            href={`/profil/${conversation.other_party?.id}`}
            className="font-semibold text-slate-900 hover:text-orange-600"
          >
            {conversation.other_party?.name}
            {conversation.other_party?.is_verified_pro && (
              <span className="ml-2 rounded bg-green-100 px-1.5 py-0.5 text-xs font-semibold text-green-700">Pro</span>
            )}
          </Link>
          <p className="truncate text-xs text-slate-500">
            {conversation.announcement?.title} · {conversation.announcement?.price_formatted}
          </p>
        </div>
        {conversation.announcement?.slug && (
          <Link
            href={`/annonces/${conversation.announcement.slug}`}
            className="ml-auto shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Voir l&apos;annonce
          </Link>
        )}
      </div>

      {error && <p className="mt-2 text-center text-xs text-red-600">{error}</p>}

      {/* Messages */}
      <div className="flex-1 space-y-2 overflow-y-auto py-4">
        {messages.length === 0 && (
          <p className="py-10 text-center text-sm text-slate-400">Aucun message — commencez la discussion.</p>
        )}
        {messages.map((m) => (
          <div key={m.id} className={`flex ${m.is_mine ? "justify-end" : "justify-start"}`}>
            <div
              className={`max-w-[75%] rounded-2xl px-4 py-2 text-sm shadow-sm ${
                m.is_mine ? "rounded-br-sm bg-orange-500 text-white" : "rounded-bl-sm bg-white text-slate-800 ring-1 ring-slate-100"
              }`}
            >
              <p className="whitespace-pre-line">{m.body}</p>
              <p className={`mt-1 text-right text-[10px] ${m.is_mine ? "text-orange-100" : "text-slate-400"}`}>
                {m.created_at ? new Date(m.created_at).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" }) : ""}
                {m.is_mine ? (m.read_at ? " · lu" : " · envoyé") : ""}
              </p>
            </div>
          </div>
        ))}
        <div ref={bottomRef} />
      </div>

      {/* Saisie */}
      <form onSubmit={onSubmit} className="flex gap-2 border-t border-slate-200 pt-3">
        <textarea
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" && !e.shiftKey) {
              e.preventDefault();
              onSubmit(e);
            }
          }}
          rows={1}
          placeholder="Écrire un message..."
          className="flex-1 resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
        />
        <button
          type="submit"
          disabled={sending || !draft.trim()}
          className="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-50"
        >
          {sending ? "..." : "Envoyer"}
        </button>
      </form>
    </div>
  );
}