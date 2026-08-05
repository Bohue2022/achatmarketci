"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { ApiError, resendOtp, setSession, verifyOtp } from "@/lib/api";

function readStored(key: string): string {
  if (typeof window === "undefined") return "";
  return window.sessionStorage.getItem(key) ?? "";
}

export default function VerificationPage() {
  const router = useRouter();
  const [email, setEmail] = useState(() => {
    if (typeof window === "undefined") return "";
    const fromQuery = new URLSearchParams(window.location.search).get("email");
    return fromQuery ?? readStored("pendingEmail");
  });
  const [digits, setDigits] = useState<string[]>(Array(6).fill(""));
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [loading, setLoading] = useState(false);
  const [cooldown, setCooldown] = useState(0);
  const [devCode, setDevCode] = useState(() => readStored("devCode"));
  const inputs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    window.sessionStorage.removeItem("devCode");
  }, []);

  useEffect(() => {
    if (cooldown <= 0) return;
    const t = setTimeout(() => setCooldown((c) => c - 1), 1000);
    return () => clearTimeout(t);
  }, [cooldown]);

  function onDigit(i: number, value: string) {
    const cleaned = value.replace(/\D/g, "").slice(-1);
    setDigits((d) => {
      const next = [...d];
      next[i] = cleaned;
      return next;
    });
    if (cleaned && i < 5) inputs.current[i + 1]?.focus();
  }

  function onKeyDown(i: number, e: React.KeyboardEvent<HTMLInputElement>) {
    if (e.key === "Backspace" && !digits[i] && i > 0) inputs.current[i - 1]?.focus();
  }

  function fillFromDev() {
    if (!devCode) return;
    const arr = devCode.slice(0, 6).padEnd(6, "0").split("").slice(0, 6);
    setDigits(arr);
    setDevCode("");
  }

  const otp = digits.join("");

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError("");
    setInfo("");
    if (!email.trim()) {
      setError("Indiquez votre adresse e-mail.");
      return;
    }
    if (otp.length !== 6) {
      setError("Saisissez les 6 chiffres du code.");
      return;
    }
    setLoading(true);
    try {
      const res = await verifyOtp(email.trim(), otp);
      setSession(res.token);
      router.push("/");
      router.refresh();
    } catch (err) {
      if (err instanceof ApiError && err.data?.code === "otp_expired") {
        setError("Le code a expiré. Demandez-en un nouveau.");
        void doResend(true);
      } else {
        setError(err instanceof ApiError ? err.message : "Vérification impossible.");
      }
    } finally {
      setLoading(false);
    }
  }

  async function doResend(auto = false) {
    setError("");
    setInfo("");
    if (!email.trim()) {
      setError("Indiquez votre adresse e-mail.");
      return;
    }
    if (!auto) setLoading(true);
    try {
      const res = await resendOtp(email.trim());
      if (res.dev_code) setDevCode(res.dev_code);
      setInfo("Un nouveau code vient d'être envoyé. Pensez à vérifier les spams.");
      setCooldown(60);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Impossible de renvoyer le code.");
    } finally {
      setLoading(false);
    }
  }

  const inputCls =
    "h-14 w-full rounded-lg border border-slate-300 text-center text-xl font-bold text-slate-900 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500";

  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <h1 className="text-2xl font-bold text-slate-900">Vérifiez votre adresse e-mail</h1>

      {email ? (
        <p className="mt-1 text-sm text-slate-500">
          Un code à 6 chiffres a été envoyé à <b className="text-slate-700">{email}</b>. Saisissez-le ci-dessous pour
          activer votre compte.
        </p>
      ) : (
        <p className="mt-1 text-sm text-slate-500">
          Saisissez votre adresse e-mail pour recevoir (ou renvoyer) le code de vérification.
        </p>
      )}

      {devCode && (
        <div className="mt-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900 ring-1 ring-blue-200">
          <p className="font-semibold">Environnement de développement</p>
          <p className="mt-1">
            Le mail est journalisé, pas réellement envoyé. Votre code de test :{" "}
            <b className="font-mono text-lg">{devCode}</b>
          </p>
          <button
            type="button"
            onClick={fillFromDev}
            className="mt-2 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
          >
            Remplir automatiquement
          </button>
        </div>
      )}

      {error && (
        <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{error}</div>
      )}
      {info && (
        <div className="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">{info}</div>
      )}

      <form onSubmit={submit} className="mt-6 space-y-4">
        {!email && (
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">Adresse e-mail</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500"
              placeholder="vous@exemple.ci"
            />
          </div>
        )}

        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700">Code de vérification</label>
          <div className="flex gap-2">
            {digits.map((d, i) => (
              <input
                key={i}
                ref={(el) => {
                  inputs.current[i] = el;
                }}
                type="text"
                inputMode="numeric"
                autoComplete={i === 0 ? "one-time-code" : "off"}
                value={d}
                onChange={(e) => onDigit(i, e.target.value)}
                onKeyDown={(e) => onKeyDown(i, e)}
                className={inputCls}
                maxLength={1}
              />
            ))}
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-lg bg-orange-500 px-4 py-2.5 font-semibold text-white hover:bg-orange-600 disabled:opacity-60"
        >
          {loading ? "Vérification..." : "Vérifier mon compte"}
        </button>
      </form>

      <div className="mt-5 text-center text-sm text-slate-500">
        {cooldown > 0 ? (
          <span>
            Renvoi possible dans <b>{cooldown}s</b>
          </span>
        ) : (
          <>
            Vous n&apos;avez rien reçu ?{" "}
            <button
              type="button"
              onClick={() => doResend()}
              disabled={loading || !email.trim()}
              className="font-semibold text-orange-600 hover:underline disabled:opacity-60"
            >
              Renvoyer le code
            </button>
          </>
        )}
      </div>

      <p className="mt-5 text-center text-sm text-slate-500">
        Déjà vérifié ?{" "}
        <Link href="/connexion" className="font-semibold text-orange-600 hover:underline">
          Se connecter
        </Link>
      </p>
    </div>
  );
}