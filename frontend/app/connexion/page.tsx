"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { login, ApiError, setSession } from "@/lib/api";

export default function ConnexionPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError("");
    setLoading(true);
    // Lecture directe depuis le formulaire : robuste face à l'autofill du navigateur
    // (qui peut remplir visuellement les champs sans mettre à jour l'état React).
    const data = new FormData(e.currentTarget);
    const emailVal = String(data.get("email") ?? "").trim();
    const passwordVal = String(data.get("password") ?? "");
    try {
      const res = await login(emailVal, passwordVal);
      setSession(res.token);
      router.push("/");
      router.refresh();
    } catch (err) {
      if (err instanceof ApiError && err.status === 403 && err.data?.code === "email_not_verified") {
        router.push(`/verification?email=${encodeURIComponent(err.data?.data?.email ?? emailVal)}`);
        return;
      }
      setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
    } finally {
      setLoading(false);
    }
  }

  const inputCls =
    "w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500";

  return (
    <div className="mx-auto flex max-w-md flex-col px-4 py-12">
      <h1 className="text-2xl font-bold text-slate-900">Connexion</h1>
      <p className="mt-1 text-sm text-slate-500">Accédez à votre espace AutoMarket CI.</p>

      {error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
          {error}
        </div>
      )}

      <form onSubmit={onSubmit} className="mt-6 space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Email</label>
          <input
            type="email"
            name="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="email"
            required
            className={inputCls}
            placeholder="vous@exemple.ci"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Mot de passe</label>
          <input
            type="password"
            name="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete="current-password"
            required
            className={inputCls}
            placeholder="••••••••"
          />
        </div>
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-orange-500 px-4 py-2.5 font-semibold text-white hover:bg-orange-600 disabled:opacity-60"
        >
          {loading ? "Connexion..." : "Se connecter"}
        </button>
      </form>

      <p className="mt-5 text-center text-sm text-slate-500">
        Pas encore de compte ?{" "}
        <Link href="/inscription" className="font-semibold text-orange-600 hover:underline">
          S&apos;inscrire
        </Link>
      </p>
    </div>
  );
}