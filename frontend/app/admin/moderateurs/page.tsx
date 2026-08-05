"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";

interface Moderator {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at?: string | null;
}

interface Moment {
  email: string;
  password: string;
}

const EMPTY = { name: "", email: "", phone: "", password: "", password_confirmation: "" };

export default function AdminModeratorsPage() {
  const [list, setList] = useState<Moderator[]>([]);
  const [form, setForm] = useState({ ...EMPTY });
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [error, setError] = useState("");
  const [created, setCreated] = useState<Moment | null>(null);
  const [showPass, setShowPass] = useState(false);

  const load = () => api<{ data: Moderator[] }>("/admin/moderators").then((r) => setList(r.data)).catch(() => {});

  useEffect(() => {
    load();
  }, []);

  function set(field: string, value: string) {
    setForm((f) => ({ ...f, [field]: value }));
    setError("");
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setCreated(null);
    setSaving(true);
    try {
      await api<{ data: Moderator }>("/admin/moderators", {
        method: "POST",
        body: JSON.stringify(form),
      });
      setCreated({ email: form.email, password: form.password });
      setForm({ ...EMPTY });
      await load();
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Impossible de créer le compte.");
    } finally {
      setSaving(false);
    }
  }

  async function remove(m: Moderator) {
    if (!window.confirm(`Retirer le modérateur « ${m.name} » ? Il ne pourra plus se connecter.`)) return;
    setDeleting(m.id);
    setError("");
    try {
      await api(`/admin/moderators/${m.id}`, { method: "DELETE" });
      setList((prev) => prev.filter((x) => x.id !== m.id));
      setCreated(null);
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : "Impossible de retirer le compte.");
    } finally {
      setDeleting(null);
    }
  }

  const valid = form.name.trim() && form.email.trim() && form.password.length >= 8 && form.password === form.password_confirmation;

  return (
    <div className="pb-16 md:pb-0">
      <h1 className="text-2xl font-bold text-slate-900">Modérateurs</h1>
      <p className="text-sm text-slate-500">
        Créer des comptes modérateurs dédiés. Chaque modérateur se connecte à l&apos;espace avec ses propres identifiants.
      </p>

      {error && <p className="mt-3 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700 ring-1 ring-red-200">{error}</p>}
      {created && (
        <div className="mt-3 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
          <p className="font-semibold">Compte créé. Identifiants à transmettre au modérateur :</p>
          <p className="mt-1">👤 {created.email}</p>
          <p>🔑 {created.password}</p>
        </div>
      )}

      {/* Formulaire de création */}
      <form onSubmit={submit} className="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">Nouveau modérateur</h2>
        <div className="mt-3 grid gap-3 md:grid-cols-2">
          <div>
            <label className="text-xs font-medium text-slate-500">Nom complet</label>
            <input
              value={form.name}
              onChange={(e) => set("name", e.target.value)}
              required
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
              placeholder="Ex. Moussa Diarra"
            />
          </div>
          <div>
            <label className="text-xs font-medium text-slate-500">Téléphone (optionnel)</label>
            <input
              value={form.phone}
              onChange={(e) => set("phone", e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
              placeholder="+22500000000"
            />
          </div>
          <div className="md:col-span-2">
            <label className="text-xs font-medium text-slate-500">Adresse e-mail (identifiant de connexion)</label>
            <input
              type="email"
              value={form.email}
              onChange={(e) => set("email", e.target.value)}
              required
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
              placeholder="moderateur@rr.ci"
            />
          </div>
          <div>
            <label className="text-xs font-medium text-slate-500">Mot de passe (min. 8 caractères)</label>
            <input
              type={showPass ? "text" : "password"}
              value={form.password}
              onChange={(e) => set("password", e.target.value)}
              required
              minLength={8}
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
            />
          </div>
          <div>
            <label className="text-xs font-medium text-slate-500">Confirmer le mot de passe</label>
            <input
              type={showPass ? "text" : "password"}
              value={form.password_confirmation}
              onChange={(e) => set("password_confirmation", e.target.value)}
              required
              className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400"
            />
          </div>
        </div>
        <div className="mt-4 flex items-center gap-3">
          <button
            type="submit"
            disabled={saving || !valid}
            className="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-40"
          >
            {saving ? "Création..." : "Créer le compte modérateur"}
          </button>
          <label className="flex cursor-pointer items-center gap-1.5 text-xs text-slate-500">
            <input type="checkbox" checked={showPass} onChange={(e) => setShowPass(e.target.checked)} />
            Afficher les mots de passe
          </label>
        </div>
      </form>

      {/* Liste */}
      <div className="mt-5">
        <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500">
          Modérateurs actifs ({list.length})
        </h2>
        {list.length === 0 && (
          <div className="mt-3 rounded-xl border border-dashed border-slate-300 bg-white py-10 text-center text-sm text-slate-500">
            Aucun modérateur pour l&apos;instant.
          </div>
        )}
        <div className="mt-3 space-y-2">
          {list.map((m) => (
            <div key={m.id} className="flex items-center justify-between rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-slate-100">
              <div>
                <p className="font-medium text-slate-900">{m.name}</p>
                <p className="text-xs text-slate-500">{m.email} · créé {m.created_at ? new Date(m.created_at).toLocaleDateString("fr-FR") : "—"}</p>
              </div>
              <button
                onClick={() => remove(m)}
                disabled={deleting === m.id}
                className="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-40"
              >
                {deleting === m.id ? "Retrait..." : "Retirer"}
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}