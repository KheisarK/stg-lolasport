import { existsSync } from "node:fs";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";
import path from "node:path";
import { npmInvocation, stopService } from "./process.mjs";
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
for (const file of [
  "backend/vendor/autoload.php",
  "backend/.env",
  "frontend/node_modules/next/package.json",
]) {
  if (!existsSync(path.join(root, file))) {
    console.error(
      "Setup belum selesai. Windows: powershell -NoProfile -ExecutionPolicy Bypass -File .\\scripts\\setup.ps1; Linux/macOS: bash scripts/setup.sh.",
    );
    process.exit(1);
  }
}
const children = [];
let stopping = false;
async function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  await Promise.all(children.map(stopService));
  setTimeout(() => process.exit(code), 300);
}
const [npm, npmArgs] = npmInvocation([
  "run",
  "dev",
  "--",
  "--port",
  process.env.FRONTEND_PORT || "3000",
]);
for (const [command, args, folder] of [
  [
    process.env.PHP_BIN ||
      (existsSync("/workspace/lola-tools/bin/php")
        ? "/workspace/lola-tools/bin/php"
        : "php"),
    [
      "artisan",
      "serve",
      "--no-reload",
      "--host=127.0.0.1",
      `--port=${process.env.BACKEND_PORT || 8000}`,
    ],
    "backend",
  ],
  [npm, npmArgs, "frontend"],
]) {
  const child = spawn(command, args, {
    cwd: path.join(root, folder),
    stdio: "inherit",
    detached: process.platform !== "win32",
    env: {
      ...process.env,
      BACKEND_URL: `http://127.0.0.1:${process.env.BACKEND_PORT || 8000}`,
    },
  });
  children.push(child);
  child.on("error", (error) => {
    console.error(error.message);
    stop(1);
  });
  child.on("exit", (code) => {
    if (!stopping) stop(code || 1);
  });
}
process.on("SIGINT", () => stop());
process.on("SIGTERM", () => stop());
