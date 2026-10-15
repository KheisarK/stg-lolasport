import { test, expect, type Page } from "@playwright/test";
import { readFileSync, writeFileSync } from "node:fs";

const fixture = JSON.parse(
  readFileSync(process.env.LOLA_E2E_FIXTURE!, "utf8"),
) as {
  email: string;
  password: string;
  clock_path: string;
  now: string;
};
const day = "2026-10-16";
async function clock(page: Page, at: string) {
  writeFileSync(fixture.clock_path, JSON.stringify({ now: at }));
  await page.clock.setFixedTime(new Date(at));
}
async function noOverflow(page: Page) {
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
}
async function ready(page: Page) {
  await expect(
    page.getByRole("button", { name: /Futsal Sintetis/ }),
  ).toBeVisible();
  await expect(page.locator(".availability")).not.toHaveText(
    "Memeriksa jadwal…",
  );
}

test("booking → DP → mulai ditolak → pelunasan → mulai tercatat; rekap dan onsite", async ({
  page,
  browser,
}, info) => {
  const mobile = info.project.name === "mobile";
  const hour = mobile ? "13:00" : "10:00";
  const customer = `Pemesan Uji ${mobile ? "Mobile" : "Desktop"}`;
  await clock(page, fixture.now);
  await page.goto("/");
  await expect(
    page.getByText("Mode demo—tidak memproses uang nyata"),
  ).toBeVisible();
  await ready(page);
  // Re-selecting a current option must not leave the form loading forever.
  await page.getByRole("button", { name: /Futsal Sintetis/ }).click();
  await page.getByLabel("Tanggal bermain").fill(day);
  await page.getByLabel("Jam mulai (WIB)").selectOption(hour);
  await page.getByLabel("Nama pemesan").fill(customer);
  await page.getByLabel("Nomor kontak").fill("080000000001");
  await expect(page.getByRole("radio", { name: /DP/ })).toBeChecked();
  await noOverflow(page);
  await page.getByRole("button", { name: "Buat booking demo" }).click();
  await expect(
    page.getByRole("heading", { name: "Rencana main tersimpan." }),
  ).toBeVisible();
  await expect(
    page.getByText("Menunggu pembayaran", { exact: true }),
  ).toBeVisible();
  await expect(page.getByTestId("remaining")).toHaveText(/130.000/);
  const privateUrl = page.url();
  expect(privateUrl.split("#")[1]).toMatch(/^[a-f0-9]{64}$/);
  const reference = await page.locator(".booking-title .eyebrow").innerText();
  await noOverflow(page);
  expect(
    await (
      await page.request.get(`/api/availability?date=${day}&court_id=1`)
    ).text(),
  ).not.toContain(customer);
  expect(
    (await page.request.post("/api/bookings", { data: {} })).status(),
  ).toBe(419);
  await page.goto("/admin");
  await expect(page).toHaveURL(/\/admin\/login$/);
  await page.getByLabel("Email", { exact: true }).fill(fixture.email);
  await page.getByLabel("Kata sandi").fill(fixture.password);
  await page.getByRole("button", { name: "Masuk", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Ruang operasional" }),
  ).toBeVisible();
  const baseline = await (
    await page.request.get(`/api/admin/reports?period=day&date=${day}`)
  ).json();
  const expectedTotal = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(baseline.total + 130000);
  await page.getByLabel("Tanggal jadwal").fill(day);
  await page.getByRole("button", { name: new RegExp(customer) }).click();
  await expect(page.getByTestId("payment-amount")).toHaveText(/50.000/);
  await expect(page.locator('input[name="amount"]')).toHaveCount(0);
  await page
    .getByRole("combobox", { name: "Metode", exact: true })
    .selectOption("transfer");
  await page.getByRole("button", { name: "Tinjau pembayaran" }).click();
  const review = page.getByRole("region", { name: "Konfirmasi pembayaran" });
  await expect(review).toContainText(reference);
  await expect(review).toContainText("Transfer");
  await expect(review).toContainText("50.000");
  // Server commits; the browser loses its response and retries the same key/body.
  let committed: Record<string, unknown>;
  await page.route(
    "**/api/admin/bookings/*/payments",
    async (route) => {
      committed = route.request().postDataJSON();
      await route.fetch();
      await route.abort("failed");
    },
    { times: 1 },
  );
  await page.getByRole("button", { name: "Konfirmasi dan catat" }).click();
  await expect(page.locator(".payment-form p[role=alert]")).toContainText(
    "Koneksi terputus",
  );
  // Recover the same pending request even after the current server balance has changed.
  await page.reload();
  await expect(
    page.getByRole("heading", { name: "Ruang operasional" }),
  ).toBeVisible();
  await page.getByLabel("Tanggal jadwal").fill(day);
  await page.getByRole("button", { name: new RegExp(customer) }).click();
  await expect(page.getByTestId("payment-amount")).toHaveText(/50.000/);
  const retry = page.waitForRequest(
    (r) => r.url().endsWith("/payments") && r.method() === "POST",
  );
  await page.getByRole("button", { name: "Coba ulang pencatatan" }).click();
  expect((await retry).postDataJSON()).toEqual(committed!);
  await expect(page.getByTestId("remaining")).toHaveText(/80.000/);
  await expect(page.locator("article .payment-list li")).toHaveCount(1);
  await clock(page, `2026-10-16T${mobile ? "06" : "03"}:00:00+00:00`);
  await page.getByRole("button", { name: "Tandai mulai bermain" }).click();
  await expect(page.locator("main > .error")).toContainText(
    "Tagihan harus lunas",
  );
  await expect(page.getByLabel("Jenis pencatatan")).toContainText(
    "Pelunasan sisa tagihan",
  );
  await expect(page.getByTestId("payment-amount")).toHaveText(/80.000/);
  await page.getByRole("button", { name: "Tinjau pembayaran" }).click();
  await page.getByRole("button", { name: "Konfirmasi dan catat" }).click();
  await expect(page.getByTestId("remaining")).toHaveText(/Rp\s*0/);
  await page.getByRole("button", { name: "Tandai mulai bermain" }).click();
  await expect(page.locator("article")).toContainText("Mulai bermain tercatat");
  await expect(page.locator("article .payment-list li")).toHaveCount(2);
  await noOverflow(page);
  const customerContext = await browser.newContext();
  const status = await customerContext.newPage();
  await status.goto(privateUrl);
  await expect(status.locator(".detail-list")).toContainText("Lunas");
  await expect(status.locator("article")).toContainText(
    "Mulai bermain tercatat",
  );
  await status.goto(privateUrl.split("#")[0] + "#" + "0".repeat(64));
  await expect(status.locator("p[role=alert]")).toContainText("tidak valid");
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
  await clock(page, fixture.now);
  await page
    .getByRole("button", { name: "Booking WA / onsite", exact: true })
    .click();
  await ready(page);
  await page.getByRole("button", { name: /Badminton 1/ }).click();
  await page.getByLabel("Tanggal bermain").fill("2026-10-17");
  await page.getByLabel("Jam mulai (WIB)").selectOption(hour);
  await page.getByLabel("Nama pemesan").fill(`Onsite ${customer}`);
  await page.getByLabel("Nomor kontak").fill("080000000002");
  await page.getByLabel("Asal booking").selectOption("onsite");
  await expect(page.getByRole("radio", { name: /DP/ })).toBeEnabled();
  await page.getByRole("button", { name: "Simpan booking pegawai" }).click();
  await expect(page.locator(".booking-title")).toContainText("Badminton 1");
  page.once("dialog", (dialog) => dialog.accept());
  await page
    .getByRole("button", { name: "Batalkan booking", exact: true })
    .click();
  await expect(page.locator(".booking-title")).toContainText("Dibatalkan");
  await noOverflow(page);
  await page.getByRole("button", { name: "Keluar", exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/login$/);
  expect((await page.request.get("/api/admin/me")).status()).toBe(401);
});

test("ponsel/desktop: DP tiga jam tetap; mendadak penuh; selesai tengah malam", async ({
  page,
}, info) => {
  const mobile = info.project.name === "mobile";
  await clock(page, `2026-10-16T${mobile ? "03" : "02"}:30:00+00:00`);
  await page.goto("/");
  await ready(page);
  await page.getByLabel("Tanggal bermain").fill("2026-10-17");
  await page
    .getByRole("combobox", { name: "Durasi", exact: true })
    .selectOption("180");
  await expect(page.locator(".summary")).toContainText("390.000");
  await expect(page.getByRole("radio", { name: /DP/ })).toBeChecked();
  await expect(page.locator(".summary dl")).toContainText("50.000");
  await page.getByRole("button", { name: /Futsal Vinil/ }).click();
  await page
    .getByRole("combobox", { name: "Durasi", exact: true })
    .selectOption("60");
  await page.getByRole("button", { name: "Hari ini", exact: true }).click();
  await page
    .getByLabel("Jam mulai (WIB)")
    .selectOption(mobile ? "11:00" : "10:00");
  await expect(page.getByRole("radio", { name: /DP/ })).toBeDisabled();
  await expect(page.getByRole("radio", { name: /Bayar penuh/ })).toBeChecked();
  await expect(page.locator(".summary")).toContainText("Kurang dari 60 menit");
  await page.getByLabel("Nama pemesan").fill("Booking Mendadak Demo");
  await page.getByLabel("Nomor kontak").fill("080000000003");
  await page.getByRole("button", { name: "Buat booking demo" }).click();
  await expect(page.getByTestId("remaining")).toHaveText(/160.000/);
  await expect(page.locator(".notice").first()).toContainText("160.000");
  await page.goto("/");
  await ready(page);
  await page.getByRole("button", { name: /Badminton 2/ }).click();
  await page.getByLabel("Tanggal bermain").fill(day);
  await page.getByLabel("Jam mulai (WIB)").selectOption("23:00");
  await page.getByLabel("Nama pemesan").fill("Sesi Tengah Malam Demo");
  await page.getByLabel("Nomor kontak").fill("080000000004");
  await page.getByRole("button", { name: "Buat booking demo" }).click();
  await expect(page.locator("article")).toContainText("hari berikutnya");
  await expect(page.locator("article")).toContainText("17 Okt 2026");
  await noOverflow(page);
});

test("320px: tanpa overflow, kontrol utama mudah disentuh, pilihan durasi tetap jelas", async ({
  page,
}, info) => {
  await clock(page, fixture.now);
  await page.setViewportSize({ width: 320, height: 740 });
  await page.goto("/");
  await ready(page);
  await noOverflow(page);
  for (const selector of [
    ".court-option",
    ".quick-dates button",
    ".summary .primary",
  ]) {
    for (const target of await page.locator(selector).all()) {
      const size = await target.boundingBox();
      expect(size!.width).toBeGreaterThanOrEqual(48);
      expect(size!.height).toBeGreaterThanOrEqual(48);
    }
  }
  await page.getByLabel("Jam mulai (WIB)").selectOption("23:00");
  await page
    .getByRole("combobox", { name: "Durasi", exact: true })
    .selectOption("180");
  await expect(page.getByLabel("Jam mulai (WIB)")).toHaveValue("");
  await expect(
    page.getByRole("button", { name: "Buat booking demo" }),
  ).toBeDisabled();
  await page.getByLabel("Jam mulai (WIB)").selectOption("20:00");
  await expect(
    page.getByRole("button", { name: "Buat booking demo" }),
  ).toBeEnabled();
  await page.screenshot({
    path: `test-results/lola-v2-${info.project.name}-320.png`,
    fullPage: true,
  });
  await page.setViewportSize({ width: 1280, height: 900 });
  await noOverflow(page);
  await page.screenshot({
    path: `test-results/lola-v2-${info.project.name}-desktop.png`,
    fullPage: true,
  });
});
