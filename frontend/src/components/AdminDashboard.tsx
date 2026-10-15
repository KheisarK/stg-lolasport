"use client";
import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import {
  api,
  ApiError,
  jakartaDate,
  dateTime,
  money,
  statusLabels,
  type Booking,
  type Court,
  type Payment,
} from "@/lib/api";
import BookingForm from "./BookingForm";
import BookingDetails from "./BookingDetails";
import PaymentForm from "./PaymentForm";

type Report = {
  total: number;
  count: number;
  payments: Payment[];
  from: string;
  until_exclusive: string;
};
export default function AdminDashboard({ name }: { name: string }) {
  const router = useRouter();
  const [tab, setTab] = useState("schedule");
  const [date, setDate] = useState(() => jakartaDate(1));
  const [court, setCourt] = useState("");
  const [courts, setCourts] = useState<Court[]>([]);
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [selected, setSelected] = useState<Booking | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [revision, setRevision] = useState(0);
  const [customerLink, setCustomerLink] = useState("");
  const [report, setReport] = useState<Report | null>(null);
  const [reportDate, setReportDate] = useState(() => jakartaDate());
  const [period, setPeriod] = useState("day");
  const [cancelBusy, setCancelBusy] = useState(false);
  const [startBusy, setStartBusy] = useState(false);
  const details = useRef<HTMLElement>(null);
  const handleError = useCallback(
    (e: unknown) => {
      if (e instanceof ApiError && e.status === 401) {
        router.push("/admin/login");
        router.refresh();
      } else setError((e as Error).message);
    },
    [router],
  );
  useEffect(() => {
    api<{ courts: Court[] }>("/courts")
      .then((data) => setCourts(data.courts))
      .catch(handleError);
  }, [handleError]);
  useEffect(() => {
    let alive = true;
    const endpoint =
      tab === "report"
        ? `/admin/reports?period=${period}&date=${reportDate}`
        : `/admin/bookings?date=${date}${court ? `&court_id=${court}` : ""}`;
    api<{ bookings: Booking[] } & Report>(endpoint)
      .then((data) => {
        if (alive) {
          if (tab === "report") setReport(data);
          else {
            setBookings(data.bookings);
            setSelected((current) =>
              current
                ? (data.bookings.find((b) => b.id === current.id) ?? null)
                : null,
            );
          }
          setLoading(false);
          setError("");
        }
      })
      .catch((e) => {
        if (alive) {
          handleError(e);
          setLoading(false);
        }
      });
    return () => {
      alive = false;
    };
  }, [date, court, revision, tab, reportDate, period, handleError]);
  function refresh() {
    setLoading(true);
    setRevision((n) => n + 1);
  }
  return (
    <>
      <section className="admin-heading">
        <div>
          <p className="eyebrow">AREA PEGAWAI · {name}</p>
          <h1 className="page-title">Ruang operasional</h1>
        </div>
        <button
          className="secondary"
          onClick={async () => {
            try {
              await api("/admin/logout", { method: "POST" });
              router.push("/admin/login");
              router.refresh();
            } catch (e) {
              handleError(e);
            }
          }}
        >
          Keluar
        </button>
      </section>
      <div className="tabs" aria-label="Menu pegawai">
        {[
          ["schedule", "Jadwal booking"],
          ["create", "Booking WA / onsite"],
          ["report", "Rekap penerimaan"],
        ].map(([value, label]) => (
          <button
            key={value}
            className={tab === value ? "active" : ""}
            aria-pressed={tab === value}
            onClick={() => {
              if (tab !== value) {
                setTab(value);
                setSelected(null);
                setLoading(true);
              }
            }}
          >
            {label}
          </button>
        ))}
      </div>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && (
        <p role="status" className="success">
          {notice}
        </p>
      )}
      {customerLink && (
        <p className="notice">
          Tautan rahasia pemesan:{" "}
          <a href={customerLink}>Buka status booking baru ↗</a>. Simpan sebelum
          meninggalkan halaman.
        </p>
      )}
      {tab === "create" && (
        <BookingForm
          admin
          onCreated={(booking, token) => {
            setNotice(`Booking ${booking.reference} berhasil dibuat.`);
            setCustomerLink(`/booking#${token}`);
            setDate(
              new Intl.DateTimeFormat("en-CA", {
                timeZone: "Asia/Jakarta",
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
              }).format(new Date(booking.starts_at)),
            );
            setCourt("");
            setTab("schedule");
            setSelected(booking);
            refresh();
          }}
        />
      )}
      {tab === "schedule" && (
        <>
          <div className="filters">
            <label>
              Tanggal jadwal
              <input
                type="date"
                value={date}
                onChange={(e) => {
                  if (date !== e.target.value) {
                    setDate(e.target.value);
                    setSelected(null);
                    setLoading(true);
                  }
                }}
              />
            </label>
            <label>
              Filter lapangan
              <select
                value={court}
                onChange={(e) => {
                  if (court !== e.target.value) {
                    setCourt(e.target.value);
                    setSelected(null);
                    setLoading(true);
                  }
                }}
              >
                <option value="">Semua lapangan</option>
                {courts.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </label>
            <button className="secondary" onClick={refresh}>
              Muat ulang jadwal
            </button>
          </div>
          <div className="admin-columns">
            <section>
              {loading ? (
                <p role="status">Memuat jadwal…</p>
              ) : !bookings.length ? (
                <p className="panel empty">
                  Belum ada booking pada pilihan ini.
                </p>
              ) : (
                bookings.map((b) => (
                  <button
                    className={`booking-row ${selected?.id === b.id ? "selected" : ""}`}
                    key={b.id}
                    onClick={() => {
                      setSelected(b);
                      requestAnimationFrame(() => {
                        if (window.matchMedia("(max-width: 760px)").matches) {
                          details.current?.scrollIntoView({
                            behavior: window.matchMedia(
                              "(prefers-reduced-motion: reduce)",
                            ).matches
                              ? "auto"
                              : "smooth",
                            block: "start",
                          });
                          details.current?.focus({ preventScroll: true });
                        }
                      });
                    }}
                  >
                    <span className="row-top">
                      <strong>{b.customer_name}</strong>
                      <span className={`badge ${b.status}`}>
                        {statusLabels[b.status]}
                      </span>
                    </span>
                    <span>
                      {b.court_name} · {dateTime(b.starts_at)} WIB
                    </span>
                    <span className="muted">
                      {b.reference} · {b.channel} ·{" "}
                      {statusLabels[b.payment_status]}
                    </span>
                  </button>
                ))
              )}
            </section>
            {selected ? (
              <article
                className="panel booking-operations"
                ref={details}
                tabIndex={-1}
                aria-label="Rincian booking"
              >
                <BookingDetails booking={selected} />
                {["pending", "confirmed"].includes(selected.status) &&
                  selected.remaining > 0 && (
                    <PaymentForm
                      key={`${selected.id}-${selected.paid}`}
                      booking={selected}
                      onRefresh={refresh}
                      onSaved={(booking) => {
                        setSelected(booking);
                        setError("");
                        setNotice(
                          `Pembayaran tercatat. Sisa ${money(booking.remaining)}.`,
                        );
                        refresh();
                      }}
                    />
                  )}
                {selected.status === "confirmed" && !selected.started_at && (
                  <section className="start-play">
                    <h3>Mulai bermain</h3>
                    <p>
                      Pastikan tagihan lunas dan pelanggan mulai sesuai jadwal.
                      Tindakan ini mencatat prosedur pegawai; tidak membuka
                      akses fisik lapangan.
                    </p>
                    <button
                      className="primary full"
                      disabled={startBusy}
                      onClick={async () => {
                        setStartBusy(true);
                        setError("");
                        try {
                          const data = await api<{ booking: Booking }>(
                            `/admin/bookings/${selected.id}/start`,
                            { method: "POST" },
                          );
                          setSelected(data.booking);
                          setNotice(
                            "Mulai bermain tercatat. Waktu dan pegawai tersimpan.",
                          );
                          refresh();
                        } catch (e) {
                          handleError(e);
                        } finally {
                          setStartBusy(false);
                        }
                      }}
                    >
                      {startBusy ? "Mencatat mulai…" : "Tandai mulai bermain"}
                    </button>
                  </section>
                )}
                {selected.status === "pending" && selected.paid === 0 && (
                  <button
                    className="danger spaced"
                    disabled={cancelBusy}
                    onClick={async () => {
                      if (
                        !window.confirm(
                          "Batalkan booking belum dibayar ini dan lepaskan slot?",
                        )
                      )
                        return;
                      setCancelBusy(true);
                      try {
                        const data = await api<{ booking: Booking }>(
                          `/admin/bookings/${selected.id}/cancel`,
                          { method: "POST" },
                        );
                        setSelected(data.booking);
                        setNotice(
                          "Booking dibatalkan; riwayat tetap tersimpan.",
                        );
                        refresh();
                      } catch (e) {
                        handleError(e);
                      } finally {
                        setCancelBusy(false);
                      }
                    }}
                  >
                    Batalkan booking
                  </button>
                )}
              </article>
            ) : (
              <aside className="panel empty">
                Pilih booking untuk melihat rincian dan mencatat pembayaran.
              </aside>
            )}
          </div>
        </>
      )}
      {tab === "report" && (
        <section>
          <div className="filters">
            <label>
              Periode
              <select
                value={period}
                onChange={(e) => {
                  setPeriod(e.target.value);
                  setLoading(true);
                }}
              >
                <option value="day">Harian</option>
                <option value="month">Bulanan</option>
              </select>
            </label>
            <label>
              Tanggal acuan
              <input
                type="date"
                value={reportDate}
                onChange={(e) => {
                  setReportDate(e.target.value);
                  setLoading(true);
                }}
              />
            </label>
            <button className="secondary" onClick={refresh}>
              Perbarui rekap
            </button>
          </div>
          <p className="notice">
            Berdasarkan waktu pencatatan pembayaran dalam WIB. Tagihan booking
            tidak ditambahkan sebagai penerimaan. Seluruh nominal adalah data
            demo.
          </p>
          {loading ? (
            <p role="status">Menghitung rekap…</p>
          ) : (
            report && (
              <div className="panel">
                <p className="eyebrow">TOTAL PENERIMAAN DEMO</p>
                <h2 className="report-total">{money(report.total)}</h2>
                <p>
                  {report.count} catatan pembayaran · mulai{" "}
                  {dateTime(report.from)} WIB hingga sebelum{" "}
                  {dateTime(report.until_exclusive)} WIB
                </p>
                {report.payments.length ? (
                  <ul className="payment-list">
                    {report.payments.map((p) => (
                      <li key={p.id}>
                        <div>
                          <strong>{p.reference}</strong>
                          <small>
                            {dateTime(p.paid_at)} WIB · {p.method} · Pegawai #
                            {p.recorded_by}
                          </small>
                        </div>
                        <strong>{money(p.amount)}</strong>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="empty">
                    Belum ada penerimaan pada periode ini.
                  </p>
                )}
              </div>
            )
          )}
        </section>
      )}
    </>
  );
}
