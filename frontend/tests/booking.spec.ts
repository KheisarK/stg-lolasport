import { test, expect } from "@playwright/test";
import { readFileSync } from "node:fs";

const credentials = JSON.parse(
  readFileSync(process.env.LOLA_E2E_FIXTURE!, "utf8"),
) as { email: string; password: string };
function tomorrow() {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date(Date.now() + 86400000));
}

test("booking → admin DP → pelunasan → rekap; WA, pembatalan, akses dan CSRF", async ({
  page,
  browser,
}, info) => {
  const mobile = info.project.name === "mobile";
  const customer = `Pemesan Uji ${mobile ? "Mobile" : "Desktop"}`;
  const hour = mobile ? "13:00" : "10:00";
  console.log(`${info.project.name}: reservasi pelanggan`);
  await page.goto("/");
  await expect(
    page.getByText("Mode demo—tidak memproses uang nyata"),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: /Futsal Sintetis/ }),
  ).toBeVisible();
  await expect(page.locator(".availability")).toContainText(
    mobile ? "Terisi: 10.00–11.00 WIB." : "Belum ada booking aktif",
  );
  // Selecting the existing court/date must not leave the form loading forever.
  await page.getByRole("button", { name: /Futsal Sintetis/ }).click();
  await page.getByLabel("Tanggal bermain").fill(tomorrow());
  await page.getByLabel("Jam mulai (WIB)").selectOption(hour);
  await page.getByLabel("Nama pemesan").fill(customer);
  await page.getByLabel("Nomor kontak").fill("080000000001");
  await page.getByRole("button", { name: "Buat booking demo" }).click();
  await expect(
    page.getByRole("heading", { name: "Rencana main tersimpan." }),
  ).toBeVisible();
  await expect(
    page.getByText("Menunggu pembayaran", { exact: true }),
  ).toBeVisible();
  await expect(page.getByTestId("remaining")).toHaveText(/150.000/);
  const privateUrl = page.url();
  expect(privateUrl.split("#")[1]).toMatch(/^[a-f0-9]{64}$/);
  const reference = await page.locator(".booking-title .eyebrow").innerText();
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  const publicSchedule = await page.request.get(
    `/api/availability?date=${tomorrow()}&court_id=1`,
  );
  expect(await publicSchedule.text()).not.toContain(customer);
  const noCsrf = await page.request.post("/api/bookings", { data: {} });
  expect(noCsrf.status()).toBe(419);
  console.log(`${info.project.name}: login dan pembayaran`);
  await page.goto("/admin");
  await expect(page).toHaveURL(/\/admin\/login$/);
  await page.getByLabel("Email", { exact: true }).fill(credentials.email);
  await page.getByLabel("Kata sandi").fill(credentials.password);
  await page.getByRole("button", { name: "Masuk", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Ruang operasional" }),
  ).toBeVisible();
  const baseline = await (
    await page.request.get(
      `/api/admin/reports?period=day&date=${new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Jakarta", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date())}`,
    )
  ).json();
  const expectedTotal = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(baseline.total + 150000);
  await page.getByLabel("Tanggal jadwal").fill(tomorrow());
  await page.getByRole("button", { name: new RegExp(customer) }).click();
  await page.getByLabel("Nominal (rupiah)").fill("45000");
  // Simulate a committed payment whose response is lost, then retry the same request.
  await page.route(
    "**/api/admin/bookings/*/payments",
    async (route) => {
      await route.fetch();
      await route.abort("failed");
    },
    { times: 1 },
  );
  await page.getByRole("button", { name: "Catat pembayaran demo" }).click();
  await expect(page.locator(".payment-form p[role=alert]")).toContainText(
    "Koneksi terputus",
  );
  await page.getByRole("button", { name: "Catat pembayaran demo" }).click();
  await expect(page.getByTestId("remaining")).toHaveText(/105.000/);
  await expect(page.locator("article .payment-list li")).toHaveCount(1);
  await page.getByLabel("Nominal (rupiah)").fill("105000");
  await page.getByRole("button", { name: "Catat pembayaran demo" }).click();
  await expect(page.getByTestId("remaining")).toHaveText(/Rp\s*0/);
  await expect(page.locator(".detail-list")).toContainText("Lunas");
  console.log(`${info.project.name}: status pelanggan dan rekap`);
  const customerContext = await browser.newContext();
  const statusPage = await customerContext.newPage();
  await statusPage.goto(privateUrl);
  await expect(statusPage.locator(".detail-list")).toContainText("Lunas");
  await statusPage.goto(privateUrl.split("#")[0] + "#" + "0".repeat(64));
  await expect(statusPage.locator("p[role=alert]")).toContainText(
    "tidak valid",
  );
  await customerContext.close();
  await page
    .getByRole("button", { name: "Rekap penerimaan", exact: true })
    .click();
  await expect(page.locator(".report-total")).toHaveText(expectedTotal);
  await expect(page.locator(".payment-list")).toContainText(reference);
  await page
    .getByRole("combobox", { name: "Periode", exact: true })
    .selectOption("month");
  await expect(page.locator(".report-total")).toHaveText(expectedTotal);
  console.log(`${info.project.name}: booking pegawai dan pembatalan`);
  await page
    .getByRole("button", { name: "Booking WA / onsite", exact: true })
    .click();
  await page.getByRole("button", { name: /Badminton 1/ }).click();
  await page.getByLabel("Jam mulai (WIB)").selectOption(hour);
  await page.getByLabel("Nama pemesan").fill(`WA ${customer}`);
  await page.getByLabel("Nomor kontak").fill("080000000002");
  await page.getByLabel("Asal booking").selectOption("whatsapp");
  await page.getByRole("button", { name: "Simpan booking pegawai" }).click();
  await expect(page.locator(".booking-title")).toContainText("Badminton 1");
  page.once("dialog", (dialog) => dialog.accept());
  await page
    .getByRole("button", { name: "Batalkan booking", exact: true })
    .click();
  await expect(page.locator(".booking-title")).toContainText("Dibatalkan");
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await page.getByRole("button", { name: "Keluar", exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/login$/);
  expect((await page.request.get("/api/admin/me")).status()).toBe(401);
});
