import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import Navbar from "@/components/Navbar";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: {
    default: "AutoMarket CI — Achat & vente de véhicules en Côte d'Ivoire",
    template: "%s | AutoMarket CI",
  },
  description:
    "Marketplace de vente de véhicules neufs et d'occasion en Côte d'Ivoire. Trouvez votre voiture à Abidjan, Bouaké ou San-Pédro.",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html
      lang="fr"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col bg-slate-50">
        <Navbar />
        <main className="flex-1">{children}</main>
        <footer className="border-t border-slate-200 bg-white py-6 text-center text-sm text-slate-500">
          © {new Date().getFullYear()} AutoMarket CI — Achetez et vendez des véhicules en toute confiance.
        </footer>
      </body>
    </html>
  );
}