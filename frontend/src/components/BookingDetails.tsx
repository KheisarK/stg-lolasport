import { money, dateTime, time, statusLabels, type Booking } from "@/lib/api";

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
      <p>
        {dateTime(booking.starts_at)}–{time(booking.ends_at)} WIB
      </p>
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
          Pembayaran pertama minimum {money(booking.initial_due)} sebelum{" "}
          {dateTime(booking.expires_at)} WIB. Hubungi pegawai untuk pencatatan
          demo. Jangan transfer uang nyata.
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
