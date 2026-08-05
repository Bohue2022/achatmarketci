import Link from "next/link";
import type { Announcement } from "@/lib/types";

const FUEL_LABELS: Record<string, string> = {
  essence: "Essence",
  diesel: "Diesel",
  hybride: "Hybride",
  electrique: "Électrique",
};

export default function AnnouncementCard({ annonce }: { annonce: Announcement }) {
  const cover = annonce.photos.find((p) => p.is_cover) ?? annonce.photos[0];

  return (
    <Link
      href={`/annonces/${annonce.slug}`}
      className="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:shadow-md"
    >
      <div className="relative aspect-[4/3] overflow-hidden bg-slate-100">
        {cover ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={cover.url}
            alt={annonce.title}
            className="h-full w-full object-cover transition group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-slate-300">
            <span className="text-4xl">🚗</span>
          </div>
        )}
        {annonce.featured && (
          <span className="absolute left-2 top-2 rounded bg-orange-500 px-2 py-0.5 text-xs font-bold text-white">
            Mis en avant
          </span>
        )}
        {annonce.condition === "neuf" && (
          <span className="absolute right-2 top-2 rounded bg-green-600 px-2 py-0.5 text-xs font-bold text-white">
            Neuf
          </span>
        )}
      </div>

      <div className="flex flex-1 flex-col gap-1 p-3">
        <h3 className="line-clamp-1 font-semibold text-slate-900">
          {annonce.brand?.name} {annonce.model?.name} {annonce.year}
        </h3>
        <p className="text-lg font-bold text-orange-600">{annonce.price_formatted}</p>

        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
          {annonce.mileage !== null && <span>{annonce.mileage.toLocaleString("fr-FR")} km</span>}
          {annonce.fuel_type && <span>{FUEL_LABELS[annonce.fuel_type] ?? annonce.fuel_type}</span>}
          <span>{annonce.transmission}</span>
        </div>

        <div className="mt-auto flex items-center justify-between pt-2 text-xs">
          <span className="text-slate-500">
            {annonce.city?.name}
            {annonce.commune ? ` · ${annonce.commune.name}` : ""}
          </span>
          <div className="flex items-center gap-2">
            {annonce.is_dedouane && (
              <span className="rounded bg-green-50 px-1.5 py-0.5 font-medium text-green-700">
                Dédouané
              </span>
            )}
            {annonce.seller?.is_verified_pro && (
              <span className="rounded bg-blue-50 px-1.5 py-0.5 font-medium text-blue-700">Pro</span>
            )}
          </div>
        </div>
      </div>
    </Link>
  );
}