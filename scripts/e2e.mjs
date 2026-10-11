import { spawn } from "node:child_process";
import { mkdtemp, mkdir, writeFile, rm } from "node:fs/promises";
import { existsSync } from "node:fs";
import { tmpdir } from "node:os";
import { randomBytes } from "node:crypto";
import { createServer } from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const directory = await mkdtemp(path.join(tmpdir(), "lola-e2e-"));
async function port() {
  const server = createServer();
  await new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(0, "127.0.0.1", resolve);
  });
  const selected = server.address().port;
  await new Promise((resolve) => server.close(resolve));
  return selected;
}
const backendPort = await port(),
  frontendPort = await port();
await writeFile(path.join(directory, "test.sqlite"), "");
await mkdir(path.join(directory, "sessions"));
await mkdir(path.join(directory, "cache"));
const env = {
  ...process.env,
  APP_ENV: "local",
  APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
  APP_DEBUG: "false",
  DB_DATABASE: path.join(directory, "test.sqlite"),
  DB_CONNECTION: "sqlite",
  SESSION_DRIVER: "file",
  SESSION_SECURE_COOKIE: "false",
  SESSION_FILES: path.join(directory, "sessions"),
  CACHE_PATH: path.join(directory, "cache"),
  CACHE_STORE: "file",
  BACKEND_URL: `http://127.0.0.1:${backendPort}`,
  LOLA_E2E_FIXTURE: path.join(directory, "credentials.json"),
  LOLA_E2E_BASE: `http://127.0.0.1:${frontendPort}`,
  LOLA_NEXT_DIST: ".next-e2e",
  NEXT_TELEMETRY_DISABLED: "1",
};
const services = [];
const php =
  process.env.PHP_BIN ||
  (existsSync("/workspace/lola-tools/bin/php")
    ? "/workspace/lola-tools/bin/php"
    : "php");
function run(command, args, cwd) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { cwd, env, stdio: "inherit" });
    child.on("error", reject);
    child.on("exit", (code) =>
      code === 0 ? resolve() : reject(new Error(`${command} exited ${code}`)),
    );
  });
}
function service(command, args, cwd) {
  const child = spawn(command, args, {
    cwd,
    env,
    stdio: ["ignore", "inherit", "inherit"],
    detached: process.platform !== "win32",
  });
  services.push(child);
  child.on("error", (error) => {
    console.error(error.message);
  });
  return child;
}
async function ready(url, child) {
  for (let i = 0; i < 120; i++) {
    if (child.exitCode !== null)
      throw new Error(`Service exited ${child.exitCode} before readiness`);
    try {
      if ((await fetch(url)).ok) return;
    } catch {
      /* Wait for startup. */
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  throw new Error(`Startup failed: ${url}`);
}
async function stop(child) {
  if (child.exitCode !== null) return;
  try {
    if (process.platform === "win32") child.kill("SIGTERM");
    else process.kill(-child.pid, "SIGTERM");
  } catch {
    /* Exited. */
  }
  for (let i = 0; i < 50 && child.exitCode === null; i++)
    await new Promise((resolve) => setTimeout(resolve, 100));
}
try {
  await run(
    php,
    ["tests/Support/browser-fixture.php", directory],
    path.join(root, "backend"),
  );
  let backend = service(
    php,
    [
      "artisan",
      "serve",
      "--no-reload",
      "--host=127.0.0.1",
      `--port=${backendPort}`,
    ],
    path.join(root, "backend"),
  );
  await ready(`${env.BACKEND_URL}/api/health`, backend);
  if (process.env.LOLA_E2E_MODE === "production")
    await run("npm", ["run", "build"], path.join(root, "frontend"));
  const frontend = service(
    "npm",
    [
      "run",
      process.env.LOLA_E2E_MODE === "production" ? "start" : "dev",
      "--",
      "--port",
      String(frontendPort),
    ],
    path.join(root, "frontend"),
  );
  await ready(`${env.LOLA_E2E_BASE}/api/health`, frontend);
  await run("npm", ["run", "test:e2e"], path.join(root, "frontend"));
  // Stop and start the actual HTTP backend against the same database, then inspect persisted records.
  await stop(backend);
  backend = service(
    php,
    [
      "artisan",
      "serve",
      "--no-reload",
      "--host=127.0.0.1",
      `--port=${backendPort}`,
    ],
    path.join(root, "backend"),
  );
  await ready(`${env.BACKEND_URL}/api/health`, backend);
  await run(
    php,
    ["tests/Support/check-persistence.php"],
    path.join(root, "backend"),
  );
  console.log(
    "Backend stopped/restarted; demo bookings and payments remained intact.",
  );
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
} finally {
  for (const child of services) await stop(child);
  await rm(directory, { recursive: true, force: true });
}
