"use client";
import { useState, type FormEvent } from "react";
import {
  api,
  ApiError,
  money,
  schedule,
  type Booking,
  type PaymentChoice,
} from "@/lib/api";

type Request = {
  kind: PaymentChoice["kind"];
  expected_amount: number;
  method: string;
  idempotency_key: string;
};
const labels = {
  dp: "Pembayaran awal DP",
  full: "Pembayaran penuh",
  settlement: "Pelunasan sisa tagihan",
};
const methods: Record<string, string> = {
  cash: "Tunai",
  transfer: "Transfer",
  qris: "QRIS",
};

function restore(key: string): Request | null {
  if (typeof window === "undefined") return null;
  try {
    const value = JSON.parse(sessionStorage.getItem(key) || "null");
    return value &&
      Object.hasOwn(labels, value.kind) &&
      Object.hasOwn(methods, value.method) &&
      Number.isInteger(value.expected_amount) &&
      value.expected_amount > 0 &&
      typeof value.idempotency_key === "string"
      ? value
      : null;
  } catch {
    return null;
  }
}

export default function PaymentForm({
  booking,
  onSaved,
  onRefresh,
}: {
  booking: Booking;
  onSaved: (booking: Booking) => void;
  onRefresh: () => void;
}) {
  const storageKey = `lola-payment-v2-${booking.id}`;
  const [pending, setPending] = useState<Request | null>(() =>
    restore(storageKey),
  );
  const [kind, setKind] = useState<PaymentChoice["kind"]>(
    pending?.kind ?? booking.payment_choices[0]?.kind ?? "full",
  );
  const [method, setMethod] = useState(pending?.method ?? "cash");
  const [reviewing, setReviewing] = useState(!!pending);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const choice = booking.payment_choices.find((c) => c.kind === kind);
  const amount = pending?.expected_amount ?? choice?.amount ?? 0;

  function review(e: FormEvent) {
    e.preventDefault();
    setReviewing(true);
    setError("");
  }
  async function save() {
    setLoading(true);
    setError("");
    try {
      const request = pending ?? {
        kind,
        expected_amount: amount,
        method,
        idempotency_key: crypto.randomUUID(),
      };
      sessionStorage.setItem(storageKey, JSON.stringify(request));
      setPending(request);
      const data = await api<{ booking: Booking }>(
        `/admin/bookings/${booking.id}/payments`,
        { method: "POST", body: JSON.stringify(request) },
      );
      sessionStorage.removeItem(storageKey);
      setPending(null);
      setReviewing(false);
      setKind(data.booking.payment_choices[0]?.kind ?? "full");
      onSaved(data.booking);
    } catch (e) {
      setError((e as Error).message);
      if (e instanceof ApiError && e.status >= 400 && e.status < 500) {
        sessionStorage.removeItem(storageKey);
        setPending(null);
        setReviewing(false);
        onRefresh();
      }
    } finally {
      setLoading(false);
    }
  }
  return (
    <form onSubmit={review} className="payment-form">
      <p className="eyebrow">PENCATATAN PEGAWAI</p>
      <h3>Catat pembayaran demo</h3>
      {!reviewing ? (
        <>
          <label>
            Jenis pencatatan
            <select
              value={kind}
              onChange={(e) => setKind(e.target.value as PaymentChoice["kind"])}
            >
              {booking.payment_choices.map((c) => (
                <option key={c.kind} value={c.kind}>
                  {c.label} · {money(c.amount)}
                </option>
              ))}
            </select>
          </label>
          <label>
            Metode
            <select value={method} onChange={(e) => setMethod(e.target.value)}>
              {Object.entries(methods).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <div className="payment-amount">
            <span>Nominal dari server</span>
            <output data-testid="payment-amount">{money(amount)}</output>
          </div>
          <button className="primary full" disabled={!choice}>
            Tinjau pembayaran
          </button>
        </>
      ) : (
        <section className="payment-review" aria-label="Konfirmasi pembayaran">
          <h3>Periksa sebelum mencatat</h3>
          <p>
            <strong>{booking.reference}</strong>
            <br />
            {booking.customer_name} · {booking.court_name}
            <br />
            {schedule(booking.starts_at, booking.ends_at)}
          </p>
          <dl>
            <div>
              <dt>Jenis</dt>
              <dd>{labels[pending?.kind ?? kind]}</dd>
            </div>
            <div>
              <dt>Nominal</dt>
              <dd data-testid="payment-amount">{money(amount)}</dd>
            </div>
            <div>
              <dt>Metode</dt>
              <dd>{methods[pending?.method ?? method]}</dd>
            </div>
          </dl>
          {pending && (
            <p className="notice">
              Permintaan sebelumnya mungkin sudah tercatat. Coba ulang dengan
              kunci dan isi yang sama; periksa juga riwayat booking.
            </p>
          )}
          <button
            type="button"
            className="primary full"
            disabled={loading}
            onClick={save}
          >
            {loading
              ? "Mencatat…"
              : pending
                ? "Coba ulang pencatatan"
                : "Konfirmasi dan catat"}
          </button>
          {!pending && (
            <button
              type="button"
              className="secondary full spaced"
              onClick={() => setReviewing(false)}
            >
              Kembali ke pilihan
            </button>
          )}
        </section>
      )}
      {error && (
        <p role="alert" className="error spaced">
          {error}
        </p>
      )}
      <p className="field-help spaced">
        Nominal berbeda belum didukung. Tunda pencatatan di demo dan cocokkan
        bukti bersama pengelola; jangan mengisi nominal rekaan.
      </p>
      <p className="field-help">
        Pencegahan request ulang belum mengenali dua input baru yang berasal
        dari transfer nyata yang sama. Tahap 1 hanya pencatatan manual demo.
      </p>
    </form>
  );
}
