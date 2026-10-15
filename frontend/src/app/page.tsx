import { connection } from "next/server";
import BookingForm from "@/components/BookingForm";
export const dynamic = "force-dynamic";
export default async function Home() {
  await connection();
  // Request-time server clock is serialized to the client to prevent hydration mismatch.
  // eslint-disable-next-line react-hooks/purity -- This Server Component renders after connection(), once per request.
  const initialNow = Date.now();
  return (
    <>
      <section className="hero">
        <div>
          <p className="eyebrow">LOLA SPORT · FUTSAL & BADMINTON</p>
          <h1>
            Jadwalkan main.
            <br />
            <em>Siapkan tim.</em>
          </h1>
          <p>
            Futsal atau badminton. Pilih jadwal, lalu lunasi sebelum bermain.
          </p>
          <div className="hero-stats">
            <span>
              <strong>02</strong> Lapangan futsal
            </span>
            <span>
              <strong>02</strong> Lapangan badminton
            </span>
            <span>
              <strong>07–00</strong> Setiap hari · WIB
            </span>
          </div>
        </div>
        <div className="court-art" aria-hidden="true">
          <div className="court-lines">
            <span className="center-line" />
            <span className="center-circle" />
            <span className="goal left" />
            <span className="goal right" />
          </div>
          <span className="art-caption">PILIH JADWAL. BAYAR. MAIN.</span>
        </div>
      </section>
      <section className="section-heading">
        <div>
          <p className="eyebrow">RESERVASI LAPANGAN</p>
          <h2>Pilih jadwal tim kamu</h2>
        </div>
        <span className="pill">Tanpa akun pelanggan</span>
      </section>
      <BookingForm initialNow={initialNow} />
    </>
  );
}
