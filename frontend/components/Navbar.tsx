"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import { api, clearSession, fetchMe, isLoggedIn } from "@/lib/api";
import type { User } from "@/lib/types";

export default function Navbar() {
  const pathname = usePathname();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    if (!isLoggedIn()) {
      setUser(null);
      setUnread(0);
      setLoading(false);
      return;
    }
    let active = true;
    fetchMe()
      .then((r) => {
        if (active) setUser(r.user);
      })
      .catch(() => {
        clearSession();
        if (active) setUser(null);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    api<{ unread: number }>("/conversations/unread-count")
      .then((r) => active && setUnread(Number(r.unread)))
      .catch(() => active && setUnread(0));
    return () => {
      active = false;
    };
    // Rafraîchit le profil à chaque changement de route (après connexion/déconnexion).
  }, [pathname]);

  return (
    <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-4 px-4">
        <Link href="/" className="text-lg font-bold text-slate-900">
          AutoMarket<span className="text-orange-500">CI</span>
        </Link>

        <nav className="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
          <Link href="/" className="hover:text-slate-900">Véhicules</Link>
          <Link href="/deposer-annonce" className="hover:text-slate-900">Déposer une annonce</Link>
          {user && (
            <Link href="/mon-profil" className="hover:text-slate-900">Mon profil</Link>
          )}
          {user && (
            <Link href="/mes-annonces" className="hover:text-slate-900">Mes annonces</Link>
          )}
          {user && (
            <Link href="/messages" className="relative hover:text-slate-900">
              Messages
              {unread > 0 && (
                <span className="ml-1 inline-flex min-w-[18px] items-center justify-center rounded-full bg-orange-500 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white">
                  {unread}
                </span>
              )}
            </Link>
          )}
          {user?.is_moderator && (
            <Link href="/admin" className="hover:text-slate-900">Administration</Link>
          )}
        </nav>

        <div className="flex items-center gap-3 text-sm">
          {!loading &&
            (user ? (
              <>
                <span className="hidden text-slate-600 sm:inline">{user.name}</span>
                {user.role === "pro" && user.is_verified_pro && (
                  <span className="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                    Pro vérifié
                  </span>
                )}
                <button
                  onClick={() => {
                    clearSession();
                    window.location.href = "/";
                  }}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50"
                >
                  Déconnexion
                </button>
              </>
            ) : (
              <>
                <Link
                  href="/connexion"
                  className="px-3 py-1.5 font-medium text-slate-700 hover:text-slate-900"
                >
                  Connexion
                </Link>
                <Link
                  href="/inscription"
                  className="rounded-lg bg-orange-500 px-3 py-1.5 font-semibold text-white hover:bg-orange-600"
                >
                  Créer un compte
                </Link>
              </>
            ))}
        </div>
      </div>
    </header>
  );
}