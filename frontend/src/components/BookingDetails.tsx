import {
  money,
  dateTime,
  schedule,
  statusLabels,
  type Booking,
} from "@/lib/api";
import HoldNotice from "./HoldNotice";

export default function BookingDetails({ booking }: { booking: Booking }) {
  return (
    <>
      <div className="booking-title">
        <div>
          <p className="eyebrow">{booking.reference}</p>
          <h2>{booking.court_name}</h2>
        </div>
        <span className={`badge ${booking.status}`}>
          {statusLabels[booking.status]}
        </span>
      </div>
      <p>{schedule(booking.starts_at, booking.ends_at)}</p>
      <dl className="detail-list">
        <div>
          <dt>Pemesan</dt>
          <dd>{booking.customer_name}</dd>
        </div>
        <div>
          <dt>Kontak</dt>
          <dd>{booking.contact}</dd>
        </div>
        <div>
          <dt>Total tagihan</dt>
          <dd>{money(booking.total)}</dd>
        </div>
        <div>
          <dt>Pembayaran diterima</dt>
          <dd>{money(booking.paid)}</dd>
        </div>
        <div className="balance">
          <dt>Sisa tagihan</dt>
          <dd data-testid="remaining">{money(booking.remaining)}</dd>
        </div>
        <div>
          <dt>Status pembayaran</dt>
          <dd>{statusLabels[booking.payment_status]}</dd>
        </div>
      </dl>
      {booking.status === "pending" && (
        <p className="notice">
          Pembayaran awal {money(booking.initial_due)} sebelum{" "}
          {dateTime(booking.expires_at)} WIB. Hubungi pegawai untuk pencatatan
          demo. Jangan transfer uang nyata.
        </p>
      )}
      {booking.status === "pending" && (
        <HoldNotice
          key={booking.server_now}
          deadline={booking.expires_at}
          serverNow={booking.server_now}
        />
      )}
      {booking.status === "pending" && (
        <p className="field-help">
          Bukti pembayaran dan antrean verifikasi belum tersedia. Pembayaran
          yang masuk tetapi terlambat diperiksa belum ditangani Tahap 1.
        </p>
      )}
      {booking.status === "confirmed" && !booking.started_at && (
        <p className="notice">
          {booking.remaining
            ? "Lunasi sisa tagihan sebelum bermain."
            : "Tagihan lunas. Pegawai mencatat mulai bermain pada jam booking."}{" "}
          Booking terkonfirmasi tidak kedaluwarsa karena timer DP.
        </p>
      )}
      {booking.started_at && (
        <p className="success" role="status">
          Mulai bermain tercatat: {dateTime(booking.started_at)} WIB
          {booking.started_by ? ` · Pegawai #${booking.started_by}` : ""}.
        </p>
      )}
      {booking.status === "expired" && (
        <p className="notice">
          Tenggat berakhir. Slot sudah dilepas; booking ini tidak dapat menerima
          pembayaran. Buat booking baru sesuai ketersediaan.
        </p>
      )}
      {booking.status === "cancelled" && (
        <p className="notice">Booking dibatalkan. Riwayat tetap tersimpan.</p>
      )}
      <h3>Riwayat pembayaran demo</h3>
      {booking.payments.length ? (
        <ul className="payment-list">
          {booking.payments.map((p) => (
            <li key={p.id}>
              <div>
                <strong>{money(p.amount)}</strong>
                <small>
                  {p.kind === "dp"
                    ? "DP"
                    : p.kind === "full"
                      ? "Pembayaran penuh"
                      : p.kind === "settlement"
                        ? "Pelunasan"
                        : "Catatan awal"}
                </small>
                <small>
                  {dateTime(p.paid_at)} WIB ·{" "}
                  {p.method === "cash"
                    ? "Tunai"
                    : p.method === "qris"
                      ? "QRIS"
                      : "Transfer"}
                </small>
              </div>
              <span className="badge confirmed">Tercatat</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="empty">Belum ada pembayaran yang dicatat.</p>
      )}
    </>
  );
}
