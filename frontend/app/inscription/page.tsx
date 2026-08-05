"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { register, ApiError } from "@/lib/api";

export default function InscriptionPage() {
  const router = useRouter();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [accountType, setAccountType] = useState("particulier");
  const [error, setError] = useState<Record<string, string> | string>("");
  const [loading, setLoading] = useState(false);

  const passwordChecks = useMemo(
    () => ({
      length: password.length >= 8,
      upper: /[A-Z]/.test(password),
      lower: /[a-z]/.test(password),
      special: /[^A-Za-z0-9]/.test(password),
    }),
    [password],
  );
  const passwordValid = Object.values(passwordChecks).every(Boolean);
  const confirmOk = password === passwordConfirmation && password.length > 0;

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      const res = await register({
        name,
        email,
        phone,
        password,
        password_confirmation: passwordConfirmation,
        account_type: accountType,
      });
      // Pas de session : il faut d'abord vérifier l'e-mail avec le code OTP reçu.
      sessionStorage.setItem("pendingEmail", res.user.email);
      if (res.dev_code) sessionStorage.setItem("devCode", res.dev_code);
      router.push(`/verification?email=${encodeURIComponent(res.user.email)}`);
    } catch (err) {
      if (err instanceof ApiError && err.data?.errors) {
        setError(err.data.errors);
      } else {
        setError(err instanceof ApiError ? err.message : "Une erreur est survenue.");
      }
    } finally {
      setLoading(false);
    }
  }

  const inputCls =
    "w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500";

  function fieldError(field: string) {
    return typeof error === "object" ? error[field] : undefined;
  }

  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <h1 className="text-2xl font-bold text-slate-900">Créer un compte</h1>
      <p className="mt-1 text-sm text-slate-500">
        Particulier (2 annonces gratuites) ou professionnel de l&apos;automobile.
        Un code de vérification vous sera envoyé par e-mail.
      </p>

      {typeof error === "string" && error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}

      <form onSubmit={onSubmit} className="mt-6 space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Type de compte</label>
          <div className="grid grid-cols-2 gap-2">
            {[
              { value: "particulier", label: "Particulier", hint: "2 annonces gratuites" },
              { value: "professionnel", label: "Professionnel", hint: "Badge & page vitrine" },
            ].map((opt) => (
              <button
                type="button"
                key={opt.value}
                onClick={() => setAccountType(opt.value)}
                className={`rounded-lg border px-3 py-2 text-left text-sm transition ${
                  accountType === opt.value
                    ? "border-orange-500 bg-orange-50"
                    : "border-slate-300 bg-white hover:bg-slate-50"
                }`}
              >
                <span className="block font-semibold text-slate-800">{opt.label}</span>
                <span className="block text-xs text-slate-500">{opt.hint}</span>
              </button>
            ))}
          </div>
        </div>

        {[
          { label: "Nom complet", state: name, set: setName, type: "text", placeholder: "Jean Kouassi", field: "name" },
          { label: "Email", state: email, set: setEmail, type: "email", placeholder: "vous@exemple.ci", field: "email" },
          { label: "Téléphone", state: phone, set: setPhone, type: "tel", placeholder: "+2250700000000", field: "phone" },
          { label: "Mot de passe", state: password, set: setPassword, type: "password", placeholder: "8 caractères min.", field: "password" },
          { label: "Confirmation", state: passwordConfirmation, set: setPasswordConfirmation, type: "password", placeholder: "••••••••", field: "password_confirmation" },
        ].map((f) => (
          <div key={f.field}>
            <label className="mb-1 block text-sm font-medium text-slate-700">{f.label}</label>
            <input
              type={f.type}
              value={f.state}
              onChange={(e) => f.set(e.target.value)}
              required={["name", "email", "phone", "password", "password_confirmation"].includes(f.field)}
              placeholder={f.placeholder}
              className={inputCls}
            />
            {fieldError(f.field) && <p className="mt-1 text-xs text-red-600">{fieldError(f.field)}</p>}
            {f.field === "password" && (
              <ul className="mt-2 space-y-1 text-xs">
                {[
                  { ok: passwordChecks.length, label: "8 caractères minimum" },
                  { ok: passwordChecks.upper, label: "Une lettre majuscule (A-Z)" },
                  { ok: passwordChecks.lower, label: "Une lettre minuscule (a-z)" },
                  { ok: passwordChecks.special, label: "Un caractère spécial (!@#$…)" },
                ].map((c) => (
                  <li key={c.label} className={c.ok ? "text-green-600" : "text-slate-400"}>
                    {c.ok ? "✓" : "○"} {c.label}
                  </li>
                ))}
              </ul>
            )}
            {f.field === "password_confirmation" && password.length > 0 && !confirmOk && (
              <p className="mt-1 text-xs text-red-600">Les mots de passe ne correspondent pas.</p>
            )}
          </div>
        ))}

        <button
          type="submit"
          disabled={loading || !passwordValid || !confirmOk}
          className="w-full rounded-lg bg-orange-500 px-4 py-2.5 font-semibold text-white hover:bg-orange-600 disabled:opacity-60"
        >
          {loading ? "Création..." : "Créer mon compte"}
        </button>
      </form>

      <p className="mt-5 text-center text-sm text-slate-500">
        Déjà inscrit ?{" "}
        <Link href="/connexion" className="font-semibold text-orange-600 hover:underline">
          Se connecter
        </Link>
      </p>
    </div>
  );
}