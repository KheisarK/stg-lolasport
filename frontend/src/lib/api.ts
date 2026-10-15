export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
  }
}

export async function api<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body) headers.set("Content-Type", "application/json");
  if (options.method && options.method !== "GET") {
    const csrf = await fetch("/api/csrf", {
      credentials: "same-origin",
      cache: "no-store",
    });
    if (!csrf.ok)
      throw new ApiError(
        "Sesi belum tersedia. Tunggu sebentar lalu coba lagi.",
        csrf.status,
      );
    const { token } = await csrf.json();
    headers.set("X-CSRF-TOKEN", token);
  }
  let response: Response;
  try {
    response = await fetch(`/api${path}`, {
      ...options,
      headers,
      credentials: "same-origin",
      cache: "no-store",
    });
  } catch {
    throw new ApiError(
      "Koneksi terputus. Periksa status sebelum mengirim ulang booking. Pembayaran dapat dicoba ulang dengan isi yang sama.",
      0,
    );
  }
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const errors = data.errors
      ? Object.values(data.errors).flat().join(" ")
      : "";
    throw new ApiError(
      response.status === 419
        ? "Sesi berakhir. Muat ulang lalu coba lagi."
        : response.status === 429
          ? "Terlalu banyak percobaan. Tunggu satu menit."
          : errors ||
            data.message ||
            "Layanan belum tersedia. Coba lagi sebentar.",
      response.status,
    );
  }
  return data as T;
}
export const money = (value: number) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
export const dateTime = (value: string) =>
  new Intl.DateTimeFormat("id-ID", {
    timeZone: "Asia/Jakarta",
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
export const time = (value: string) =>
  new Intl.DateTimeFormat("id-ID", {
    timeZone: "Asia/Jakarta",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  }).format(new Date(value));
export const schedule = (start: string, end: string) =>
  jakartaDate(0, Date.parse(start)) === jakartaDate(0, Date.parse(end))
    ? `${dateTime(start)}–${time(end)} WIB`
    : `${dateTime(start)}–${dateTime(end)} WIB (hari berikutnya)`;
export function jakartaDate(offset = 0, reference = Date.now()) {
  const date = new Date(reference + offset * 86400000);
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(date);
}
export const statusLabels: Record<string, string> = {
  pending: "Menunggu pembayaran",
  confirmed: "Terkonfirmasi",
  expired: "Kedaluwarsa",
  cancelled: "Dibatalkan",
  unpaid: "Belum dibayar",
  partial: "Dibayar sebagian",
  paid: "Lunas",
};
export type Court = {
  id: number;
  name: string;
  sport: string;
  hourly_rate: number;
};
export type Rules = {
  open_hour: number;
  close_hour: number;
  durations: number[];
  dp_amount: number;
  full_payment_within_minutes: number;
  hold_minutes: number;
  advance_days: number;
};
export type Payment = {
  id: number;
  amount: number;
  method: string;
  paid_at: string;
  recorded_by?: number;
  reference?: string;
  kind?: string | null;
};
export type PaymentChoice = {
  kind: "dp" | "full" | "settlement";
  label: string;
  amount: number;
};
export type Booking = {
  id: number;
  reference: string;
  court_name: string;
  customer_name: string;
  contact: string;
  channel: string;
  starts_at: string;
  ends_at: string;
  expires_at: string;
  total: number;
  initial_due: number;
  paid: number;
  remaining: number;
  status: string;
  payment_status: string;
  payments: Payment[];
  payment_option: string;
  payment_choices: PaymentChoice[];
  started_at: string | null;
  started_by?: number | null;
  server_now: string;
};
