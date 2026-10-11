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
          <p className="eyebrow">RUANG UNTUK BERMAIN</p>
          <h1>
            Atur jadwal.
            <br />
            <em>Mulai permainan.</em>
          </h1>
          <p>
            Pilih lapangan favorit, temukan waktu yang pas, dan simpan tempat
            untuk tim kamu.
          </p>
          <div className="hero-stats">
            <span>
              <strong>02</strong> Lapangan futsal
            </span>
            <span>
              <strong>02</strong> Lapangan badminton
            </span>
            <span>
              <strong>WIB</strong> Semua jadwal
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
          <span className="art-caption">YOUR NEXT GAME STARTS HERE ↗</span>
        </div>
      </section>
      <section className="section-heading">
        <div>
          <p className="eyebrow">RESERVASI LAPANGAN</p>
          <h2>Siapkan permainan berikutnya</h2>
        </div>
        <span className="pill">Tanpa akun pelanggan</span>
      </section>
      <BookingForm initialNow={initialNow} />
    </>
  );
}
