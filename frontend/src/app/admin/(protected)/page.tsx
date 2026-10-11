import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import AdminDashboard from "@/components/AdminDashboard";
export const dynamic = "force-dynamic";
export default async function AdminPage() {
  const cookie = (await cookies()).toString();
  const response = await fetch(
    `${process.env.BACKEND_URL || "http://127.0.0.1:8000"}/api/admin/me`,
    {
      headers: { Cookie: cookie, Accept: "application/json" },
      cache: "no-store",
    },
  ).catch(() => null);
  if (response?.status === 401 || response?.status === 403)
    redirect("/admin/login");
  if (!response?.ok)
    return (
      <section className="panel">
        <h1>Layanan belum tersedia</h1>
        <p>Jadwal belum dapat dimuat. Coba muat ulang sebentar lagi.</p>
      </section>
    );
  const { user } = await response.json();
  return <AdminDashboard name={user.name} />;
}
