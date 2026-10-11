import type { Metadata } from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata = {
  title: "Lola Sport · Reservasi Lapangan",
  description: "Prototipe reservasi futsal dan badminton Lola Sport",
  robots: { index: false, follow: false },
};
export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="id">
      <body>
        <div className="demo">Mode demo—tidak memproses uang nyata</div>
        <header>
          <Link href="/" className="brand">
            <span className="brand-icon">
              L<span>↗</span>
            </span>{" "}
            LOLA<span className="brand-light">SPORT</span>
          </Link>
          <nav>
            <Link href="/">Reservasi</Link>
            <Link href="/admin">
              Area pegawai <span aria-hidden>↗</span>
            </Link>
          </nav>
        </header>
        <main>{children}</main>
        <footer>
          <strong>LOLA SPORT</strong>
          <span>Futsal & badminton · Waktu Indonesia Barat</span>
          <small>
            Prototipe kelompok · Aturan dan tarif contoh perlu ditinjau
            pengelola.
          </small>
        </footer>
      </body>
    </html>
  );
}
