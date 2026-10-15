"use client";
import { useEffect, useState } from "react";
import { dateTime } from "@/lib/api";

export default function HoldNotice({
  deadline,
  serverNow,
}: {
  deadline: string;
  serverNow: string;
}) {
  const [sample] = useState(() => ({
    server: Date.parse(serverNow),
    client: Date.now(),
  }));
  const [now, setNow] = useState(sample.server);
  useEffect(() => {
    const timer = setInterval(
      () => setNow(sample.server + Date.now() - sample.client),
      1000,
    );
    return () => clearInterval(timer);
  }, [sample]);
  const seconds = Math.max(0, Math.ceil((Date.parse(deadline) - now) / 1000));
  return (
    <div className="hold-notice">
      <span>Tenggat pencatatan pembayaran</span>
      <strong>
        {seconds
          ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
          : "Perbarui status"}
      </strong>
      <small>
        Sebelum {dateTime(deadline)} WIB. Status akhir mengikuti server.
      </small>
    </div>
  );
}
