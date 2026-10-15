"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import {
  api,
  money,
  jakartaDate,
  time,
  type Court,
  type Rules,
  type Booking,
} from "@/lib/api";

export default function BookingForm({
  admin = false,
  onCreated,
  initialNow,
}: {
  admin?: boolean;
  initialNow?: number;
  onCreated?: (booking: Booking, token: string) => void;
}) {
  const router = useRouter();
  const [now, setNow] = useState(() => initialNow ?? Date.now());
  const clockOffset = useRef(0);
  const [courts, setCourts] = useState<Court[]>([]);
  const [rules, setRules] = useState<Rules | null>(null);
  const [courtId, setCourtId] = useState(1);
  const [date, setDate] = useState(() => jakartaDate(1, now));
  const [start, setStart] = useState("10:00");
  const [duration, setDuration] = useState(60);
  const [option, setOption] = useState("dp");
  const [busy, setBusy] = useState<{ starts_at: string; ends_at: string }[]>(
    [],
  );
  const [slotsLoading, setSlotsLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [slotsError, setSlotsError] = useState("");
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    let alive = true;
    api<{ courts: Court[]; rules: Rules; server_now: string }>("/courts")
      .then((data) => {
        if (alive) {
          setCourts(data.courts);
          setRules(data.rules);
          clockOffset.current = Date.parse(data.server_now) - Date.now();
          setNow(Date.parse(data.server_now));
        }
      })
      .catch((e) => {
        if (alive) setError(e.message);
      });
    return () => {
      alive = false;
    };
  }, []);
  useEffect(() => {
    const timer = setInterval(
      () => setNow(Date.now() + clockOffset.current),
      1000,
    );
    return () => clearInterval(timer);
  }, []);
  useEffect(() => {
    const controller = new AbortController();
    api<{ busy: typeof busy }>(
      `/availability?date=${date}&court_id=${courtId}`,
      { signal: controller.signal },
    )
      .then((data) => {
        if (!controller.signal.aborted) {
          setBusy(data.busy);
          setSlotsError("");
          setSlotsLoading(false);
        }
      })
      .catch((e) => {
        if (!controller.signal.aborted) {
          setSlotsError(e.message);
          setSlotsLoading(false);
        }
      });
    return () => controller.abort();
  }, [date, courtId, revision]);
  const court = courts.find((c) => c.id === courtId);
  const total = court ? (court.hourly_rate * duration) / 60 : 0;
  const hours = Array.from(
    { length: rules ? rules.close_hour - rules.open_hour : 17 },
    (_, i) => i + (rules?.open_hour ?? 7),
  ).filter((h) => h + duration / 60 <= (rules?.close_hour ?? 24));
  const validStart = hours.some(
    (h) => `${String(h).padStart(2, "0")}:00` === start,
  )
    ? start
    : "";
  const from = Date.parse(`${date}T${validStart}:00+07:00`);
  const to = from + duration * 60000;
  const urgent =
    Number.isFinite(from) &&
    from - now < (rules?.full_payment_within_minutes ?? 60) * 60000;
  const canDp =
    Number.isFinite(from) && !urgent && total > (rules?.dp_amount ?? 50000);
  const paymentOption = option === "dp" && !canDp ? "full" : option;
  const unavailable =
    !validStart ||
    from <= now ||
    busy.some(
      (b) => from < Date.parse(b.ends_at) && to > Date.parse(b.starts_at),
    );
  const due = paymentOption === "full" ? total : (rules?.dp_amount ?? 50000);
  function changeSchedule() {
    setSlotsLoading(true);
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError("");
    const form = new FormData(event.currentTarget);
    try {
      const data = await api<{ booking: Booking; access_token: string }>(
        admin ? "/admin/bookings" : "/bookings",
        {
          method: "POST",
          body: JSON.stringify({
            court_id: courtId,
            date,
            start_time: validStart,
            duration,
            payment_option: paymentOption,
            customer_name: form.get("customer_name"),
            contact: form.get("contact"),
            ...(admin ? { channel: form.get("channel") } : {}),
          }),
        },
      );
      if (admin) {
        onCreated?.(data.booking, data.access_token);
        setRevision((value) => value + 1);
      } else router.push(`/booking#${data.access_token}`);
    } catch (e) {
      setError((e as Error).message);
      setRevision((value) => value + 1);
    } finally {
      setSubmitting(false);
    }
  }
  return (
    <form onSubmit={submit} className="booking-layout" id="pesan">
      <div className="panel">
        <h3>
          <span className="step">1</span> Pilih lapangan & waktu
        </h3>
        {!rules && <p role="status">Memuat pilihan lapangan…</p>}
        <div className="court-grid" role="group" aria-label="Pilih lapangan">
          {courts.map((c) => (
            <button
              type="button"
              key={c.id}
              aria-pressed={courtId === c.id}
              className={`court-option ${courtId === c.id ? "selected" : ""}`}
              onClick={() => {
                if (courtId !== c.id) {
                  setCourtId(c.id);
                  changeSchedule();
                }
              }}
            >
              <span className="sport-label">
                {c.sport === "futsal" ? "FUTSAL" : "BADMINTON"}{" "}
                <span aria-hidden>{courtId === c.id ? "●" : "○"}</span>
              </span>
              <strong>{c.name}</strong>
              <small>{money(c.hourly_rate)} / jam</small>
            </button>
          ))}
        </div>
        <div className="quick-dates" aria-label="Pilih tanggal cepat">
          {[
            [0, "Hari ini"],
            [1, "Besok"],
          ].map(([offset, label]) => (
            <button
              type="button"
              key={offset}
              aria-pressed={date === jakartaDate(Number(offset), now)}
              onClick={() => {
                const next = jakartaDate(Number(offset), now);
                if (date !== next) {
                  setDate(next);
                  changeSchedule();
                }
              }}
            >
              {label}
            </button>
          ))}
        </div>
        <div className="form-row">
          <label>
            Tanggal bermain
            <input
              name="date"
              type="date"
              required
              min={jakartaDate(0, now)}
              max={jakartaDate(rules?.advance_days ?? 90, now)}
              value={date}
              onChange={(e) => {
                if (date !== e.target.value) {
                  setDate(e.target.value);
                  changeSchedule();
                }
              }}
            />
          </label>
          <label>
            Durasi
            <select
              value={duration}
              onChange={(e) => setDuration(Number(e.target.value))}
            >
              {(rules?.durations ?? [60, 120, 180]).map((d) => (
                <option value={d} key={d}>
                  {d / 60} jam
                </option>
              ))}
            </select>
          </label>
        </div>
        <label>
          Jam mulai (WIB)
          <select
            required
            value={validStart}
            onChange={(e) => setStart(e.target.value)}
          >
            <option value="" disabled>
              Pilih jam mulai
            </option>
            {hours.map((h) => {
              const value = `${h.toString().padStart(2, "0")}:00`;
              const from = new Date(`${date}T${value}:00+07:00`).getTime();
              const to = from + duration * 60000;
              const unavailable =
                from <= now ||
                busy.some(
                  (b) =>
                    from < Date.parse(b.ends_at) &&
                    to > Date.parse(b.starts_at),
                );
              return (
                <option key={h} value={value} disabled={unavailable}>
                  {value}
                  {h + duration / 60 === 24 ? " · selesai 00:00 (+1 hari)" : ""}
                  {unavailable ? " · Tidak tersedia" : ""}
                </option>
              );
            })}
          </select>
        </label>
        <p className="field-help">
          Senin–Minggu, 07.00–00.00 WIB. Jam tutup 00.00 berada pada tanggal
          berikutnya.
        </p>
        <div className="availability" aria-live="polite">
          {slotsLoading ? (
            "Memeriksa jadwal…"
          ) : slotsError ? (
            <span className="error-text">{slotsError}</span>
          ) : busy.length ? (
            <>
              Terisi:{" "}
              {busy
                .map((b) => `${time(b.starts_at)}–${time(b.ends_at)}`)
                .join(", ")}{" "}
              WIB.
            </>
          ) : (
            "● Belum ada booking aktif pada tanggal ini."
          )}
        </div>
        <h3 className="spaced">
          <span className="step">2</span> Data pemesan
        </h3>
        <p className="muted">
          Gunakan data fiktif untuk mencoba prototipe ini.
        </p>
        <div className="form-row customer-fields">
          <label>
            Nama pemesan
            <input
              name="customer_name"
              autoComplete="name"
              required
              maxLength={100}
              placeholder="Nama untuk reservasi"
            />
          </label>
          <label>
            Nomor kontak
            <input
              name="contact"
              type="tel"
              autoComplete="tel"
              required
              minLength={8}
              maxLength={30}
              placeholder="Nomor telepon pemesan"
            />
          </label>
        </div>
        {admin && (
          <label>
            Asal booking
            <select name="channel">
              <option value="whatsapp">WhatsApp</option>
              <option value="onsite">Datang langsung</option>
            </select>
          </label>
        )}
      </div>
      <aside className="panel summary">
        <p className="eyebrow">RINGKASAN BOOKING</p>
        <h3>{court?.name ?? "Pilih lapangan"}</h3>
        <p>
          {date} · {validStart || "Pilih jam"} WIB · {duration / 60} jam
        </p>
        <div className="total-line">
          <span>Perkiraan tagihan</span>
          <strong>{money(total)}</strong>
        </div>
        <h3>
          <span className="step">3</span> Pilihan pembayaran
        </h3>
        <label className="radio-card">
          <input
            type="radio"
            name="payment_option"
            value="dp"
            checked={paymentOption === "dp"}
            disabled={!canDp}
            onChange={() => setOption("dp")}
          />
          <span>
            <strong>DP {money(rules?.dp_amount ?? 50000)}</strong>
            <small>Per booking, bukan per jam</small>
          </span>
        </label>
        <label className="radio-card">
          <input
            type="radio"
            name="payment_option"
            value="full"
            checked={paymentOption === "full"}
            onChange={() => setOption("full")}
          />
          <span>
            <strong>Bayar penuh</strong>
            <small>Seluruh tagihan sekaligus</small>
          </span>
        </label>
        {urgent && (
          <p className="notice" role="status">
            Kurang dari 60 menit sebelum mulai: wajib bayar penuh. Berlaku untuk
            booking web, WA, dan onsite.
          </p>
        )}
        <dl>
          <div>
            <dt>Pembayaran awal</dt>
            <dd>{money(due)}</dd>
          </div>
          <div>
            <dt>Sisa setelah pembayaran awal</dt>
            <dd>{money(total - due)}</dd>
          </div>
        </dl>
        <p className="notice">
          Setelah booking tersimpan, slot ditahan paling lama{" "}
          {rules?.hold_minutes ?? 20} menit, maksimal hingga jam mulai. Memilih
          DP/penuh belum berarti pembayaran diterima.
        </p>
        <p className="pay-before-play">
          Lunas sebelum bermain. Pelunasan dicatat pegawai pada booking yang
          sama.
        </p>
        {error && (
          <p role="alert" className="error">
            {error}
          </p>
        )}
        <button
          className="primary full"
          disabled={
            !rules || submitting || slotsLoading || !!slotsError || unavailable
          }
        >
          {submitting
            ? "Menyimpan booking…"
            : admin
              ? "Simpan booking pegawai →"
              : "Buat booking demo →"}
        </button>
        <small className="muted">
          Harga akhir dihitung server. Ketentuan demo perlu ditinjau pengelola.
        </small>
      </aside>
    </form>
  );
}
