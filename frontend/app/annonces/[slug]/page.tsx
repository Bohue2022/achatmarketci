import { notFound } from "next/navigation";
import type { Metadata } from "next";
import Link from "next/link";
import { api } from "@/lib/api";
import ContacterVendeur from "@/components/ContacterVendeur";
import type { Announcement } from "@/lib/types";

const FUEL_LABELS: Record<string, string> = {
  essence: "Essence",
  diesel: "Diesel",
  hybride: "Hybride",
  electrique: "Électrique",
};

interface PageProps {
  params: Promise<{ slug: string }>;
}

async function fetchAnnouncement(slug: string): Promise<Announcement | null> {
  try {
    const res = await api<{ data: Announcement }>(`/announcements/${slug}`, { auth: false });
    return res.data;
  } catch {
    return null;
  }
}

export async function generateMetadata(props: PageProps): Promise<Metadata> {
  const { slug } = await props.params;
  const annonce = await fetchAnnouncement(slug);
  if (!annonce) return { title: "Annonce introuvable" };
  return {
    title: `${annonce.full_title} — ${annonce.price_formatted}`,
    description: annonce.description.slice(0, 160),
  };
}

export default async function AnnouncementPage(props: PageProps) {
  const { slug } = await props.params;
  const annonce = await fetchAnnouncement(slug);
  if (!annonce) notFound();

  const gallery = annonce.photos;
  const cover = gallery.find((p) => p.is_cover) ?? gallery[0];

  const specs: { label: string; value: string }[] = [
    { label: "Année", value: annonce.year ? String(annonce.year) : "—" },
    { label: "Kilométrage", value: annonce.mileage !== null ? `${annonce.mileage.toLocaleString("fr-FR")} km` : "—" },
    { label: "Carburant", value: FUEL_LABELS[annonce.fuel_type ?? ""] ?? annonce.fuel_type ?? "—" },
    { label: "Boîte", value: annonce.transmission ?? "—" },
    { label: "État", value: annonce.condition === "neuf" ? "Neuf" : "Occasion" },
    { label: "Dédouané", value: annonce.is_dedouane ? "Oui" : "Non" },
    { label: "Carte grise", value: annonce.has_grise ? "Disponible" : "Non disponible" },
    { label: "Origine", value: annonce.origin ? annonce.origin.replace(/_/g, " ") : "—" },
  ];

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Galerie */}
        <div>
          <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-2xl bg-slate-200">
            {cover ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={cover.url} alt={annonce.title} className="h-full w-full object-cover" />
            ) : (
              <span className="text-7xl text-slate-300">🚗</span>
            )}
          </div>
          {gallery.length > 1 && (
            <div className="mt-3 flex gap-2">
              {gallery.map((p) => (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  key={p.id}
                  src={p.url}
                  alt=""
                  className="h-20 w-28 rounded-lg object-cover ring-1 ring-slate-200"
                />
              ))}
            </div>
          )}
        </div>

        {/* Infos */}
        <div>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h1 className="text-2xl font-bold text-slate-900">{annonce.full_title}</h1>
              <p className="mt-1 text-slate-500">
                {annonce.city?.name}
                {annonce.commune ? ` · ${annonce.commune.name}` : ""}
              </p>
            </div>
            {annonce.seller?.is_verified_pro && (
              <span className="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Pro vérifié</span>
            )}
          </div>

          <p className="mt-4 text-3xl font-bold text-orange-600">{annonce.price_formatted}</p>

          <div className="mt-5 grid grid-cols-2 gap-3">
            {specs.map((s) => (
              <div key={s.label} className="rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-100">
                <dt className="text-xs text-slate-500">{s.label}</dt>
                <dd className="mt-0.5 font-semibold capitalize text-slate-800">{s.value}</dd>
              </div>
            ))}
          </div>

          {/* Contact vendeur */}
          <div className="mt-6 rounded-xl border border-slate-200 bg-white p-4">
            <p className="font-semibold text-slate-900">
              <Link href={`/profil/${annonce.seller?.id}`} className="hover:text-orange-600">
                {annonce.seller?.company_name ?? annonce.seller?.name}
              </Link>
            </p>
            <div className="mt-3 flex flex-col gap-2 sm:flex-row">
              {annonce.seller?.phone && (
                <a
                  href={`tel:${annonce.seller.phone}`}
                  className="flex-1 rounded-lg bg-green-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-green-700"
                >
                  📞 {annonce.seller.phone}
                </a>
              )}
              {annonce.seller?.whatsapp && (
                <a
                  href={`https://wa.me/${annonce.seller.whatsapp.replace(/[^0-9]/g, "")}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex-1 rounded-lg bg-emerald-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-emerald-600"
                >
                  WhatsApp
                </a>
              )}
            </div>
            <ContacterVendeur slug={annonce.slug} sellerId={annonce.seller?.id} />
          </div>
        </div>
      </div>

      {/* Description */}
      <div className="mt-8 rounded-2xl bg-white p-6 shadow-sm">
        <h2 className="text-lg font-bold text-slate-900">Description</h2>
        <p className="mt-2 whitespace-pre-line leading-relaxed text-slate-600">{annonce.description}</p>
      </div>
    </div>
  );
}