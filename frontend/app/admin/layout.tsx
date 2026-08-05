"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { clearSession, fetchMe, isLoggedIn } from "@/lib/api";
import type { User } from "@/lib/types";

const NAV = [
  { href: "/admin", label: "Tableau de bord", icon: "📊" },
  { href: "/admin/moderation", label: "Modération", icon: "🛡️" },
  { href: "/admin/annonces", label: "Annonces", icon: "🚗" },
  { href: "/admin/users", label: "Utilisateurs", icon: "👥" },
];

const ADMIN_NAV = [
  { href: "/admin/moderateurs", label: "Modérateurs", icon: "🛠️" },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);

  useEffect(() => {
    if (!isLoggedIn()) {
      router.replace("/connexion");
      return;
    }
    let active = true;
    fetchMe()
      .then((r) => {
        if (!active) return;
        if (!r.user.is_moderator) {
          router.replace("/");
          return;
        }
        setUser(r.user);
      })
      .catch(() => {
        clearSession();
        if (active) router.replace("/connexion");
      });
    return () => {
      active = false;
    };
  }, [router]);

  if (!user) {
    return <div className="p-10 text-center text-slate-500">Vérification des accès...</div>;
  }

return (
      <div className="mx-auto flex max-w-6xl gap-6 px-4 py-8">
        {/* Sidebar */}
        <aside className="hidden w-56 shrink-0 md:block">
          <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
            <p className="px-2 pb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Administration</p>
            <nav className="space-y-1">
              {NAV.map((item) => {
                const active = item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href);
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ${
                      active ? "bg-orange-500 text-white" : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                    }`}
                  >
                    <span>{item.icon}</span> {item.label}
                  </Link>
                );
              })}
              {user.role === "admin" &&
                ADMIN_NAV.map((item) => {
                  const active = item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href);
                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ${
                        active ? "bg-orange-500 text-white" : "text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                      }`}
                    >
                      <span>{item.icon}</span> {item.label}
                    </Link>
                  );
                })}
            </nav>
            <div className="mt-4 border-t border-slate-100 pt-3">
              <p className="px-2 text-sm font-medium text-slate-700">{user.name}</p>
              <p className="px-2 text-xs text-slate-400">{user.role === "admin" ? "Administrateur" : "Modérateur"}</p>
            </div>
          </div>
        </aside>

        {/* Contenu */}
        <main className="min-w-0 flex-1">{children}</main>

        {/* Nav mobile */}
        <div className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white md:hidden">
          <div className="flex justify-around px-2 py-2">
            {NAV.map((item) => {
              const active = item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${active ? "bg-orange-500 text-white" : "text-slate-600"}`}
                >
                  {item.icon} {item.label}
                </Link>
              );
            })}
            {user.role === "admin" &&
              ADMIN_NAV.map((item) => {
                const active = pathname === item.href;
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${active ? "bg-orange-500 text-white" : "text-slate-600"}`}
                  >
                    {item.icon} {item.label}
                  </Link>
                );
              })}
          </div>
        </div>
      </div>
    );
}
