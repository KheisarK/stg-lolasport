"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { api, type Booking } from "@/lib/api";
import BookingDetails from "@/components/BookingDetails";

export default function StatusPage() {
  const [booking, setBooking] = useState<Booking | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [copied, setCopied] = useState(false);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    const load = () =>
      api<{ booking: Booking }>("/bookings/status", {
        signal: controller.signal,
        headers: { Authorization: `Bearer ${window.location.hash.slice(1)}` },
      })
        .then((data) => {
          if (!controller.signal.aborted) {
            setBooking(data.booking);
            setError("");
            setLoading(false);
          }
        })
        .catch((e) => {
          if (!controller.signal.aborted) {
            setBooking(null);
            setError(e.message);
            setLoading(false);
          }
        });
    void load();
    const timer = setInterval(() => void load(), 30000);
    window.addEventListener("hashchange", load);
    return () => {
      controller.abort();
      clearInterval(timer);
      window.removeEventListener("hashchange", load);
    };
  }, [revision]);
  return (
    <section className="narrow">
      <p className="eyebrow">BOOKING KAMU</p>
      <h1 className="page-title">Rencana main tersimpan.</h1>
      <p className="muted">
        Simpan tautan halaman ini untuk memeriksa status. Tautan bersifat
        rahasia dan memberikan akses ke rincian booking.
      </p>
      {loading && <p role="status">Memuat booking…</p>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {booking && (
        <article className="panel">
          <BookingDetails booking={booking} />
          <div className="actions">
            <button
              className="primary"
              onClick={async () => {
                try {
                  await navigator.clipboard.writeText(window.location.href);
                  setCopied(true);
                } catch {
                  setError(
                    "Salin tautan lengkap melalui kolom alamat browser.",
                  );
                }
              }}
            >
              {copied ? "Tautan tersalin" : "Salin tautan booking"}
            </button>
            <button
              className="secondary"
              onClick={() => {
                setLoading(true);
                setRevision((n) => n + 1);
              }}
            >
              Perbarui status
            </button>
          </div>
        </article>
      )}
      <Link className="back-link" href="/">
        ← Kembali ke reservasi
      </Link>
    </section>
  );
}
